<?php
/**
 * Shared harness for the two smoke suites.
 *
 *   php tools/smoke.php        26 command-level checks: sign-in throttle, stock
 *                              reservation, cart clamp, abandoned-order sweep,
 *                              Paystack verification guard
 *   php tools/smoke_http.php   37 end-to-end checks over HTTP; it starts and
 *                              stops its own pair of PHP dev servers
 *
 * Neither suite ever touches the application database. They work in a throwaway
 * <DB_NAME>_smoke, created here from schema.sql + seed.sql when it is missing
 * and dropped again at the end (keep it with --keep, or SMOKE_KEEP=1).
 *
 * Run the two suites one at a time - they share that database name.
 *
 * CLI only: /tools/ is web-denied by .htaccess apart from doctor.php, and the
 * SAPI guard below is the second lock on that door.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$GLOBALS['smoke_pass']     = 0;
$GLOBALS['smoke_fail']     = 0;
$GLOBALS['smoke_run']      = bin2hex(random_bytes(4));
$GLOBALS['smoke_boot']     = null;
$GLOBALS['smoke_servers']  = [];
$GLOBALS['smoke_shutdown'] = false;
$GLOBALS['smoke_keep_log'] = false;

function smoke_root(): string
{
    return dirname(__DIR__);
}

/**
 * Reads an environment variable, falling back to the project .env. Needed
 * before config.php is loaded (that file defines its own env()).
 */
function smoke_env(string $key, string $default = ''): string
{
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return $v;
    }
    static $parsed = null;
    if ($parsed === null) {
        $parsed = [];
        $file   = smoke_root() . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $val] = explode('=', $line, 2);
                $val = trim($val);
                if (strlen($val) > 1 && ($val[0] === '"' || $val[0] === "'") && str_ends_with($val, $val[0])) {
                    $val = substr($val, 1, -1);
                }
                $parsed[trim($k)] = $val;
            }
        }
    }
    return $parsed[$key] ?? $default;
}

function smoke_keep(): bool
{
    return in_array('--keep', $GLOBALS['argv'] ?? [], true) || getenv('SMOKE_KEEP') === '1';
}

/** A fresh password for whatever account a suite needs to sign in as. */
function smoke_password(): string
{
    return 'Smk-' . bin2hex(random_bytes(6)) . '-1!';
}

/**
 * Provisions the throwaway database, then boots config.php (and with it
 * functions.php) against it. Returns root / name / created / pdo / sys.
 */
function smoke_boot(): array
{
    $root = smoke_root();
    $host = smoke_env('DB_HOST', '127.0.0.1');
    $user = smoke_env('DB_USER', 'root');
    $pass = smoke_env('DB_PASS', '');
    $name = getenv('SMOKE_DB') ?: smoke_env('DB_NAME', 'velora_shop') . '_smoke';

    // The sweeper's rate-limit stamp lives in shared storage: remember whether
    // it existed before we started, so teardown only removes one we caused.
    $GLOBALS['smoke_stamp']     = $root . '/storage/logs/last-stock-release';
    $GLOBALS['smoke_stamp_pre'] = is_file($GLOBALS['smoke_stamp']);

    $opts = [
        PDO::ATTR_ERRMODE                => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE     => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true, // schema.sql / seed.sql are multi-statement files
    ];

    try {
        $sys    = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, $opts);
        $exists = (int) $sys->query(
            'SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ' . $sys->quote($name)
        )->fetchColumn() > 0;
        if (!$exists) {
            $sys->exec("CREATE DATABASE `{$name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        $pdo    = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, $opts);
        $tables = (int) $sys->query(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ' . $sys->quote($name)
        )->fetchColumn();
        if ($tables === 0) {
            $pdo->exec((string) file_get_contents($root . '/schema.sql'));
            $pdo->exec((string) file_get_contents($root . '/seed.sql'));
        }
    } catch (PDOException $e) {
        fwrite(STDERR, "smoke: could not provision `{$name}` - {$e->getMessage()}\n");
        fwrite(STDERR, "smoke: the database user needs CREATE/DROP for a throwaway schema, or\n");
        fwrite(STDERR, "       point SMOKE_DB at an empty database that user already owns.\n");
        exit(2);
    }

    // config.php lets the real environment win over .env, which is exactly how
    // this app - and every child server the HTTP suite spawns - gets pinned to
    // the throwaway database instead of the real one.
    putenv("DB_NAME={$name}");
    $_ENV['DB_NAME']     = $name;
    $_SERVER['DB_NAME']  = $name;

    require $root . '/config.php';

    return $GLOBALS['smoke_boot'] = [
        'root'    => $root,
        'name'    => $name,
        'created' => !$exists,
        'pdo'     => $pdo,
        'sys'     => $sys,
    ];
}

/* --------------------------------------------------------------- reporting */

function group(string $title): void
{
    echo "\n== $title ==\n";
}

function okk(string $name, bool $cond, string $detail = ''): void
{
    if ($cond) {
        $GLOBALS['smoke_pass']++;
        echo "  [PASS] $name\n";
        return;
    }
    $GLOBALS['smoke_fail']++;
    echo '  [FAIL] ' . $name . ($detail !== '' ? " - $detail" : '') . "\n";
}

function smoke_summary(string $suite): void
{
    smoke_teardown();
    echo "\n----------------------------------------\n";
    echo "$suite: passed {$GLOBALS['smoke_pass']}   failed {$GLOBALS['smoke_fail']}\n";
    exit($GLOBALS['smoke_fail'] > 0 ? 1 : 0);
}

/* ------------------------------------------------- child PHP dev servers */

function smoke_server(int $port, array $env): array
{
    $root = smoke_root();
    $base = "http://127.0.0.1:{$port}";
    $log  = sys_get_temp_dir() . '/velora_smoke_srv_' . $port . '_' . $GLOBALS['smoke_run'] . '.log';
    @unlink($log);

    // A leftover server from an earlier run would answer with its own
    // environment (possibly the real database), so refuse to share the port.
    if (req($base . '/index.php')['code'] !== 0) {
        fwrite(STDERR, "smoke: something already answers on {$base} - stop it first (its environment would be used).\n");
        exit(2);
    }

    // stdout/stderr go to a file, not a pipe: `php -S` logs every request, and
    // an undrained pipe would fill up and stall it mid-run.
    $descriptors = [
        0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => ['file', $log, 'a'],
        2 => ['file', $log, 'a'],
    ];

    $proc = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root],
        $descriptors,
        $pipes,
        $root,
        array_merge(getenv() ?: [], $env)
    );
    if (!is_resource($proc)) {
        fwrite(STDERR, "smoke: could not start a PHP server on port {$port}\n");
        exit(2);
    }
    return ['proc' => $proc, 'log' => $log, 'base' => "http://127.0.0.1:{$port}"];
}

function smoke_wait(array $server, float $seconds = 25.0): void
{
    $until = microtime(true) + $seconds;
    while (microtime(true) < $until) {
        if (req($server['base'] . '/index.php')['code'] !== 0) {
            return;
        }
        usleep(250000);
    }
    $GLOBALS['smoke_keep_log'] = true;
    fwrite(STDERR, "smoke: {$server['base']} never answered.\n--- server log ---\n");
    fwrite(STDERR, (string) @file_get_contents($server['log']) . "\n");
    exit(2);
}

function smoke_stop(array $server): void
{
    if (is_resource($server['proc'])) {
        @proc_terminate($server['proc']);
        @proc_close($server['proc']);
    }
    if (!$GLOBALS['smoke_keep_log']) {
        @unlink($server['log']);
    }
}

function smoke_teardown(): void
{
    if ($GLOBALS['smoke_shutdown']) {
        return;
    }
    $GLOBALS['smoke_shutdown'] = true;

    foreach ($GLOBALS['smoke_servers'] as $server) {
        smoke_stop($server);
    }
    $GLOBALS['smoke_servers'] = [];

    foreach (glob(sys_get_temp_dir() . '/velora_jar_' . $GLOBALS['smoke_run'] . '_*.txt') ?: [] as $jar) {
        @unlink($jar);
    }
    // Our own page hits fire the sweeper hook, which writes the shared stamp.
    // If we are the ones who created it, take it back so a real sweep is not
    // blocked for the next ten minutes.
    if (is_file((string) ($GLOBALS['smoke_stamp'] ?? '')) && empty($GLOBALS['smoke_stamp_pre'])) {
        @unlink((string) $GLOBALS['smoke_stamp']);
    }

    $boot = $GLOBALS['smoke_boot'];
    if ($boot === null) {
        return;
    }
    if (!$boot['created'] || smoke_keep()) {
        printf(
            "\n(smoke database `%s` kept%s)\n",
            $boot['name'],
            $boot['created'] ? ' - --keep' : ' - it already existed'
        );
        return;
    }
    try {
        $boot['sys']->exec('DROP DATABASE IF EXISTS `' . $boot['name'] . '`');
        printf("\n(dropped smoke database `%s`)\n", $boot['name']);
    } catch (Throwable $e) {
        fwrite(STDERR, "smoke: could not drop `{$boot['name']}`: {$e->getMessage()}\n");
    }
}

register_shutdown_function('smoke_teardown');

/* ---------------------------------------------------------- HTTP helpers */

function smoke_jar(string $tag): string
{
    return sys_get_temp_dir() . '/velora_jar_' . $GLOBALS['smoke_run'] . '_' . $tag . '.txt';
}

/** One request. $post null = GET. Cookies persist through $jar (a file path). */
function req(string $url, ?array $post = null, ?string $jar = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_USERAGENT      => 'velora-smoke/1.0',
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw       = (string) curl_exec($ch);
    $code      = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerLen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $redirect  = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    $headers = substr($raw, 0, $headerLen);
    $body    = substr($raw, $headerLen);
    if ($redirect === '' && preg_match('#^Location:\s*(.+?)\s*$#mi', $headers, $m)) {
        $redirect = trim($m[1]);
    }
    return ['code' => $code, 'body' => $body, 'headers' => $headers, 'location' => $redirect];
}

function csrf_of(string $html): string
{
    return preg_match('/name="csrf" value="([a-f0-9]{64})"/', $html, $m) ? $m[1] : '';
}

/** Extracts PHP diagnostics from a body (only visible while APP_DEBUG is on). */
function dirty(string $body): string
{
    if (!preg_match('/(Fatal error|Uncaught \w+|Deprecated:|Warning:)/', $body, $m)) {
        return '';
    }
    preg_match('/(.{0,120}(?:Fatal error|Uncaught \w+|Deprecated:|Warning:).{0,160})/s', $body, $n);
    return trim(preg_replace('/\s+/', ' ', $n[1] ?? $m[1]));
}
