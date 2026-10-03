<?php
/**
 * Zion Groups of Companies - Sync & Deploy Pipeline (PHP).
 *
 * Runs via CLI or Browser:
 *   CLI:     php sync_deploy.php
 *   CLI:     php sync_deploy.php --public-key=pk_live_... --secret-key=sk_live_...
 *   Browser: Open /sync_deploy.php in your browser
 *
 * Pipeline:
 *   1. Preps Payment Gateway (validates and injects Paystack keys into .env)
 *   2. Pulls latest changes from Git (origin/main)
 *   3. Re-establishes runtime storage folders & secure permissions
 *   4. Runs pre-flight diagnostics (database, tables, extensions, gateway status)
 */

declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
$root  = __DIR__;
$envFile = $root . '/.env';

// Helper to read .env
function read_env_map(string $file): array
{
    if (!is_file($file) || !is_readable($file)) {
        return [];
    }
    $map = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
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
            $map[$k] = $v;
        }
    }
    return $map;
}

// Helper to update/inject key-values into .env safely
function update_env_file(string $file, array $updates): bool
{
    $lines = is_file($file) ? file($file, FILE_IGNORE_NEW_LINES) : [];
    $matched = [];

    $newLines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed !== '' && $trimmed[0] !== '#' && str_contains($trimmed, '=')) {
            [$k] = explode('=', $trimmed, 2);
            $k = trim($k);
            if (array_key_exists($k, $updates)) {
                $newLines[] = $k . '=' . $updates[$k];
                $matched[$k] = true;
                continue;
            }
        }
        $newLines[] = $line;
    }

    foreach ($updates as $k => $v) {
        if (!isset($matched[$k])) {
            $newLines[] = $k . '=' . $v;
        }
    }

    $content = implode("\n", $newLines) . "\n";
    $ok = @file_put_contents($file, $content) !== false;
    if ($ok) {
        @chmod($file, 0600);
    }
    return $ok;
}

// Parse CLI options or Web inputs
$cliOpts = $isCli ? getopt('', ['public-key:', 'secret-key:', 'branch:', 'help']) : [];
if ($isCli && isset($cliOpts['help'])) {
    echo "Usage: php sync_deploy.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --public-key=KEY   Set/update Paystack Public Key in .env (pk_live_... or pk_test_...)\n";
    echo "  --secret-key=KEY   Set/update Paystack Secret Key in .env (sk_live_... or sk_test_...)\n";
    echo "  --branch=BRANCH    Git branch to deploy (default: main)\n";
    echo "  --help             Show this help message\n";
    exit(0);
}

$currentEnv = read_env_map($envFile);

$submittedPub = $isCli ? ($cliOpts['public-key'] ?? null) : ($_POST['paystack_public_key'] ?? null);
$submittedSec = $isCli ? ($cliOpts['secret-key'] ?? null) : ($_POST['paystack_secret_key'] ?? null);
$branch       = $isCli ? ($cliOpts['branch'] ?? 'main') : (trim((string) ($_POST['branch'] ?? 'main')) ?: 'main');

// In browser mode, only execute when POST action=deploy or if run via CLI
$doDeploy = $isCli || (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']));

$logs = [];
function log_step(string $title): void {
    global $logs, $isCli;
    $logs[] = ['type' => 'step', 'msg' => $title];
    if ($isCli) {
        printf("\n\033[1;36m==>\033[0m \033[1m%s\033[0m\n", $title);
    }
}
function log_ok(string $msg): void {
    global $logs, $isCli;
    $logs[] = ['type' => 'ok', 'msg' => $msg];
    if ($isCli) {
        printf("    \033[0;32m✓\033[0m %s\n", $msg);
    }
}
function log_warn(string $msg): void {
    global $logs, $isCli;
    $logs[] = ['type' => 'warn', 'msg' => $msg];
    if ($isCli) {
        printf("    \033[0;33m!\033[0m %s\n", $msg);
    }
}
function log_err(string $msg): void {
    global $logs, $isCli;
    $logs[] = ['type' => 'err', 'msg' => $msg];
    if ($isCli) {
        printf("    \033[1;31m✗\033[0m %s\n", $msg);
    }
}

if ($doDeploy) {
    // -------------------------------------------------------------------------
    // 1. PREP PAYMENT GATEWAY (PAYSTACK KEYS)
    // -------------------------------------------------------------------------
    log_step("1. Preparing Payment Gateway (Paystack Keys)");

    if (!is_file($envFile) && is_file($root . '/.env.example')) {
        @copy($root . '/.env.example', $envFile);
        @chmod($envFile, 0600);
        log_ok("Created .env from .env.example");
        $currentEnv = read_env_map($envFile);
    }

    $updates = [];
    if ($submittedPub !== null && trim($submittedPub) !== '') {
        $updates['PAYSTACK_PUBLIC_KEY'] = trim($submittedPub);
    }
    if ($submittedSec !== null && trim($submittedSec) !== '') {
        $updates['PAYSTACK_SECRET_KEY'] = trim($submittedSec);
    }

    if ($updates !== []) {
        if (update_env_file($envFile, $updates)) {
            log_ok("Updated Paystack keys in .env");
            $currentEnv = read_env_map($envFile);
        } else {
            log_err("Failed to write updated keys to .env");
        }
    }

    $pubKey = $currentEnv['PAYSTACK_PUBLIC_KEY'] ?? '';
    $secKey = $currentEnv['PAYSTACK_SECRET_KEY'] ?? '';

    if ($pubKey !== '' || $secKey !== '') {
        $isLive = str_starts_with($pubKey, 'pk_live_') || str_starts_with($secKey, 'sk_live_');
        $isTest = str_starts_with($pubKey, 'pk_test_') || str_starts_with($secKey, 'sk_test_');

        if ($isLive) {
            log_ok("Paystack Mode: LIVE (Production keys active: " . substr($pubKey, 0, 8) . "...)");
        } elseif ($isTest) {
            log_ok("Paystack Mode: TEST (Sandbox test keys active: " . substr($pubKey, 0, 8) . "...)");
        } else {
            log_warn("Paystack keys active with custom prefix (" . substr($pubKey, 0, 8) . "...)");
        }
    } else {
        log_warn("Paystack keys are empty in .env - checkout will operate in demo / simulation mode.");
    }

    // -------------------------------------------------------------------------
    // 2. PULL LATEST CHANGES FROM GIT
    // -------------------------------------------------------------------------
    log_step("2. Pulling Latest Changes from Git (origin/$branch)");

    $gitCheck = @shell_exec('git --version 2>&1');
    if (!$gitCheck || !str_contains($gitCheck, 'git version')) {
        log_warn("Git binary not accessible via PHP shell_exec. Skipping git pull.");
    } else {
        $beforeCommit = trim((string) @shell_exec('git rev-parse --short HEAD 2>&1'));
        $fetchOut = @shell_exec("git fetch origin " . escapeshellarg($branch) . " 2>&1");
        $checkoutOut = @shell_exec("git checkout -B " . escapeshellarg($branch) . " origin/" . escapeshellarg($branch) . " 2>&1");
        $afterCommit = trim((string) @shell_exec('git rev-parse --short HEAD 2>&1'));
        $commitMsg = trim((string) @shell_exec('git log -1 --pretty=%s 2>&1'));

        if ($afterCommit !== '' && !str_contains($afterCommit, 'fatal')) {
            log_ok("Git updated: $beforeCommit -> $afterCommit ($commitMsg)");
        } else {
            log_warn("Git fetch/checkout note: " . trim($checkoutOut ?: $fetchOut ?: 'No output'));
        }
    }

    // -------------------------------------------------------------------------
    // 3. RUNTIME STORAGE & PERMISSIONS
    // -------------------------------------------------------------------------
    log_step("3. Verifying Runtime Storage Directories & Permissions");

    foreach (['storage', 'storage/logs', 'storage/uploads'] as $dir) {
        $fullPath = $root . '/' . $dir;
        if (!is_dir($fullPath)) {
            @mkdir($fullPath, 0755, true);
        }
        @chmod($fullPath, 0755);
    }
    log_ok("Storage directories initialized with 0755 permissions.");

    if (is_file($envFile)) {
        @chmod($envFile, 0600);
        log_ok(".env file secured with 0600 permissions.");
    }

    // -------------------------------------------------------------------------
    // 4. PRE-FLIGHT DIAGNOSTICS
    // -------------------------------------------------------------------------
    log_step("4. Running Pre-Flight Diagnostics (tools/doctor.php)");

    if (is_file($root . '/tools/doctor.php')) {
        ob_start();
        $doctorOut = @shell_exec('php ' . escapeshellarg($root . '/tools/doctor.php') . ' 2>&1');
        if ($doctorOut !== null && $doctorOut !== '') {
            $doctorLines = explode("\n", trim($doctorOut));
            foreach ($doctorLines as $dl) {
                $dl = trim($dl);
                if ($dl === '') continue;
                if (str_starts_with($dl, '[PASS]')) {
                    log_ok(substr($dl, 6));
                } elseif (str_starts_with($dl, '[WARN]')) {
                    log_warn(substr($dl, 6));
                } elseif (str_starts_with($dl, '[FAIL]')) {
                    log_err(substr($dl, 6));
                } else {
                    $logs[] = ['type' => 'info', 'msg' => $dl];
                }
            }
        } else {
            log_ok("Pre-flight check script located at tools/doctor.php.");
        }
    }

    log_step("Deployment Pipeline Complete!");
}

if ($isCli) {
    exit(0);
}

// -----------------------------------------------------------------------------
// WEB INTERFACE (HTML)
// -----------------------------------------------------------------------------
$activePub = $currentEnv['PAYSTACK_PUBLIC_KEY'] ?? '';
$activeSec = $currentEnv['PAYSTACK_SECRET_KEY'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sync &amp; Deploy | Zion Groups</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet"/>
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    pre, code { font-family: 'JetBrains Mono', monospace; }
  </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-4 sm:p-8">
  <div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <header class="flex items-center justify-between border-b border-slate-800 pb-6">
      <div>
        <div class="flex items-center gap-3">
          <div class="h-3 w-3 rounded-full bg-emerald-500 animate-pulse"></div>
          <span class="text-xs uppercase tracking-widest text-emerald-400 font-semibold">Zion Live Deployment</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold mt-1 text-white">Sync &amp; Deploy Pipeline</h1>
        <p class="text-sm text-slate-400 mt-1">Pull latest Git commits, configure Paystack keys, and deploy to live.</p>
      </div>
      <a href="index.php" class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-300 border border-slate-700 rounded-lg hover:bg-slate-900 transition">
        Storefront &rarr;
      </a>
    </header>

    <!-- Configuration & Deploy Form -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
      <form method="post" class="space-y-5">
        <input type="hidden" name="action" value="deploy"/>

        <div class="border-b border-slate-800 pb-4">
          <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <span>💳</span> Payment Gateway (Paystack Configuration)
          </h2>
          <p class="text-xs text-slate-400 mt-0.5">
            Configure or update Paystack API keys. Leave blank to retain current settings.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="pub-key">
              Paystack Public Key
            </label>
            <input id="pub-key" name="paystack_public_key" type="text"
                   placeholder="<?= htmlspecialchars($activePub !== '' ? substr($activePub, 0, 12) . '...' : 'pk_live_... or pk_test_...') ?>"
                   value="<?= htmlspecialchars($activePub) ?>"
                   class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-700 rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500 font-mono transition"/>
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="sec-key">
              Paystack Secret Key
            </label>
            <input id="sec-key" name="paystack_secret_key" type="password"
                   placeholder="<?= htmlspecialchars($activeSec !== '' ? '••••••••••••••••' : 'sk_live_... or sk_test_...') ?>"
                   value="<?= htmlspecialchars($activeSec) ?>"
                   class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-700 rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500 font-mono transition"/>
          </div>
        </div>

        <div class="flex items-center gap-4 pt-2">
          <div class="w-48">
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5" for="branch">
              Git Branch
            </label>
            <input id="branch" name="branch" type="text" value="<?= htmlspecialchars($branch) ?>"
                   class="w-full px-3.5 py-2 bg-slate-950 border border-slate-700 rounded-xl text-sm text-slate-200 font-mono focus:outline-none focus:border-emerald-500"/>
          </div>

          <div class="flex-1 flex justify-end pt-5">
            <button type="submit"
                    class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-900/30 transition-all flex items-center gap-2 cursor-pointer">
              <span>🚀</span> Run Sync &amp; Deploy Now
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Execution Terminal / Output -->
    <?php if ($doDeploy): ?>
      <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl">
        <div class="bg-slate-950 px-4 py-3 border-b border-slate-800 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="h-3 w-3 rounded-full bg-red-500/80"></span>
            <span class="h-3 w-3 rounded-full bg-yellow-500/80"></span>
            <span class="h-3 w-3 rounded-full bg-green-500/80"></span>
            <span class="text-xs font-mono text-slate-400 ml-2">deployment-log.sh</span>
          </div>
          <span class="text-xs text-emerald-400 font-mono">Status: Done</span>
        </div>

        <div class="p-6 font-mono text-sm space-y-2 bg-slate-950/70 overflow-x-auto">
          <?php foreach ($logs as $log): ?>
            <?php if ($log['type'] === 'step'): ?>
              <div class="text-cyan-400 font-bold mt-4 first:mt-0 text-base border-b border-slate-800/80 pb-1">
                ==&gt; <?= htmlspecialchars($log['msg']) ?>
              </div>
            <?php elseif ($log['type'] === 'ok'): ?>
              <div class="text-emerald-400 flex items-start gap-2">
                <span class="text-emerald-500 shrink-0 font-bold">✓</span>
                <span><?= htmlspecialchars($log['msg']) ?></span>
              </div>
            <?php elseif ($log['type'] === 'warn'): ?>
              <div class="text-amber-400 flex items-start gap-2">
                <span class="text-amber-500 shrink-0 font-bold">!</span>
                <span><?= htmlspecialchars($log['msg']) ?></span>
              </div>
            <?php elseif ($log['type'] === 'err'): ?>
              <div class="text-rose-400 flex items-start gap-2">
                <span class="text-rose-500 shrink-0 font-bold">✗</span>
                <span><?= htmlspecialchars($log['msg']) ?></span>
              </div>
            <?php else: ?>
              <div class="text-slate-400 pl-4"><?= htmlspecialchars($log['msg']) ?></div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
