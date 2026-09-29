<?php
/**
 * Zion Groups - pre-flight check for a new server.
 *
 * CLI:   php tools/doctor.php        (exit code 1 when something fails)
 * Web:   /tools/doctor.php           (DELETE THIS FILE after checking)
 *
 * Prints PASS / WARN / FAIL rows only - never secret values.
 */

declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
$root  = dirname(__DIR__);

$rows = [];
function chk(string $label, string $status, string $detail = ''): void
{
    global $rows;
    $rows[] = [$label, $status, $detail];
}

/* --------------------------------------------------------------- .env ---- */
$env = [];
if (is_readable($root . '/.env')) {
    foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (($v !== '' && ($v[0] === '"' || $v[0] === "'") && str_ends_with($v, $v[0]))) {
            $v = substr($v, 1, -1);
        }
        if ($k !== '') {
            $env[$k] = $v;
        }
    }
}
$get = static fn(string $k, string $d = '') => $env[$k] ?? (getenv($k) !== false && getenv($k) !== '' ? (string) getenv($k) : $d);

chk('.env file', $env !== [] ? 'PASS' : 'WARN',
    $env !== [] ? 'found' : 'missing - copy .env.example to .env and fill in the host values');

$appEnv   = $get('APP_ENV', 'development');
$appDebug = $get('APP_DEBUG', $appEnv === 'production' ? 'false' : 'true');
chk('APP_ENV / APP_DEBUG', ($appEnv === 'production' && $appDebug === 'true') ? 'FAIL' : 'PASS',
    "APP_ENV=$appEnv, APP_DEBUG=$appDebug"
    . (($appEnv === 'production' && $appDebug === 'true') ? ' - set APP_DEBUG=false before going live' : ''));
if ($get('APP_URL') === '') {
    chk('APP_URL', 'WARN', 'not set - absolute links fall back to the live host (set it once the domain is known)');
} else {
    chk('APP_URL', 'PASS', 'set');
}

/* -------------------------------------------------------------- PHP ------ */
chk('PHP version', PHP_VERSION >= '8.2.0' ? 'PASS' : 'FAIL', PHP_VERSION . ' (need 8.2+)');

foreach (['pdo_mysql' => 'database', 'mbstring' => 'text handling', 'fileinfo' => 'image uploads', 'session' => 'login/cart', 'filter' => 'validation'] as $ext => $why) {
    chk("extension $ext", extension_loaded($ext) ? 'PASS' : 'FAIL', $why);
}

$cliIni = static fn(string $k): string => (string) ini_get($k);
$toBytes = static function (string $v): int {
    $v = trim($v);
    if ($v === '' || $v === '-1') {
        return PHP_INT_MAX;
    }
    $n = (int) $v;
    return match (strtolower(substr($v, -1))) {
        'g' => $n * 1073741824,
        'm' => $n * 1048576,
        'k' => $n * 1024,
        default => $n,
    };
};

chk('file uploads enabled', filter_var(ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN) ? 'PASS' : 'FAIL', 'admin image uploads need this');
$upMax = $toBytes($cliIni('upload_max_filesize'));
$postMax = $toBytes($cliIni('post_max_size'));
chk('upload limits >= 8 MB', ($upMax >= 8 * 1048576 && $postMax >= 8 * 1048576) ? 'PASS' : 'WARN',
    'upload_max_filesize=' . $cliIni('upload_max_filesize') . ', post_max_size=' . $cliIni('post_max_size')
    . (($upMax < 8 * 1048576 || $postMax < 8 * 1048576) ? ' - raise via .user.ini / php.ini' : ''));

/* ---------------------------------------------------- storage folders ---- */
foreach (['storage/logs', 'storage/uploads'] as $dir) {
    $path = $root . '/' . $dir;
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);
    chk("$dir writable", ($exists && $writable) ? 'PASS' : 'FAIL',
        !$exists ? 'folder missing - create it with chmod 755 (or 775)' : ($writable ? 'ok' : 'chmod -R 755 (or 775) the folder'));
}

/* ---------------------------------------------------------- database ----- */
$dbHost = $get('DB_HOST', '127.0.0.1');
$dbName = $get('DB_NAME', 'velora_shop');
$dbUser = $get('DB_USER', 'root');
$dbPass = $get('DB_PASS', '');

$pdo = null;
try {
    $dsn = 'mysql:host=' . $dbHost . ';port=' . (int) $get('DB_PORT', '3306') . ';dbname=' . $dbName . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT            => 8,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    chk('database connection', 'PASS', $isCli ? "$dbUser@$dbHost/$dbName" : 'connected');
} catch (Throwable $e) {
    chk('database connection', 'FAIL', $isCli ? $e->getMessage() : 'could not connect - check DB_* in .env');
}

if ($pdo !== null) {
    try {
        $wanted = ['categories', 'brands', 'products', 'product_images', 'product_variants', 'product_specs',
            'product_bundles', 'users', 'addresses', 'wishlist', 'cart_items', 'promo_codes', 'orders',
            'order_items', 'order_events', 'settings', 'contact_messages', 'reviews'];
        $present = [];
        foreach ($pdo->query('SHOW TABLES') as $row) {
            $present[] = (string) $row[0];
        }
        $missing = array_values(array_diff($wanted, $present));
        chk('schema imported', $missing === [] ? 'PASS' : 'FAIL',
            $missing === [] ? count($wanted) . ' tables present'
                : 'missing: ' . implode(', ', $missing) . ' - import schema.sql (then seed.sql)');

        if ($missing === []) {
            $products = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
            $active   = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();
            $admins   = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            chk('catalogue data', $active > 0 ? 'PASS' : 'WARN', "$products products ($active live)");
            chk('admin account', $admins > 0 ? 'PASS' : 'WARN', $admins > 0 ? "$admins admin user(s) exist" : 'no admin user - create one before first login');
        }
    } catch (Throwable $e) {
        chk('database queries', 'FAIL', $isCli ? $e->getMessage() : 'server error while reading tables');
    }
}

/* ------------------------------------------------------------ apache ----- */
if (!$isCli) {
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    chk('HTTPS', $https ? 'PASS' : 'WARN', $https ? 'active' : 'not active yet - install the certificate, then uncomment the HTTPS block in .htaccess');
    if (function_exists('apache_get_modules')) {
        $mods = apache_get_modules();
        chk('mod_rewrite', in_array('mod_rewrite', $mods, true) ? 'PASS' : 'FAIL', 'robots.txt / sitemap.xml rewrites need it');
        chk('mod_headers', in_array('mod_headers', $mods, true) ? 'PASS' : 'WARN', in_array('mod_headers', $mods, true) ? 'active' : 'missing - security headers in .htaccess are skipped');
    }
}
chk('.htaccess present', is_file($root . '/.htaccess') ? 'PASS' : 'FAIL', 'Apache document roots need it (AllowOverride All)');

/* ------------------------------------------------------------- out ------ */
$fails = count(array_filter($rows, static fn($r) => $r[1] === 'FAIL'));
$warns = count(array_filter($rows, static fn($r) => $r[1] === 'WARN'));

if ($isCli) {
    foreach ($rows as [$label, $status, $detail]) {
        printf("%-4s %-28s %s%s\n", '[' . $status . ']', $label, $detail !== '' ? $detail : '', PHP_EOL);
    }
    printf("\n%d checks: %d failed, %d warnings\n", count($rows), $fails, $warns);
    exit($fails > 0 ? 1 : 0);
}

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><title>Zion - server check</title>';
echo '<style>body{font:14px/1.5 ui-monospace,Consolas,monospace;margin:2rem;background:#111;color:#eee}'
    . 'table{border-collapse:collapse;width:100%;max-width:900px}'
    . 'td,th{border-bottom:1px solid #333;padding:.5rem;text-align:left;vertical-align:top}'
    . '.PASS{color:#7ee787}.WARN{color:#e3b341}.FAIL{color:#ff7b72;font-weight:700}'
    . '.banner{background:#7a1220;color:#fff;padding:1rem;margin-bottom:1.5rem;max-width:900px}</style>';
echo '<div class="banner"><strong>Delete /tools/doctor.php once you have read this page.</strong></div>';
echo '<h1>Zion server check</h1><table><tr><th>Status</th><th>Check</th><th>Detail</th></tr>';
foreach ($rows as [$label, $status, $detail]) {
    echo '<tr><td class="' . $status . '">' . $status . '</td><td>' . htmlspecialchars($label) . '</td><td>'
        . htmlspecialchars($detail) . '</td></tr>';
}
echo '</table><p>' . count($rows) . ' checks: ' . $fails . ' failed, ' . $warns . " warnings</p>";
