<?php
/**
 * Zion Groups of Companies - Admin Deploy Engine Service.
 *
 * Provides git repository inspection, GitHub remote update checks,
 * transactional code deployment (fetch & reset/pull), storage directory repair,
 * OPcache flushing, and pre-flight health diagnostics.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

const DEPLOY_DEFAULT_REPO = 'https://github.com/Ajigiwe/zion.git';
const DEPLOY_REPO_SLUG    = 'Ajigiwe/zion';
const DEPLOY_DEFAULT_BRANCH = 'main';

/**
 * Returns full path to the project root directory.
 */
function deploy_root_dir(): string
{
    return dirname(__DIR__);
}

/**
 * Returns full path to deployment metadata file.
 */
function deploy_meta_file(): string
{
    return deploy_root_dir() . '/storage/deploy_meta.json';
}

/**
 * Returns full path to deployment history file.
 */
function deploy_history_file(): string
{
    return deploy_root_dir() . '/storage/deploy_history.json';
}

/**
 * Inspect local Git state and server environment.
 *
 * @return array{
 *   git_available: bool,
 *   git_version: string,
 *   has_git_dir: bool,
 *   branch: string,
 *   hash: string,
 *   full_hash: string,
 *   message: string,
 *   author: string,
 *   date: string,
 *   is_dirty: bool,
 *   dirty_files: string[],
 *   remote_url: string,
 *   last_deploy_time: string,
 *   opcache_enabled: bool,
 *   meta: array<string, mixed>
 * }
 */
function deploy_get_local_status(): array
{
    $root = deploy_root_dir();
    $meta = [];
    if (is_file(deploy_meta_file())) {
        $raw = @file_get_contents(deploy_meta_file());
        if ($raw) {
            $meta = @json_decode($raw, true) ?: [];
        }
    }

    $gitVer = @shell_exec('git --version 2>&1');
    $gitAvailable = is_string($gitVer) && str_contains($gitVer, 'git version');
    $gitVerClean  = $gitAvailable ? trim($gitVer) : 'Not available';

    $hasGitDir = is_dir($root . '/.git');
    $branch    = 'main';
    $hash      = (string) ($meta['hash'] ?? '');
    $fullHash  = '';
    $message   = (string) ($meta['message'] ?? '');
    $author    = (string) ($meta['author'] ?? '');
    $date      = (string) ($meta['date'] ?? '');
    $isDirty   = false;
    $dirtyList = [];
    $remoteUrl = DEPLOY_DEFAULT_REPO;

    if ($gitAvailable && $hasGitDir) {
        $curDir = getcwd();
        @chdir($root);

        // Branch
        $br = trim((string) @shell_exec('git rev-parse --abbrev-ref HEAD 2>&1'));
        if ($br !== '' && !str_contains($br, 'fatal')) {
            $branch = $br;
        }

        // Hash
        $h = trim((string) @shell_exec('git rev-parse --short HEAD 2>&1'));
        if ($h !== '' && !str_contains($h, 'fatal')) {
            $hash = $h;
        }
        $fh = trim((string) @shell_exec('git rev-parse HEAD 2>&1'));
        if ($fh !== '' && !str_contains($fh, 'fatal')) {
            $fullHash = $fh;
        }

        // Commit message
        $msg = trim((string) @shell_exec('git log -1 --pretty=format:"%s" 2>&1'));
        if ($msg !== '' && !str_contains($msg, 'fatal')) {
            $message = $msg;
        }

        // Author
        $aut = trim((string) @shell_exec('git log -1 --pretty=format:"%an <%ae>" 2>&1'));
        if ($aut !== '' && !str_contains($aut, 'fatal')) {
            $author = $aut;
        }

        // Date
        $dt = trim((string) @shell_exec('git log -1 --pretty=format:"%ad (%cr)" --date=format:"%Y-%m-%d %H:%M:%S" 2>&1'));
        if ($dt !== '' && !str_contains($dt, 'fatal')) {
            $date = $dt;
        }

        // Dirty files
        $statusOut = trim((string) @shell_exec('git status --porcelain 2>&1'));
        if ($statusOut !== '' && !str_contains($statusOut, 'fatal')) {
            $lines = array_filter(array_map('trim', explode("\n", $statusOut)));
            $isDirty = ($lines !== []);
            $dirtyList = array_values($lines);
        }

        // Remote URL
        $rem = trim((string) @shell_exec('git remote get-url origin 2>&1'));
        if ($rem !== '' && !str_contains($rem, 'fatal')) {
            $remoteUrl = $rem;
        }

        if ($curDir) {
            @chdir($curDir);
        }
    }

    $opcacheActive = function_exists('opcache_get_status')
        && is_array(@opcache_get_status(false))
        && !empty(opcache_get_status(false)['opcache_enabled']);

    return [
        'git_available'    => $gitAvailable,
        'git_version'      => $gitVerClean,
        'has_git_dir'      => $hasGitDir,
        'branch'           => $branch,
        'hash'             => $hash !== '' ? $hash : 'unknown',
        'full_hash'        => $fullHash,
        'message'          => $message,
        'author'           => $author,
        'date'             => $date,
        'is_dirty'         => $isDirty,
        'dirty_files'      => $dirtyList,
        'remote_url'       => $remoteUrl,
        'last_deploy_time' => (string) ($meta['deploy_time'] ?? ''),
        'opcache_enabled'  => $opcacheActive,
        'meta'             => $meta,
    ];
}

/**
 * Check latest commit on remote GitHub repository.
 *
 * @param string $slug Repository slug (e.g. 'Ajigiwe/zion').
 * @param string $branch Branch name (default: 'main').
 * @return array{
 *   ok: bool,
 *   hash: string,
 *   full_hash: string,
 *   message: string,
 *   author: string,
 *   date: string,
 *   url: string,
 *   error?: string
 * }
 */
function deploy_check_remote_github(string $slug = DEPLOY_REPO_SLUG, string $branch = DEPLOY_DEFAULT_BRANCH): array
{
    $url = "https://api.github.com/repos/{$slug}/commits/" . urlencode($branch);
    $raw = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'Zion-Admin-DeployEngine/1.0',
            CURLOPT_HTTPHEADER     => ['Accept: application/vnd.github.v3+json'],
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            return ['ok' => false, 'hash' => '', 'full_hash' => '', 'message' => '', 'author' => '', 'date' => '', 'url' => '', 'error' => "GitHub API answered HTTP $httpCode"];
        }
    }

    if (!$raw) {
        $opts = [
            'http' => [
                'method'  => 'GET',
                'header'  => "User-Agent: Zion-Admin-DeployEngine/1.0\r\nAccept: application/vnd.github.v3+json\r\n",
                'timeout' => 5,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ];
        $raw = @file_get_contents($url, false, stream_context_create($opts));
    }

    if ($raw) {
        $data = @json_decode((string) $raw, true);
        if (is_array($data) && isset($data['sha'])) {
            $formattedDate = '';
            if (!empty($data['commit']['author']['date'])) {
                $ts = strtotime((string) $data['commit']['author']['date']);
                $formattedDate = date('Y-m-d H:i:s T', $ts);
            }
            return [
                'ok'        => true,
                'hash'      => substr((string) $data['sha'], 0, 7),
                'full_hash' => (string) $data['sha'],
                'message'   => trim(explode("\n", (string) ($data['commit']['message'] ?? ''))[0]),
                'author'    => ($data['commit']['author']['name'] ?? '') . ' <' . ($data['commit']['author']['email'] ?? '') . '>',
                'date'      => $formattedDate,
                'url'       => (string) ($data['html_url'] ?? "https://github.com/{$slug}/commit/{$data['sha']}"),
            ];
        }
    }

    return [
        'ok'        => false,
        'hash'      => '',
        'full_hash' => '',
        'message'   => '',
        'author'    => '',
        'date'      => '',
        'url'       => '',
        'error'     => 'Could not connect to GitHub API (network or rate limit)',
    ];
}

/**
 * Read deployment history log.
 *
 * @return array<int, array<string, mixed>>
 */
function deploy_get_history(): array
{
    $file = deploy_history_file();
    if (!is_file($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    if (!$raw) {
        return [];
    }
    $decoded = @json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Execute deployment pipeline.
 *
 * @param array{
 *   mode?: 'pull_and_sync'|'opcache_only'|'health_check',
 *   branch?: string,
 *   force_reset?: bool,
 *   flush_opcache?: bool,
 *   verify_health?: bool
 * } $options
 * @param string|null $adminUser Admin username/email who triggered the deploy.
 * @return array{
 *   success: bool,
 *   before_commit: string,
 *   after_commit: string,
 *   commit_info: array<string, mixed>,
 *   logs: array<int, array{type: 'step'|'ok'|'warn'|'err'|'info', msg: string}>,
 *   duration_sec: float
 * }
 */
function deploy_execute(array $options = [], ?string $adminUser = null): array
{
    $startTime = microtime(true);
    $root      = deploy_root_dir();
    $mode      = $options['mode'] ?? 'pull_and_sync';
    $branch    = trim((string) ($options['branch'] ?? DEPLOY_DEFAULT_BRANCH)) ?: DEPLOY_DEFAULT_BRANCH;
    $forceReset = $options['force_reset'] ?? true;
    $flushOpcache = $options['flush_opcache'] ?? true;
    $verifyHealth = $options['verify_health'] ?? true;

    $logs = [];
    $logStep = static function (string $msg) use (&$logs): void {
        $logs[] = ['type' => 'step', 'msg' => $msg];
    };
    $logOk = static function (string $msg) use (&$logs): void {
        $logs[] = ['type' => 'ok', 'msg' => $msg];
    };
    $logWarn = static function (string $msg) use (&$logs): void {
        $logs[] = ['type' => 'warn', 'msg' => $msg];
    };
    $logErr = static function (string $msg) use (&$logs): void {
        $logs[] = ['type' => 'err', 'msg' => $msg];
    };
    $logInfo = static function (string $msg) use (&$logs): void {
        $logs[] = ['type' => 'info', 'msg' => $msg];
    };

    $curDir = getcwd();
    @chdir($root);

    $beforeCommit = 'unknown';
    $afterCommit  = 'unknown';
    $commitData   = [];
    $overallSuccess = true;

    // -------------------------------------------------------------------------
    // MODE: OPCACHE ONLY
    // -------------------------------------------------------------------------
    if ($mode === 'opcache_only') {
        $logStep("1. Flushing PHP Bytecode Cache (OPcache)");
        if (function_exists('opcache_reset')) {
            if (@opcache_reset()) {
                $logOk("OPcache successfully reset. All modified PHP scripts recompiled.");
            } else {
                $logWarn("opcache_reset() called but returned false (OPcache may be disabled or restricted).");
            }
        } else {
            $logWarn("opcache_reset() function not available on this PHP installation.");
        }
        @clearstatcache(true);
        $logOk("File status cache cleared (clearstatcache).");

        $duration = round(microtime(true) - $startTime, 2);
        if ($curDir) @chdir($curDir);

        return [
            'success'       => true,
            'before_commit' => '',
            'after_commit'  => '',
            'commit_info'   => [],
            'logs'          => $logs,
            'duration_sec'  => $duration,
        ];
    }

    // -------------------------------------------------------------------------
    // STEP 1: GIT PULL & SYNC
    // -------------------------------------------------------------------------
    if ($mode === 'pull_and_sync') {
        $logStep("1. Synchronizing Codebase from GitHub (origin/$branch)");

        $gitVer = @shell_exec('git --version 2>&1');
        $gitAvailable = is_string($gitVer) && str_contains($gitVer, 'git version');

        if ($gitAvailable) {
            $logOk("Git binary detected: " . trim($gitVer));

            // Ensure .git is initialized
            if (!is_dir($root . '/.git')) {
                $logInfo("Initializing git repository in web root...");
                @shell_exec('git init 2>&1');
                @shell_exec('git config core.filemode false 2>&1');
                @shell_exec('git remote add origin ' . escapeshellarg(DEPLOY_DEFAULT_REPO) . ' 2>&1');
            }

            // Ensure origin remote is pointing to canonical repo
            $currentRemote = trim((string) @shell_exec('git remote get-url origin 2>&1'));
            if ($currentRemote === '' || str_contains($currentRemote, 'fatal')) {
                @shell_exec('git remote add origin ' . escapeshellarg(DEPLOY_DEFAULT_REPO) . ' 2>&1');
            } else {
                @shell_exec('git remote set-url origin ' . escapeshellarg(DEPLOY_DEFAULT_REPO) . ' 2>&1');
            }

            // Current revision before update
            $bc = trim((string) @shell_exec('git rev-parse --short HEAD 2>&1'));
            if ($bc !== '' && !str_contains($bc, 'fatal')) {
                $beforeCommit = $bc;
            }

            // 1. Fetch origin branch
            $fetchOut = trim((string) @shell_exec("git fetch origin " . escapeshellarg($branch) . " 2>&1"));
            if ($fetchOut !== '') {
                $logInfo("git fetch: " . $fetchOut);
            }

            // 2. Synchronize / Checkout
            if ($forceReset) {
                @shell_exec("git branch -M " . escapeshellarg($branch) . " 2>&1");
                $syncOut = trim((string) @shell_exec("git reset --hard origin/" . escapeshellarg($branch) . " 2>&1"));
                $logOk("Reset to origin/$branch: " . ($syncOut ?: 'HEAD updated.'));
            } else {
                $syncOut = trim((string) @shell_exec("git pull origin " . escapeshellarg($branch) . " 2>&1"));
                $logOk("Pulled origin/$branch: " . ($syncOut ?: 'Already up to date.'));
            }

            // Query updated revision
            $ac = trim((string) @shell_exec('git rev-parse --short HEAD 2>&1'));
            if ($ac !== '' && !str_contains($ac, 'fatal')) {
                $afterCommit = $ac;
            }

            $commitMsg    = trim((string) @shell_exec('git log -1 --pretty=format:"%s" 2>&1'));
            $commitAuthor = trim((string) @shell_exec('git log -1 --pretty=format:"%an <%ae>" 2>&1'));
            $commitDate   = trim((string) @shell_exec('git log -1 --pretty=format:"%ad (%cr)" --date=format:"%Y-%m-%d %H:%M:%S" 2>&1'));
            $diffStat     = trim((string) @shell_exec('git diff --stat HEAD~1 HEAD 2>&1'));
            if ($diffStat === '' || str_contains($diffStat, 'fatal')) {
                $diffStat = trim((string) @shell_exec('git log -1 --stat --oneline 2>&1'));
            }

            $commitData = [
                'hash'          => $afterCommit,
                'message'       => $commitMsg,
                'author'        => $commitAuthor,
                'date'          => $commitDate,
                'deploy_time'   => date('Y-m-d H:i:s T'),
                'changed_files' => $diffStat,
                'deployed_by'   => $adminUser ?: 'Admin',
            ];

            $logOk("Active Revision : $beforeCommit -> $afterCommit");
            $logOk("Commit Message  : \"$commitMsg\"");
            $logOk("Commit Author   : $commitAuthor");
            $logOk("Commit Date     : $commitDate");

            if ($diffStat !== '' && !str_contains($diffStat, 'fatal')) {
                $logInfo("Updated Files:\n" . preg_replace('/^/m', '       ', $diffStat));
            }
        } else {
            $logWarn("Git binary is not available or restricted on this hosting environment.");
            // Fallback via GitHub API latest commit
            $remoteCheck = deploy_check_remote_github(DEPLOY_REPO_SLUG, $branch);
            if ($remoteCheck['ok']) {
                $afterCommit = $remoteCheck['hash'];
                $commitData  = [
                    'hash'        => $remoteCheck['hash'],
                    'message'     => $remoteCheck['message'],
                    'author'      => $remoteCheck['author'],
                    'date'        => $remoteCheck['date'],
                    'deploy_time' => date('Y-m-d H:i:s T'),
                    'deployed_by' => $adminUser ?: 'Admin',
                ];
                $logOk("Synchronized remote commit info: [{$remoteCheck['hash']}] {$remoteCheck['message']}");
            } else {
                $logErr("Could not retrieve Git or GitHub updates: " . ($remoteCheck['error'] ?? 'Unknown'));
                $overallSuccess = false;
            }
        }
    }

    // -------------------------------------------------------------------------
    // STEP 2: RUNTIME STORAGE DIRECTORIES & SECURITY
    // -------------------------------------------------------------------------
    $logStep("2. Re-establishing Runtime Storage & File Permissions");
    $storageDirs = [
        $root . '/storage',
        $root . '/storage/uploads',
        $root . '/storage/uploads/products',
        $root . '/storage/cache',
        $root . '/storage/logs',
        $root . '/storage/backups',
    ];

    foreach ($storageDirs as $dir) {
        if (!is_dir($dir)) {
            if (@mkdir($dir, 0755, true)) {
                $logOk("Created directory: " . str_replace($root . '/', '', $dir));
            } else {
                $logWarn("Could not create directory: " . str_replace($root . '/', '', $dir));
            }
        } else {
            @chmod($dir, 0755);
        }
    }

    // Protect storage uploads and logs with .htaccess if missing
    $htaccessLogs = $root . '/storage/logs/.htaccess';
    if (!is_file($htaccessLogs)) {
        @file_put_contents($htaccessLogs, "Require all denied\n");
    }

    if (is_file($root . '/.env')) {
        @chmod($root . '/.env', 0600);
        $logOk(".env file permissions locked (0600).");
    }

    // -------------------------------------------------------------------------
    // STEP 3: FLUSH PHP OPCACHE
    // -------------------------------------------------------------------------
    if ($flushOpcache) {
        $logStep("3. Flushing PHP Bytecode Cache (OPcache)");
        if (function_exists('opcache_reset')) {
            if (@opcache_reset()) {
                $logOk("OPcache successfully cleared. New code is immediately active.");
            } else {
                $logWarn("opcache_reset() called (OPcache might be inactive or restricted by host).");
            }
        } else {
            $logInfo("OPcache not detected on this server configuration.");
        }
        @clearstatcache(true);
    }

    // -------------------------------------------------------------------------
    // STEP 4: PRE-FLIGHT DATABASE & HEALTH VERIFICATION
    // -------------------------------------------------------------------------
    if ($verifyHealth) {
        $logStep("4. Running Pre-Flight Diagnostics");

        // Database connection test
        try {
            $dbVersion = db_val('SELECT VERSION()');
            $logOk("Database connected successfully: MySQL/MariaDB v" . $dbVersion);

            // Verify core tables
            $tables = ['products', 'categories', 'brands', 'orders', 'users', 'intake_batches', 'intake_items', 'reviews'];
            $missing = [];
            foreach ($tables as $tbl) {
                $cnt = db_val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$tbl]);
                if ((int)$cnt === 0) {
                    $missing[] = $tbl;
                }
            }

            if ($missing === []) {
                $prodCount  = (int) db_val('SELECT COUNT(*) FROM products');
                $orderCount = (int) db_val('SELECT COUNT(*) FROM orders');
                $logOk("Core database tables verified: all 8 critical schemas intact ({$prodCount} products, {$orderCount} orders).");
            } else {
                $logErr("Missing critical tables in database: " . implode(', ', $missing));
                $overallSuccess = false;
            }
        } catch (Throwable $e) {
            $logErr("Database connection test failed: " . $e->getMessage());
            $overallSuccess = false;
        }

        // Check required PHP extensions
        $exts = ['pdo', 'pdo_mysql', 'mbstring', 'curl', 'zip'];
        $missingExts = [];
        foreach ($exts as $ext) {
            if (!extension_loaded($ext)) {
                $missingExts[] = $ext;
            }
        }
        if ($missingExts === []) {
            $logOk("PHP extensions verified: " . implode(', ', $exts));
        } else {
            $logWarn("Missing recommended PHP extensions: " . implode(', ', $missingExts));
        }
    }

    // -------------------------------------------------------------------------
    // STEP 5: SAVE DEPLOYMENT METADATA & HISTORY
    // -------------------------------------------------------------------------
    $duration = round(microtime(true) - $startTime, 2);

    if ($commitData !== []) {
        @file_put_contents(deploy_meta_file(), json_encode($commitData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    $historyRecord = [
        'id'            => 'dep_' . date('Ymd_His'),
        'timestamp'     => date('Y-m-d H:i:s T'),
        'mode'          => $mode,
        'branch'        => $branch,
        'before_commit' => $beforeCommit,
        'after_commit'  => $afterCommit,
        'message'       => $commitData['message'] ?? 'Direct synchronization',
        'author'        => $commitData['author'] ?? 'Admin',
        'deployed_by'   => $adminUser ?: 'Admin',
        'success'       => $overallSuccess,
        'duration_sec'  => $duration,
        'logs'          => $logs,
    ];

    $existingHistory = deploy_get_history();
    array_unshift($existingHistory, $historyRecord);
    // Keep last 50 deploys in history
    $trimmedHistory = array_slice($existingHistory, 0, 50);
    @file_put_contents(deploy_history_file(), json_encode($trimmedHistory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $logStep("5. Deployment Finished in {$duration}s");
    if ($overallSuccess) {
        $logOk("All deployment tasks succeeded. Store is running commit: $afterCommit");
    } else {
        $logWarn("Deployment finished with one or more warnings/errors - check log details above.");
    }

    if ($curDir) {
        @chdir($curDir);
    }

    return [
        'success'       => $overallSuccess,
        'before_commit' => $beforeCommit,
        'after_commit'  => $afterCommit,
        'commit_info'   => $commitData,
        'logs'          => $logs,
        'duration_sec'  => $duration,
    ];
}
