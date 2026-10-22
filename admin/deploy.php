<?php
/**
 * Zion Groups of Companies - Admin Deploy Engine.
 *
 * Provides a native back-office interface to check GitHub for updates,
 * deploy latest commits directly from git (origin/main), repair runtime
 * directories, flush PHP OPcache, and run pre-flight diagnostics.
 */

declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/deploy_service.php';

require_admin();
$admin = current_user();

$lastExecution = null;

// ------------------------------------------------------------- POST Handlers
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $adminName = ($admin['name'] ?? '') . ' (' . ($admin['email'] ?? 'admin') . ')';

    if ($action === 'deploy_code') {
        $branch       = trim((string) ($_POST['branch'] ?? 'main')) ?: 'main';
        $forceReset   = !empty($_POST['force_reset']);
        $flushOpcache = !empty($_POST['flush_opcache']);
        $verifyHealth = !empty($_POST['verify_health']);

        $res = deploy_execute([
            'mode'          => 'pull_and_sync',
            'branch'        => $branch,
            'force_reset'   => $forceReset,
            'flush_opcache' => $flushOpcache,
            'verify_health' => $verifyHealth,
        ], $adminName);

        if ($res['success']) {
            flash_set('success', sprintf('Deployment successful! Active commit is now [%s]. Duration: %.2fs.', $res['after_commit'], $res['duration_sec']));
        } else {
            flash_set('error', 'Deployment completed with one or more warnings/errors. Review the execution logs below.');
        }

        $_SESSION['deploy_last_execution'] = $res;
        header('Location: ' . url('admin/deploy.php'));
        exit;
    }

    if ($action === 'opcache_flush') {
        $res = deploy_execute([
            'mode' => 'opcache_only',
        ], $adminName);

        flash_set('success', 'PHP OPcache successfully reset and file stat cache cleared.');
        $_SESSION['deploy_last_execution'] = $res;
        header('Location: ' . url('admin/deploy.php'));
        exit;
    }

    if ($action === 'health_check') {
        $res = deploy_execute([
            'mode'          => 'pull_and_sync',
            'force_reset'   => false,
            'flush_opcache' => true,
            'verify_health' => true,
        ], $adminName);

        flash_set('info', 'Pre-flight diagnostics completed. Review status below.');
        $_SESSION['deploy_last_execution'] = $res;
        header('Location: ' . url('admin/deploy.php'));
        exit;
    }

    flash_set('error', 'Unknown deploy action.');
    header('Location: ' . url('admin/deploy.php'));
    exit;
}

// ------------------------------------------------------------- Data Preparation
$lastExecution = $_SESSION['deploy_last_execution'] ?? null;
unset($_SESSION['deploy_last_execution']);

$local  = deploy_get_local_status();
$remote = deploy_check_remote_github(DEPLOY_REPO_SLUG, $local['branch']);
$history = deploy_get_history();

$isUpToDate = false;
if ($remote['ok'] && $local['hash'] !== 'unknown') {
    $isUpToDate = ($remote['hash'] === $local['hash'])
        || ($remote['full_hash'] !== '' && $remote['full_hash'] === $local['full_hash']);
}

admin_head('Deploy Engine', 'deploy');
?>

<div class="max-w-7xl mx-auto flex flex-col gap-6">

  <!-- Header & Breadcrumbs -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-outline-variant/60">
    <div>
      <div class="flex items-center gap-2 font-label-tag text-label-tag uppercase tracking-widest text-on-surface-variant mb-1">
        <a href="<?= e(url('admin/index.php')) ?>" class="hover:text-primary transition-colors">Admin</a>
        <span>/</span>
        <span class="text-primary font-bold">System Reliability</span>
      </div>
      <div class="flex items-center gap-3 flex-wrap">
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-black tracking-tight">
          Deploy Engine
        </h1>
        <?php if ($remote['ok']): ?>
          <?php if ($isUpToDate): ?>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-secondary-fixed text-on-secondary-fixed inline-flex items-center gap-1">
              <span class="material-symbols-outlined text-sm">check_circle</span>
              Up to Date (<?= e($local['hash']) ?>)
            </span>
          <?php else: ?>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-amber-500/20 text-amber-900 border border-amber-500/40 inline-flex items-center gap-1 animate-pulse">
              <span class="material-symbols-outlined text-sm">upgrade</span>
              Update Available (<?= e($remote['hash']) ?>)
            </span>
          <?php endif; ?>
        <?php else: ?>
          <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-surface-container text-on-surface-variant inline-flex items-center gap-1">
            <span class="material-symbols-outlined text-sm">commit</span>
            Current: <?= e($local['hash']) ?>
          </span>
        <?php endif; ?>
      </div>
      <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
        One-click deployment, Git synchronization from GitHub, runtime directory repair, and zero-downtime OPcache flushing.
      </p>
    </div>

    <!-- Quick Action: Instant Deploy Button -->
    <div class="flex items-center gap-2">
      <form method="POST" action="<?= e(url('admin/deploy.php')) ?>" onsubmit="return confirm('Deploy latest changes from GitHub (origin/<?= e($local['branch']) ?>) to this live server?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="deploy_code">
        <input type="hidden" name="branch" value="<?= e($local['branch']) ?>">
        <input type="hidden" name="force_reset" value="1">
        <input type="hidden" name="flush_opcache" value="1">
        <input type="hidden" name="verify_health" value="1">
        <button type="submit" class="px-4 py-2.5 bg-primary text-on-primary hover:bg-primary/90 font-label-nav text-label-nav uppercase tracking-wider rounded-lg shadow-sm flex items-center gap-2 transition-all font-bold">
          <span class="material-symbols-outlined text-lg">rocket_launch</span>
          Deploy Latest Code
        </button>
      </form>
    </div>
  </div>

  <!-- Metric Summary Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Local Active Revision -->
    <div class="p-4 rounded-xl border border-outline-variant/70 bg-surface flex flex-col justify-between shadow-xs">
      <div class="flex items-start justify-between gap-2">
        <span class="font-label-tag text-label-tag uppercase tracking-wider text-on-surface-variant">Active Local Commit</span>
        <span class="w-8 h-8 rounded-lg bg-primary-container/40 text-primary flex items-center justify-center shrink-0">
          <span class="material-symbols-outlined text-lg">commit</span>
        </span>
      </div>
      <div class="mt-3">
        <div class="flex items-center gap-2">
          <span class="font-mono text-lg font-black text-on-surface bg-surface-container-high px-2 py-0.5 rounded">
            <?= e($local['hash']) ?>
          </span>
          <span class="text-xs text-on-surface-variant font-medium">(<?= e($local['branch']) ?>)</span>
        </div>
        <div class="text-[11px] text-on-surface-variant mt-1.5 truncate" title="<?= e($local['message']) ?>">
          <?= e($local['message'] ?: 'No commit message recorded') ?>
        </div>
        <div class="text-[10px] text-outline mt-0.5">
          <?= e($local['date'] ?: 'Local tracking') ?>
        </div>
      </div>
    </div>

    <!-- GitHub Remote Status -->
    <div class="p-4 rounded-xl border border-outline-variant/70 bg-surface flex flex-col justify-between shadow-xs">
      <div class="flex items-start justify-between gap-2">
        <span class="font-label-tag text-label-tag uppercase tracking-wider text-on-surface-variant">GitHub Remote</span>
        <span class="w-8 h-8 rounded-lg bg-secondary-container/50 text-secondary flex items-center justify-center shrink-0">
          <span class="material-symbols-outlined text-lg">cloud</span>
        </span>
      </div>
      <div class="mt-3">
        <?php if ($remote['ok']): ?>
          <div class="flex items-center gap-2">
            <span class="font-mono text-lg font-black text-on-surface bg-surface-container-high px-2 py-0.5 rounded">
              <?= e($remote['hash']) ?>
            </span>
            <?php if ($isUpToDate): ?>
              <span class="text-[11px] text-[#2e7d32] font-bold">Synchronized</span>
            <?php else: ?>
              <span class="text-[11px] text-amber-700 font-bold">Pending Pull</span>
            <?php endif; ?>
          </div>
          <div class="text-[11px] text-on-surface-variant mt-1.5 truncate" title="<?= e($remote['message']) ?>">
            <?= e($remote['message']) ?>
          </div>
          <div class="text-[10px] text-outline mt-0.5">
            <?= e($remote['date']) ?>
          </div>
        <?php else: ?>
          <div class="text-xs font-bold text-error">Remote unreachable</div>
          <div class="text-[10px] text-outline mt-1 truncate" title="<?= e($remote['error'] ?? '') ?>">
            <?= e($remote['error'] ?? 'API timeout or limit') ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Server & Runtime -->
    <div class="p-4 rounded-xl border border-outline-variant/70 bg-surface flex flex-col justify-between shadow-xs">
      <div class="flex items-start justify-between gap-2">
        <span class="font-label-tag text-label-tag uppercase tracking-wider text-on-surface-variant">Runtime &amp; OPcache</span>
        <span class="w-8 h-8 rounded-lg bg-surface-container-high text-on-surface flex items-center justify-center shrink-0">
          <span class="material-symbols-outlined text-lg">terminal</span>
        </span>
      </div>
      <div class="mt-3">
        <div class="flex items-center gap-2">
          <span class="font-body-sm text-body-sm font-bold text-on-surface">PHP <?= PHP_VERSION ?></span>
          <span class="px-1.5 py-0.2 rounded text-[10px] font-bold uppercase <?= $local['opcache_enabled'] ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-surface-container text-outline' ?>">
            <?= $local['opcache_enabled'] ? 'OPcache ON' : 'OPcache OFF' ?>
          </span>
        </div>
        <div class="text-[11px] text-on-surface-variant mt-1.5 truncate">
          Git: <?= e($local['git_version']) ?>
        </div>
        <div class="text-[10px] text-outline mt-0.5">
          SAPI: <?= PHP_SAPI ?>
        </div>
      </div>
    </div>

    <!-- Last Deployment Run -->
    <div class="p-4 rounded-xl border border-outline-variant/70 bg-surface flex flex-col justify-between shadow-xs">
      <div class="flex items-start justify-between gap-2">
        <span class="font-label-tag text-label-tag uppercase tracking-wider text-on-surface-variant">Last Deployed</span>
        <span class="w-8 h-8 rounded-lg bg-surface-container-high text-on-surface flex items-center justify-center shrink-0">
          <span class="material-symbols-outlined text-lg">history</span>
        </span>
      </div>
      <div class="mt-3">
        <?php
        $lastRecord = $history[0] ?? null;
        ?>
        <?php if ($lastRecord): ?>
          <div class="flex items-center gap-2">
            <span class="font-body-sm text-body-sm font-bold text-on-surface">
              <?= e($lastRecord['after_commit'] ?: $lastRecord['before_commit'] ?: 'Deployed') ?>
            </span>
            <span class="px-1.5 py-0.2 rounded text-[10px] font-bold uppercase <?= !empty($lastRecord['success']) ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-error-container text-on-error-container' ?>">
              <?= !empty($lastRecord['success']) ? 'Success' : 'Warnings' ?>
            </span>
          </div>
          <div class="text-[11px] text-on-surface-variant mt-1.5 truncate">
            By <?= e((string) ($lastRecord['deployed_by'] ?? 'Admin')) ?>
          </div>
          <div class="text-[10px] text-outline mt-0.5">
            <?= e((string) ($lastRecord['timestamp'] ?? '')) ?> (<?= e((string) ($lastRecord['duration_sec'] ?? '0')) ?>s)
          </div>
        <?php else: ?>
          <div class="text-xs text-on-surface-variant">No web deployments recorded yet</div>
          <div class="text-[10px] text-outline mt-1">Ready for initial deploy</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($local['is_dirty']): ?>
    <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-start gap-3">
      <span class="material-symbols-outlined text-amber-600 text-xl mt-0.5">warning</span>
      <div class="text-xs text-on-surface space-y-1">
        <span class="font-bold text-sm block text-amber-800">Local Modified Files Detected (<?= count($local['dirty_files']) ?>)</span>
        <p class="text-on-surface-variant leading-relaxed">
          The working directory contains uncommitted changes. Using <strong>Force Reset (Recommended)</strong> will synchronize these files with origin/<?= e($local['branch']) ?> cleanly.
        </p>
        <details class="pt-1">
          <summary class="font-mono text-[11px] text-primary cursor-pointer select-none">View modified files</summary>
          <div class="mt-2 p-2 bg-surface-container rounded font-mono text-[11px] space-y-0.5 max-h-36 overflow-y-auto">
            <?php foreach ($local['dirty_files'] as $df): ?>
              <div><?= e($df) ?></div>
            <?php endforeach; ?>
          </div>
        </details>
      </div>
    </div>
  <?php endif; ?>

  <!-- Main Deployment Control Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Primary Deployment Console (2 Cols) -->
    <div class="lg:col-span-2 space-y-6">
      <section class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-6">
        <div>
          <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Pipeline Action</span>
          <h2 class="font-headline-md text-headline-md text-on-surface font-bold">Synchronize Code from GitHub</h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-1 leading-relaxed">
            Fetches all remote commits from <code class="font-mono text-xs bg-surface-container px-1 py-0.5 rounded"><?= e($local['remote_url']) ?></code>, applies them to your server, clears the PHP OPcache, and verifies database integrity.
          </p>
        </div>

        <form method="POST" action="<?= e(url('admin/deploy.php')) ?>" class="space-y-5" onsubmit="return confirm('Execute deploy pipeline now?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="deploy_code">

          <!-- Branch & Strategy Settings -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant font-bold mb-1.5">Target Git Branch</label>
              <input type="text" name="branch" value="<?= e($local['branch']) ?>" required
                     class="w-full h-11 px-3 border border-outline-variant rounded-lg font-mono text-sm font-bold bg-surface-container-lowest focus:border-primary outline-none" />
              <span class="text-[11px] text-outline mt-1 block">Usually <code class="font-mono">main</code></span>
            </div>

            <div>
              <label class="block font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant font-bold mb-1.5">Remote Repository</label>
              <input type="text" value="<?= e($local['remote_url']) ?>" readonly
                     class="w-full h-11 px-3 border border-outline-variant rounded-lg font-mono text-xs text-on-surface-variant bg-surface-container select-all outline-none" />
              <span class="text-[11px] text-outline mt-1 block">Canonical repository for this deployment</span>
            </div>
          </div>

          <!-- Deployment Options Toggles -->
          <div class="p-4 bg-surface-container/40 rounded-xl border border-outline-variant/60 space-y-3">
            <span class="font-label-nav text-[11px] uppercase tracking-wider text-on-surface font-bold block">Execution Options</span>
            
            <label class="flex items-start gap-3 cursor-pointer select-none">
              <input type="checkbox" name="force_reset" value="1" checked class="mt-0.5 w-4 h-4 text-primary rounded border-outline-variant focus:ring-primary" />
              <div>
                <span class="font-body-sm text-body-sm font-bold text-on-surface block">Force Reset to Remote HEAD (Recommended)</span>
                <span class="font-body-xs text-xs text-on-surface-variant block">Runs <code class="font-mono text-[11px]">git reset --hard origin/<?= e($local['branch']) ?></code> to guarantee a 100% clean sync without git merge conflicts.</span>
              </div>
            </label>

            <label class="flex items-start gap-3 cursor-pointer select-none">
              <input type="checkbox" name="flush_opcache" value="1" checked class="mt-0.5 w-4 h-4 text-primary rounded border-outline-variant focus:ring-primary" />
              <div>
                <span class="font-body-sm text-body-sm font-bold text-on-surface block">Flush PHP OPcache &amp; Stat Cache</span>
                <span class="font-body-xs text-xs text-on-surface-variant block">Clears in-memory bytecode caches so updated files take effect instantly without restarting Apache.</span>
              </div>
            </label>

            <label class="flex items-start gap-3 cursor-pointer select-none">
              <input type="checkbox" name="verify_health" value="1" checked class="mt-0.5 w-4 h-4 text-primary rounded border-outline-variant focus:ring-primary" />
              <div>
                <span class="font-body-sm text-body-sm font-bold text-on-surface block">Run Pre-Flight Database &amp; Schemas Check</span>
                <span class="font-body-xs text-xs text-on-surface-variant block">Verifies database connectivity and guarantees all core schemas and storage paths exist.</span>
              </div>
            </label>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
            <button type="submit" class="px-6 py-3.5 bg-primary-container text-on-primary hover:bg-primary font-label-nav text-label-nav font-bold uppercase tracking-wider rounded-xl shadow-xs inline-flex items-center gap-2 transition-colors">
              <span class="material-symbols-outlined text-lg">rocket_launch</span>
              Deploy Latest from GitHub (Pull &amp; Sync)
            </button>
            <span class="font-body-xs text-xs text-on-surface-variant">
              Deployed by <strong><?= e($admin['name'] ?? 'Admin') ?></strong>
            </span>
          </div>
        </form>
      </section>

      <!-- Live / Last Execution Log Terminal -->
      <?php if ($lastExecution): ?>
        <section class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-4">
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-xl">terminal</span>
              <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">Execution Output</h3>
            </div>
            <span class="px-2.5 py-0.5 rounded text-xs font-mono font-bold uppercase <?= $lastExecution['success'] ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-error-container text-on-error-container' ?>">
              <?= $lastExecution['success'] ? 'Success' : 'Warnings / Failed' ?> (<?= e((string)$lastExecution['duration_sec']) ?>s)
            </span>
          </div>

          <div class="p-4 rounded-xl bg-[#0a0a0f] text-emerald-400 font-mono text-xs overflow-x-auto max-h-96 space-y-1.5 border border-white/10 shadow-inner">
            <?php foreach ($lastExecution['logs'] as $entry): ?>
              <?php
              $t = $entry['type'];
              $m = $entry['msg'];
              ?>
              <?php if ($t === 'step'): ?>
                <div class="text-sky-300 font-bold mt-2 pt-1 border-t border-white/5">==&gt; <?= e($m) ?></div>
              <?php elseif ($t === 'ok'): ?>
                <div class="text-emerald-400 pl-4">&#x2713; <?= nl2br(e($m)) ?></div>
              <?php elseif ($t === 'warn'): ?>
                <div class="text-amber-400 pl-4">! <?= nl2br(e($m)) ?></div>
              <?php elseif ($t === 'err'): ?>
                <div class="text-rose-400 font-bold pl-4">&#x2717; <?= nl2br(e($m)) ?></div>
              <?php else: ?>
                <div class="text-slate-400 pl-4"><?= nl2br(e($m)) ?></div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
    </div>

    <!-- Quick Utilities & Side Diagnostics (1 Col) -->
    <div class="space-y-6">

      <!-- Fast Action Cards -->
      <section class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-4">
        <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold flex items-center gap-2">
          <span class="material-symbols-outlined text-primary">bolt</span>
          Quick Maintenance
        </h3>
        <p class="font-body-sm text-body-sm text-on-surface-variant">
          Perform standalone maintenance routines without pulling code.
        </p>

        <div class="space-y-3 pt-1">
          <!-- Flush OPcache -->
          <form method="POST" action="<?= e(url('admin/deploy.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="opcache_flush">
            <button type="submit" class="w-full py-2.5 px-4 rounded-xl border border-outline-variant hover:border-primary hover:bg-surface-container font-label-nav text-label-nav uppercase tracking-wider text-on-surface font-bold flex items-center justify-between transition-colors">
              <span class="flex items-center gap-2">
                <span class="material-symbols-outlined text-base">cached</span>
                Flush OPcache
              </span>
              <span class="text-[11px] text-outline font-mono">Reset Bytecode</span>
            </button>
          </form>

          <!-- Pre-flight Diagnostics -->
          <form method="POST" action="<?= e(url('admin/deploy.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="health_check">
            <button type="submit" class="w-full py-2.5 px-4 rounded-xl border border-outline-variant hover:border-primary hover:bg-surface-container font-label-nav text-label-nav uppercase tracking-wider text-on-surface font-bold flex items-center justify-between transition-colors">
              <span class="flex items-center gap-2">
                <span class="material-symbols-outlined text-base">fact_check</span>
                Run Diagnostics
              </span>
              <span class="text-[11px] text-outline font-mono">Verify Health</span>
            </button>
          </form>
        </div>
      </section>

      <!-- Repository Information Card -->
      <section class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-3">
        <h3 class="font-label-nav text-label-nav uppercase tracking-wider text-secondary font-bold">Repository Link</h3>
        <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
          This installation is linked to the GitHub repository:
        </p>
        <div class="p-3 bg-surface-container rounded-lg font-mono text-xs break-all text-on-surface font-bold">
          <?= e($local['remote_url']) ?>
        </div>
        <div class="pt-2">
          <a href="https://github.com/<?= e(DEPLOY_REPO_SLUG) ?>" target="_blank" rel="noopener noreferrer"
             class="text-xs text-primary font-bold hover:underline inline-flex items-center gap-1">
            <span>View on GitHub</span>
            <span class="material-symbols-outlined text-sm">open_in_new</span>
          </a>
        </div>
      </section>

      <!-- Deployment Guidelines Card -->
      <section class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-3">
        <h3 class="font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant font-bold">How Deploy Engine Works</h3>
        <ul class="font-body-sm text-body-sm text-on-surface-variant space-y-2 list-disc list-inside leading-relaxed">
          <li><strong>Zero Disruption:</strong> Pulls code directly into the active root with atomic replacement.</li>
          <li><strong>OPcache Cleared:</strong> Flushes PHP memory so file edits are live immediately.</li>
          <li><strong>Storage Protected:</strong> User uploads, session tokens, and database credentials remain untouched.</li>
          <li><strong>Admin-Only:</strong> Authenticated and protected with CSRF anti-tampering tokens.</li>
        </ul>
      </section>
    </div>
  </div>

  <!-- Deployment History Log Section -->
  <section class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-4 mt-2">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">Deployment History</h3>
        <p class="font-body-sm text-body-sm text-on-surface-variant">Log of previous deployments performed from this console or sync scripts.</p>
      </div>
      <span class="text-xs font-mono text-outline"><?= count($history) ?> records logged</span>
    </div>

    <?php if ($history === []): ?>
      <div class="p-8 rounded-xl border border-dashed border-outline-variant text-center font-body-sm text-body-sm text-on-surface-variant">
        No past deployment history recorded yet. Use the <strong>Deploy Latest Code</strong> button above to run your first synchronized deployment.
      </div>
    <?php else: ?>
      <div class="overflow-x-auto rounded-xl border border-outline-variant">
        <table class="w-full border-collapse">
          <thead>
            <tr class="bg-surface-container text-left">
              <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">Status</th>
              <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">Commit Revision</th>
              <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">Commit Message</th>
              <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">Triggered By</th>
              <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">Deploy Time</th>
              <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">Duration</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $h): ?>
              <?php
              $isSuccess = !empty($h['success']);
              $commitHash = (string) ($h['after_commit'] ?: ($h['before_commit'] ?: 'HEAD'));
              ?>
              <tr class="border-t border-outline-variant hover:bg-surface-container/30 transition-colors">
                <td class="p-3">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $isSuccess ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-error-container text-on-error-container' ?>">
                    <?= $isSuccess ? 'Success' : 'Warning' ?>
                  </span>
                </td>
                <td class="p-3 font-mono text-xs font-bold text-on-surface">
                  <?= e($commitHash) ?>
                  <?php if (!empty($h['branch'])): ?>
                    <span class="text-[10px] font-normal text-outline">(<?= e($h['branch']) ?>)</span>
                  <?php endif; ?>
                </td>
                <td class="p-3 font-body-sm text-body-sm text-on-surface max-w-md truncate" title="<?= e((string)($h['message'] ?? '')) ?>">
                  <?= e((string) ($h['message'] ?? 'No message recorded')) ?>
                </td>
                <td class="p-3 font-body-sm text-body-sm text-on-surface-variant">
                  <?= e((string) ($h['deployed_by'] ?? 'Admin')) ?>
                </td>
                <td class="p-3 font-mono text-xs text-on-surface-variant whitespace-nowrap">
                  <?= e((string) ($h['timestamp'] ?? '')) ?>
                </td>
                <td class="p-3 font-mono text-xs text-on-surface-variant">
                  <?= e((string) ($h['duration_sec'] ?? '0')) ?>s
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

</div>
