<?php
/**
 * Zion Groups of Companies - Backup, Restore & Data Wipe Management Center.
 *
 * Provides comprehensive database & media backups, snapshot management,
 * restore with safety rollback snapshots, and granular data wiping.
 */

declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/backup_service.php';

require_admin();
$admin = current_user();

// ------------------------------------------------------------- GET: Direct Download
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $file = (string) ($_GET['file'] ?? '');
    $csrf = (string) ($_GET['csrf'] ?? '');
    if (!hash_equals(csrf_token(), $csrf)) {
        flash_set('error', 'Security session expired. Please retry downloading.');
        header('Location: ' . url('admin/backup.php'));
        exit;
    }
    backup_stream_download($file);
    exit;
}

// ------------------------------------------------------------- POST Action Handlers
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $back = url('admin/backup.php');

    try {
        switch ($action) {
            // ------------------------------------------------- CREATE BACKUP
            case 'create_backup':
                $type = (string) ($_POST['backup_type'] ?? 'full');
                $dest = (string) ($_POST['destination'] ?? 'storage');

                $filename = match ($type) {
                    'database' => 'zion_db_' . date('Ymd_His') . '.sql',
                    'uploads'  => 'zion_uploads_' . date('Ymd_His') . '.zip',
                    default    => 'zion_full_' . date('Ymd_His') . '.zip',
                };
                $targetPath = backup_storage_dir() . '/' . $filename;

                if ($type === 'database') {
                    backup_export_database($targetPath);
                    $label = 'Database snapshot';
                } elseif ($type === 'uploads') {
                    backup_export_uploads($targetPath);
                    $label = 'Media uploads archive';
                } else {
                    backup_export_full($targetPath, $admin['email'] ?? null);
                    $label = 'Full system backup bundle';
                }

                if ($dest === 'download') {
                    backup_stream_download($filename);
                    exit;
                }

                flash_set('success', "{$label} successfully created and stored: {$filename} (" . backup_format_bytes(filesize($targetPath)) . ')');
                header('Location: ' . $back . '#snapshots');
                exit;

            // ------------------------------------------------- RESTORE FROM UPLOADED FILE
            case 'restore_upload':
                $password = (string) ($_POST['admin_password'] ?? '');
                if (!password_verify($password, (string) ($admin['password_hash'] ?? ''))) {
                    flash_set('error', 'Administrator password verification failed. Restore aborted for security.');
                    header('Location: ' . $back . '#restore');
                    exit;
                }

                if (empty($_FILES['backup_file']['tmp_name']) || !is_uploaded_file($_FILES['backup_file']['tmp_name'])) {
                    flash_set('error', 'Please select a valid .sql or .zip backup file to upload.');
                    header('Location: ' . $back . '#restore');
                    exit;
                }

                $uploadedName = $_FILES['backup_file']['name'];
                $tmpPath = $_FILES['backup_file']['tmp_name'];
                $ext = strtolower(pathinfo($uploadedName, PATHINFO_EXTENSION));

                if (!in_array($ext, ['sql', 'zip'], true)) {
                    flash_set('error', 'Only .sql and .zip backup archives are supported.');
                    header('Location: ' . $back . '#restore');
                    exit;
                }

                // Move to storage temporary file
                $tempRestoreFile = backup_storage_dir() . '/restore_temp_' . time() . '.' . $ext;
                if (!move_uploaded_file($tmpPath, $tempRestoreFile)) {
                    flash_set('error', 'Failed to process uploaded backup archive.');
                    header('Location: ' . $back . '#restore');
                    exit;
                }

                $result = backup_restore_from_file($tempRestoreFile, (int) $admin['id']);
                @unlink($tempRestoreFile);

                $msg = 'Restore complete!';
                if ($result['restored_database']) {
                    $msg .= ' Database schema and tables restored.';
                }
                if ($result['restored_media'] > 0) {
                    $msg .= " Restored {$result['restored_media']} media uploads.";
                }
                $msg .= " An automated safety snapshot ({$result['safety_snapshot']}) was saved before execution.";

                flash_set('success', $msg);
                header('Location: ' . $back);
                exit;

            // ------------------------------------------------- RESTORE FROM EXISTING SNAPSHOT
            case 'restore_snapshot':
                $password = (string) ($_POST['admin_password'] ?? '');
                if (!password_verify($password, (string) ($admin['password_hash'] ?? ''))) {
                    flash_set('error', 'Administrator password verification failed. Restore aborted for security.');
                    header('Location: ' . $back . '#snapshots');
                    exit;
                }

                $file = basename((string) ($_POST['filename'] ?? ''));
                $targetPath = backup_storage_dir() . '/' . $file;

                if (!is_file($targetPath)) {
                    flash_set('error', 'Selected snapshot was not found on the server.');
                    header('Location: ' . $back . '#snapshots');
                    exit;
                }

                $result = backup_restore_from_file($targetPath, (int) $admin['id']);
                $msg = "System restored successfully from snapshot '{$file}'!";
                $msg .= " Pre-restore safety snapshot ({$result['safety_snapshot']}) created.";

                flash_set('success', $msg);
                header('Location: ' . $back);
                exit;

            // ------------------------------------------------- DELETE SNAPSHOT
            case 'delete_snapshot':
                $file = basename((string) ($_POST['filename'] ?? ''));
                if (backup_delete_file($file)) {
                    flash_set('success', "Snapshot '{$file}' deleted from server.");
                } else {
                    flash_set('error', "Could not delete '{$file}'.");
                }
                header('Location: ' . $back . '#snapshots');
                exit;

            // ------------------------------------------------- DATA WIPE & RESET
            case 'wipe_data':
                $wipeType = (string) ($_POST['wipe_type'] ?? '');
                $password = (string) ($_POST['admin_password'] ?? '');
                $confirm  = trim((string) ($_POST['confirm_phrase'] ?? ''));

                // Verify password
                if (!password_verify($password, (string) ($admin['password_hash'] ?? ''))) {
                    flash_set('error', 'Administrator password incorrect. Data wipe aborted.');
                    header('Location: ' . $back . '#wipe');
                    exit;
                }

                // Verify typed confirmation phrase
                $expectedPhrases = [
                    'transactions'   => 'CONFIRM-WIPE-TRANSACTIONS',
                    'catalog'        => 'CONFIRM-WIPE-CATALOG',
                    'seed_reset'     => 'CONFIRM-SEED-RESET',
                    'factory_reset'  => 'CONFIRM-FACTORY-RESET',
                ];

                $expected = $expectedPhrases[$wipeType] ?? null;
                if ($expected === null || $confirm !== $expected) {
                    flash_set('error', "Confirmation phrase mismatch. You must type precisely '{$expected}' to proceed.");
                    header('Location: ' . $back . '#wipe');
                    exit;
                }

                $result = backup_data_wipe($wipeType, (int) $admin['id']);
                $msg = $result['message'] . " (Safety rollback snapshot taken: {$result['safety_snapshot']})";

                flash_set('success', $msg);
                header('Location: ' . $back);
                exit;

            default:
                flash_set('error', 'Unrecognized action.');
                header('Location: ' . $back);
                exit;
        }
    } catch (Throwable $e) {
        flash_set('error', 'Error: ' . $e->getMessage());
        header('Location: ' . $back);
        exit;
    }
}

// ------------------------------------------------------------- View Data
$stats = backup_system_stats();
$snapshots = backup_list_files();

admin_head('Backup & Wipe Operations', 'backup');
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
      <h1 class="font-headline-lg text-headline-lg text-on-surface font-black tracking-tight">
        Backup, Restore & Data Wipe
      </h1>
      <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
        Secure snapshot engine, full system packaging, point-in-time recovery, and emergency safety rollbacks.
      </p>
    </div>

    <!-- Quick Action: Instant Full Backup -->
    <div class="flex items-center gap-2">
      <form method="POST" action="<?= e(url('admin/backup.php')) ?>" class="inline-flex">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_backup">
        <input type="hidden" name="backup_type" value="full">
        <input type="hidden" name="destination" value="storage">
        <button type="submit" class="px-4 py-2.5 bg-primary text-on-primary hover:bg-primary/90 font-label-nav text-label-nav uppercase tracking-wider rounded-lg shadow-sm flex items-center gap-2 transition-all">
          <span class="material-symbols-outlined text-lg">cloud_sync</span>
          Take Full Snapshot Now
        </button>
      </form>
    </div>
  </div>

  <!-- Metric Summary Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Database Stats -->
    <div class="p-4 rounded-xl border border-outline-variant/70 bg-surface flex flex-col justify-between shadow-xs">
      <div class="flex items-start justify-between gap-2">
        <span class="font-label-tag text-label-tag uppercase tracking-wider text-on-surface-variant">Database Size</span>
        <span class="w-8 h-8 rounded-lg bg-primary-container/40 text-primary flex items-center justify-center shrink-0">
          <span class="material-symbols-outlined text-lg">database</span>
        </span>
      </div>
      <div class="mt-3">
        <div class="font-headline-md text-headline-md font-bold text-on-surface"><?= e($stats['db_size_formatted']) ?></div>
        <div class="text-[12px] text-on-surface-variant mt-1 flex items-center gap-2">
          <span><?= e((string)$stats['counts']['products']) ?> products</span> • 
          <span><?= e((string)$stats['counts']['orders']) ?> orders</span> • 
          <span><?= e((string)$stats['counts']['users']) ?> accounts</span>
        </div>
      </div>
    </div>

    <!-- Media Storage Stats -->
    <div class="p-4 rounded-xl border border-outline-variant/70 bg-surface flex flex-col justify-between shadow-xs">
      <div class="flex items-start justify-between gap-2">
        <span class="font-label-tag text-label-tag uppercase tracking-wider text-on-surface-variant">Media Storage</span>
        <span class="w-8 h-8 rounded-lg bg-secondary-fixed/40 text-secondary flex items-center justify-center shrink-0">
          <span class="material-symbols-outlined text-lg">photo_library</span>
        </span>
      </div>
      <div class="mt-3">
        <div class="font-headline-md text-headline-md font-bold text-on-surface"><?= e($stats['uploads_size_fmt']) ?></div>
        <div class="text-[12px] text-on-surface-variant mt-1">
          <?= e((string)$stats['uploads_count']) ?> stored catalog images & uploads
        </div>
      </div>
    </div>

    <!-- Snapshots Stored -->
    <div class="p-4 rounded-xl border border-outline-variant/70 bg-surface flex flex-col justify-between shadow-xs">
      <div class="flex items-start justify-between gap-2">
        <span class="font-label-tag text-label-tag uppercase tracking-wider text-on-surface-variant">Saved Snapshots</span>
        <span class="w-8 h-8 rounded-lg bg-surface-container-high text-on-surface-variant flex items-center justify-center shrink-0">
          <span class="material-symbols-outlined text-lg">inventory_2</span>
        </span>
      </div>
      <div class="mt-3">
        <div class="font-headline-md text-headline-md font-bold text-on-surface"><?= count($snapshots) ?> <span class="text-sm font-normal text-on-surface-variant">(<?= e($stats['backups_size_fmt']) ?>)</span></div>
        <div class="text-[12px] text-on-surface-variant mt-1">
          Protected in <code class="px-1 py-0.5 rounded bg-surface-container text-[11px]">storage/backups/</code>
        </div>
      </div>
    </div>

    <!-- System Safety Guard -->
    <div class="p-4 rounded-xl border border-outline-variant/70 bg-surface flex flex-col justify-between shadow-xs">
      <div class="flex items-start justify-between gap-2">
        <span class="font-label-tag text-label-tag uppercase tracking-wider text-on-surface-variant">Safety Rollback</span>
        <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
          <span class="material-symbols-outlined text-lg">verified_user</span>
        </span>
      </div>
      <div class="mt-3">
        <div class="font-label-tag text-label-tag font-bold text-emerald-700 uppercase tracking-wide flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Active & Guarded
        </div>
        <div class="text-[12px] text-on-surface-variant mt-1">
          Auto snapshot prior to every restore & wipe
        </div>
      </div>
    </div>
  </div>

  <!-- Navigation Tabs -->
  <div class="border-b border-outline-variant/70 flex gap-2 overflow-x-auto" id="tabContainer">
    <button type="button" data-tab-btn="create" class="px-4 py-3 border-b-2 border-primary text-primary font-label-nav text-label-nav uppercase tracking-wider font-bold flex items-center gap-2 transition-all">
      <span class="material-symbols-outlined text-base">cloud_download</span> Create Backup
    </button>
    <button type="button" data-tab-btn="restore" class="px-4 py-3 border-b-2 border-transparent text-on-surface-variant hover:text-on-surface font-label-nav text-label-nav uppercase tracking-wider font-semibold flex items-center gap-2 transition-all">
      <span class="material-symbols-outlined text-base">settings_backup_restore</span> Restore System
    </button>
    <button type="button" data-tab-btn="snapshots" class="px-4 py-3 border-b-2 border-transparent text-on-surface-variant hover:text-on-surface font-label-nav text-label-nav uppercase tracking-wider font-semibold flex items-center gap-2 transition-all">
      <span class="material-symbols-outlined text-base">folder_zip</span> Snapshots Registry (<?= count($snapshots) ?>)
    </button>
    <button type="button" data-tab-btn="wipe" class="px-4 py-3 border-b-2 border-transparent text-error/80 hover:text-error font-label-nav text-label-nav uppercase tracking-wider font-semibold flex items-center gap-2 transition-all">
      <span class="material-symbols-outlined text-base">warning</span> Data Wipe & Reset
    </button>
  </div>

  <!-- ========================================================================= -->
  <!-- TAB 1: CREATE BACKUP -->
  <!-- ========================================================================= -->
  <section data-tab-content="create" class="flex flex-col gap-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

      <!-- Full System Backup (Recommended) -->
      <div class="p-6 rounded-2xl border-2 border-primary/40 bg-surface shadow-xs flex flex-col justify-between relative overflow-hidden">
        <div class="absolute top-0 right-0 px-3 py-1 bg-primary text-on-primary font-label-tag text-label-tag uppercase tracking-widest rounded-bl-xl font-bold">
          Recommended
        </div>
        <div>
          <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center mb-4">
            <span class="material-symbols-outlined text-2xl">folder_special</span>
          </div>
          <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Full System Archive</h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-2">
            Complete snapshot bundle including entire database structure & data, all uploaded media images, and system verification manifest.
          </p>
          <ul class="mt-4 space-y-2 text-[13px] text-on-surface-variant">
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-base">check_circle</span>
              All 18 SQL Database Tables
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-base">check_circle</span>
              All Product & Media Uploads
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-base">check_circle</span>
              Sha256 Integrity Verification
            </li>
          </ul>
        </div>

        <form method="POST" action="<?= e(url('admin/backup.php')) ?>" class="mt-6 flex flex-col gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create_backup">
          <input type="hidden" name="backup_type" value="full">
          <div class="flex items-center gap-2">
            <button type="submit" name="destination" value="storage" class="flex-1 py-2.5 px-3 bg-primary text-on-primary hover:bg-primary/90 font-label-nav text-label-nav uppercase tracking-wider rounded-lg transition-colors flex items-center justify-center gap-2 font-bold">
              <span class="material-symbols-outlined text-base">save</span> Save Snapshot
            </button>
            <button type="submit" name="destination" value="download" title="Download directly to your computer" class="py-2.5 px-3 border border-outline-variant hover:bg-surface-container font-label-nav text-label-nav uppercase tracking-wider rounded-lg transition-colors flex items-center justify-center gap-1.5 text-on-surface">
              <span class="material-symbols-outlined text-base">download</span>
            </button>
          </div>
        </form>
      </div>

      <!-- Database Only -->
      <div class="p-6 rounded-2xl border border-outline-variant/70 bg-surface shadow-xs flex flex-col justify-between">
        <div>
          <div class="w-12 h-12 rounded-xl bg-secondary-fixed/40 text-secondary flex items-center justify-center mb-4">
            <span class="material-symbols-outlined text-2xl">database</span>
          </div>
          <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Database Only (SQL)</h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-2">
            Clean standard MySQL dump containing table schemas, rows, foreign keys, orders, settings, and customer accounts.
          </p>
          <ul class="mt-4 space-y-2 text-[13px] text-on-surface-variant">
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-secondary text-base">check_circle</span>
              Raw <code class="px-1 py-0.5 rounded bg-surface-container text-[12px]">.sql</code> dump format
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-secondary text-base">check_circle</span>
              Fast execution & minimal file size
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-secondary text-base">check_circle</span>
              Compatible with phpMyAdmin
            </li>
          </ul>
        </div>

        <form method="POST" action="<?= e(url('admin/backup.php')) ?>" class="mt-6 flex flex-col gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create_backup">
          <input type="hidden" name="backup_type" value="database">
          <div class="flex items-center gap-2">
            <button type="submit" name="destination" value="storage" class="flex-1 py-2.5 px-3 bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-label-nav text-label-nav uppercase tracking-wider rounded-lg transition-colors flex items-center justify-center gap-2 font-bold">
              <span class="material-symbols-outlined text-base">save</span> Save Snapshot
            </button>
            <button type="submit" name="destination" value="download" title="Download directly to your computer" class="py-2.5 px-3 border border-outline-variant hover:bg-surface-container font-label-nav text-label-nav uppercase tracking-wider rounded-lg transition-colors flex items-center justify-center gap-1.5 text-on-surface">
              <span class="material-symbols-outlined text-base">download</span>
            </button>
          </div>
        </form>
      </div>

      <!-- Media Uploads Only -->
      <div class="p-6 rounded-2xl border border-outline-variant/70 bg-surface shadow-xs flex flex-col justify-between">
        <div>
          <div class="w-12 h-12 rounded-xl bg-surface-container-high text-on-surface-variant flex items-center justify-center mb-4">
            <span class="material-symbols-outlined text-2xl">photo_library</span>
          </div>
          <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Media Uploads Only</h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-2">
            ZIP archive containing all user-uploaded product photos, brand assets, banners, and attachments from <code class="px-1 py-0.5 rounded bg-surface-container text-[11px]">storage/uploads/</code>.
          </p>
          <ul class="mt-4 space-y-2 text-[13px] text-on-surface-variant">
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-outline text-base">check_circle</span>
              All JPG, PNG, WebP, GIF files
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-outline text-base">check_circle</span>
              Preserves directory structure
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-outline text-base">check_circle</span>
              Zipped for quick archiving
            </li>
          </ul>
        </div>

        <form method="POST" action="<?= e(url('admin/backup.php')) ?>" class="mt-6 flex flex-col gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create_backup">
          <input type="hidden" name="backup_type" value="uploads">
          <div class="flex items-center gap-2">
            <button type="submit" name="destination" value="storage" class="flex-1 py-2.5 px-3 bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-label-nav text-label-nav uppercase tracking-wider rounded-lg transition-colors flex items-center justify-center gap-2 font-bold">
              <span class="material-symbols-outlined text-base">save</span> Save Snapshot
            </button>
            <button type="submit" name="destination" value="download" title="Download directly to your computer" class="py-2.5 px-3 border border-outline-variant hover:bg-surface-container font-label-nav text-label-nav uppercase tracking-wider rounded-lg transition-colors flex items-center justify-center gap-1.5 text-on-surface">
              <span class="material-symbols-outlined text-base">download</span>
            </button>
          </div>
        </form>
      </div>

    </div>
  </section>

  <!-- ========================================================================= -->
  <!-- TAB 2: RESTORE SYSTEM -->
  <!-- ========================================================================= -->
  <section data-tab-content="restore" class="hidden flex flex-col gap-6">
    <div class="p-6 rounded-2xl border border-outline-variant/70 bg-surface shadow-xs">
      <div class="max-w-2xl">
        <div class="flex items-center gap-2 text-primary font-bold mb-2">
          <span class="material-symbols-outlined text-xl">security</span>
          <span class="font-label-nav text-label-nav uppercase tracking-wider">Guarded Restoration Center</span>
        </div>
        <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Restore from a Backup Archive</h2>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-2">
          Upload a previously generated <code class="px-1 py-0.5 rounded bg-surface-container text-[12px]">.zip</code> full backup bundle or <code class="px-1 py-0.5 rounded bg-surface-container text-[12px]">.sql</code> database dump.
        </p>

        <!-- Safety guarantee notice -->
        <div class="my-5 p-4 rounded-xl bg-surface-container border border-outline-variant/60 flex items-start gap-3">
          <span class="material-symbols-outlined text-primary text-xl shrink-0 mt-0.5">verified</span>
          <div class="text-[13px] text-on-surface">
            <strong class="font-bold block text-on-surface mb-0.5">Automatic Safety Protection</strong>
            Before applying the restore, the system will automatically create a pre-restore safety snapshot of your current database. Your administrator account credentials will also be preserved so you do not get logged out.
          </div>
        </div>

        <form method="POST" action="<?= e(url('admin/backup.php')) ?>" enctype="multipart/form-data" class="flex flex-col gap-4 mt-4" onsubmit="return confirmRestore(this);">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="restore_upload">

          <label class="flex flex-col gap-1.5">
            <span class="font-label-nav text-label-nav uppercase tracking-wider text-on-surface font-bold">Select Backup Archive (.zip or .sql)</span>
            <input type="file" name="backup_file" accept=".zip,.sql" required
                   class="block w-full text-sm text-on-surface file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-container/60 file:text-primary hover:file:bg-primary-container file:cursor-pointer border border-outline-variant rounded-lg p-2 bg-surface-container-lowest">
          </label>

          <label class="flex flex-col gap-1.5 mt-2">
            <span class="font-label-nav text-label-nav uppercase tracking-wider text-on-surface font-bold">Confirm Administrator Password</span>
            <input type="password" name="admin_password" required placeholder="Enter your current admin password"
                   class="h-11 px-3 border border-outline-variant rounded-lg font-body-sm text-body-sm focus:border-primary outline-none bg-surface max-w-md">
            <span class="text-[12px] text-on-surface-variant">Required to authenticate destructive state modification.</span>
          </label>

          <div class="pt-3">
            <button type="submit" class="px-6 py-3 bg-primary text-on-primary hover:bg-primary/90 font-label-nav text-label-nav uppercase tracking-wider rounded-lg font-bold flex items-center gap-2 shadow-sm transition-all">
              <span class="material-symbols-outlined text-lg">settings_backup_restore</span>
              Execute Safe Restoration
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>

  <!-- ========================================================================= -->
  <!-- TAB 3: SERVER SNAPSHOTS REGISTRY -->
  <!-- ========================================================================= -->
  <section data-tab-content="snapshots" class="hidden flex flex-col gap-6">
    <div class="rounded-2xl border border-outline-variant/70 bg-surface shadow-xs overflow-hidden">
      <div class="p-5 border-b border-outline-variant/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-surface-container-lowest">
        <div>
          <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Server Stored Snapshots</h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant">
            Archives stored in <code class="px-1.5 py-0.5 rounded bg-surface-container font-mono text-[12px]">storage/backups/</code>.
          </p>
        </div>
        <div class="text-right">
          <span class="font-label-tag text-label-tag uppercase tracking-wider text-on-surface-variant">Total Storage:</span>
          <span class="font-bold text-on-surface text-sm ml-1"><?= e($stats['backups_size_fmt']) ?></span>
        </div>
      </div>

      <?php if (empty($snapshots)): ?>
        <div class="p-12 text-center flex flex-col items-center justify-center gap-3 text-on-surface-variant">
          <span class="material-symbols-outlined text-5xl text-outline-variant">inventory_2</span>
          <p class="font-body-md text-body-md font-semibold">No saved snapshots found in storage/backups/.</p>
          <p class="text-sm text-on-surface-variant max-w-md">Take a full snapshot using the Create Backup tab to safeguard your catalog and orders.</p>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full text-left font-body-sm text-body-sm">
            <thead class="bg-surface-container/60 text-on-surface-variant uppercase text-[11px] tracking-wider border-b border-outline-variant/60">
              <tr>
                <th class="py-3 px-4 font-bold">Snapshot Archive</th>
                <th class="py-3 px-4 font-bold">Type</th>
                <th class="py-3 px-4 font-bold">Size</th>
                <th class="py-3 px-4 font-bold">Created Date</th>
                <th class="py-3 px-4 font-bold text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/40">
              <?php foreach ($snapshots as $snap): ?>
                <tr class="hover:bg-surface-container-lowest/70 transition-colors">
                  <!-- Filename & Safety Badge -->
                  <td class="py-3.5 px-4 font-medium text-on-surface">
                    <div class="flex items-center gap-2">
                      <span class="material-symbols-outlined text-lg <?= $snap['type'] === 'full' ? 'text-primary' : ($snap['type'] === 'database' ? 'text-secondary' : 'text-on-surface-variant') ?>">
                        <?= $snap['type'] === 'full' ? 'folder_zip' : ($snap['type'] === 'database' ? 'database' : 'photo_library') ?>
                      </span>
                      <span class="font-mono text-xs text-on-surface font-semibold"><?= e($snap['filename']) ?></span>
                      <?php if ($snap['is_safety']): ?>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-700 border border-amber-500/20">
                          Auto Safety
                        </span>
                      <?php endif; ?>
                    </div>
                  </td>

                  <!-- Type Badge -->
                  <td class="py-3.5 px-4">
                    <span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-0.5 rounded font-bold
                      <?= match($snap['type']) {
                          'full'     => 'bg-primary-container/60 text-primary',
                          'database' => 'bg-secondary-fixed/60 text-secondary',
                          default    => 'bg-surface-container-high text-on-surface-variant'
                      } ?>">
                      <?= e($snap['type']) ?>
                    </span>
                  </td>

                  <!-- Size -->
                  <td class="py-3.5 px-4 font-mono text-xs text-on-surface-variant">
                    <?= e($snap['size_formatted']) ?>
                  </td>

                  <!-- Date -->
                  <td class="py-3.5 px-4 text-xs text-on-surface-variant">
                    <?= e($snap['date_formatted']) ?>
                  </td>

                  <!-- Action Buttons -->
                  <td class="py-3.5 px-4 text-right">
                    <div class="inline-flex items-center gap-1.5">
                      <!-- Direct Download -->
                      <a href="<?= e(url('admin/backup.php?action=download&file=' . urlencode($snap['filename']) . '&csrf=' . csrf_token())) ?>"
                         title="Download to computer"
                         class="p-1.5 rounded-lg border border-outline-variant hover:bg-surface-container text-on-surface transition-colors">
                        <span class="material-symbols-outlined text-base">download</span>
                      </a>

                      <!-- Quick Restore Button (Triggers modal) -->
                      <button type="button"
                              title="Restore system from this snapshot"
                              onclick="openRestoreModal('<?= e($snap['filename']) ?>')"
                              class="p-1.5 rounded-lg border border-outline-variant hover:bg-primary-container/40 text-primary transition-colors">
                        <span class="material-symbols-outlined text-base">settings_backup_restore</span>
                      </button>

                      <!-- Delete Button (Triggers form) -->
                      <form method="POST" action="<?= e(url('admin/backup.php')) ?>" class="inline-block" onsubmit="return confirm('Delete snapshot <?= e($snap['filename']) ?> permanently?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_snapshot">
                        <input type="hidden" name="filename" value="<?= e($snap['filename']) ?>">
                        <button type="submit" title="Delete snapshot" class="p-1.5 rounded-lg border border-outline-variant hover:bg-error-container/40 text-error transition-colors">
                          <span class="material-symbols-outlined text-base">delete</span>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ========================================================================= -->
  <!-- TAB 4: DATA WIPE & RESET CENTER (DANGER ZONE) -->
  <!-- ========================================================================= -->
  <section data-tab-content="wipe" class="hidden flex flex-col gap-6">

    <!-- Warning Header -->
    <div class="p-5 rounded-2xl bg-error-container/20 border border-error/30 flex items-start gap-4">
      <span class="material-symbols-outlined text-error text-3xl shrink-0 mt-0.5">warning</span>
      <div>
        <h2 class="font-headline-sm text-headline-sm font-black text-error">Granular Data Wipe & Reset Operations</h2>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
          Perform controlled data purging for store transitions, staging-to-production launches, or fresh catalog rebuilds.
          Every operation <strong class="text-on-surface">automatically generates a safety rollback snapshot</strong> before running.
        </p>
      </div>
    </div>

    <!-- Wipe Grid Options -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

      <!-- Option 1: Wipe Orders & Transaction History -->
      <div class="p-6 rounded-2xl border border-outline-variant/80 bg-surface shadow-xs flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between gap-2 mb-3">
            <span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-0.5 rounded bg-amber-500/10 text-amber-700 font-bold border border-amber-500/20">
              Go-Live Preparation
            </span>
            <span class="material-symbols-outlined text-xl text-amber-600">receipt_long</span>
          </div>
          <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Wipe Orders & Customer Activity</h3>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-2">
            Purges all test orders, order items, events, shopping carts, customer reviews, contact messages, and customer accounts.
          </p>
          <div class="mt-4 p-3 rounded-lg bg-surface-container text-xs text-on-surface space-y-1">
            <div class="font-bold text-on-surface flex items-center gap-1.5 text-emerald-700">
              <span class="material-symbols-outlined text-sm">check</span> Preserves:
            </div>
            <div>Products, Categories, Brands, Site Settings, Admin accounts.</div>
          </div>
        </div>

        <div class="mt-6 pt-4 border-t border-outline-variant/60">
          <button type="button" onclick="openWipeModal('transactions', 'Wipe Orders & Customer Activity', 'CONFIRM-WIPE-TRANSACTIONS', 'All orders, order items, cart items, customer addresses, contact messages, reviews, and customer accounts will be permanently cleared.')"
                  class="w-full py-2.5 px-4 bg-surface-container-high hover:bg-error/10 hover:text-error hover:border-error border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase tracking-wider font-bold transition-colors text-on-surface flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-base">delete_sweep</span>
            Wipe Transactional Data
          </button>
        </div>
      </div>

      <!-- Option 2: Wipe Product Catalog & Media -->
      <div class="p-6 rounded-2xl border border-outline-variant/80 bg-surface shadow-xs flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between gap-2 mb-3">
            <span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-0.5 rounded bg-amber-500/10 text-amber-700 font-bold border border-amber-500/20">
              Inventory Reset
            </span>
            <span class="material-symbols-outlined text-xl text-amber-600">inventory_2</span>
          </div>
          <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Wipe Catalogue & Media Uploads</h3>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-2">
            Deletes all products, variants, specs, bundles, categories, brands, and clears uploaded photos from storage/uploads/.
          </p>
          <div class="mt-4 p-3 rounded-lg bg-surface-container text-xs text-on-surface space-y-1">
            <div class="font-bold text-on-surface flex items-center gap-1.5 text-emerald-700">
              <span class="material-symbols-outlined text-sm">check</span> Preserves:
            </div>
            <div>Order history, Customer accounts, Site Settings, Admin accounts.</div>
          </div>
        </div>

        <div class="mt-6 pt-4 border-t border-outline-variant/60">
          <button type="button" onclick="openWipeModal('catalog', 'Wipe Catalogue & Media Uploads', 'CONFIRM-WIPE-CATALOG', 'All products, variants, specifications, bundles, categories, brands, and uploaded images will be permanently erased.')"
                  class="w-full py-2.5 px-4 bg-surface-container-high hover:bg-error/10 hover:text-error hover:border-error border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase tracking-wider font-bold transition-colors text-on-surface flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-base">delete_sweep</span>
            Wipe Product Catalogue
          </button>
        </div>
      </div>

      <!-- Option 3: Reset to Fresh Demo Seed Data -->
      <div class="p-6 rounded-2xl border border-outline-variant/80 bg-surface shadow-xs flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between gap-2 mb-3">
            <span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-0.5 rounded bg-blue-500/10 text-blue-700 font-bold border border-blue-500/20">
              Seed Demo State
            </span>
            <span class="material-symbols-outlined text-xl text-blue-600">restart_alt</span>
          </div>
          <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Reset to Fresh Demo Seed</h3>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-2">
            Re-populates the complete default Zion catalogue (lingerie & musical instruments, categories, brands, sample reviews) from seed.sql.
          </p>
          <div class="mt-4 p-3 rounded-lg bg-surface-container text-xs text-on-surface space-y-1">
            <div class="font-bold text-on-surface flex items-center gap-1.5 text-emerald-700">
              <span class="material-symbols-outlined text-sm">check</span> Preserves:
            </div>
            <div>Your active administrator login credentials.</div>
          </div>
        </div>

        <div class="mt-6 pt-4 border-t border-outline-variant/60">
          <button type="button" onclick="openWipeModal('seed_reset', 'Reset to Fresh Demo Seed', 'CONFIRM-SEED-RESET', 'Current catalogue and orders will be replaced with fresh seed catalogue data.')"
                  class="w-full py-2.5 px-4 bg-surface-container-high hover:bg-blue-600 hover:text-white border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase tracking-wider font-bold transition-colors text-on-surface flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-base">refresh</span>
            Apply Demo Seed Catalog
          </button>
        </div>
      </div>

      <!-- Option 4: Full Factory Reset -->
      <div class="p-6 rounded-2xl border-2 border-error/40 bg-surface shadow-xs flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between gap-2 mb-3">
            <span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-0.5 rounded bg-error-container text-on-error-container font-black">
              Extreme Danger
            </span>
            <span class="material-symbols-outlined text-xl text-error">dangerous</span>
          </div>
          <h3 class="font-headline-sm text-headline-sm font-bold text-error">Complete Factory Reset</h3>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-2">
            Erases all transactional data, products, categories, brands, promo codes, and resets site settings to fresh defaults.
          </p>
          <div class="mt-4 p-3 rounded-lg bg-error-container/20 text-xs text-on-surface space-y-1 border border-error/20">
            <div class="font-bold text-error flex items-center gap-1.5">
              <span class="material-symbols-outlined text-sm">shield</span> Non-lockout guarantee:
            </div>
            <div>Only your active administrator account will be preserved.</div>
          </div>
        </div>

        <div class="mt-6 pt-4 border-t border-outline-variant/60">
          <button type="button" onclick="openWipeModal('factory_reset', 'Complete Factory Reset', 'CONFIRM-FACTORY-RESET', 'Every table in the database will be wiped clean and settings will return to initial defaults.')"
                  class="w-full py-2.5 px-4 bg-error text-on-error hover:bg-error/90 rounded-lg font-label-nav text-label-nav uppercase tracking-wider font-bold transition-colors flex items-center justify-center gap-2 shadow-xs">
            <span class="material-symbols-outlined text-base">delete_forever</span>
            Execute Factory Reset
          </button>
        </div>
      </div>

    </div>
  </section>

</div>

<!-- ========================================================================= -->
<!-- MODAL: RESTORE CONFIRMATION -->
<!-- ========================================================================= -->
<div id="restoreModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
  <div class="bg-surface rounded-2xl border border-outline-variant max-w-md w-full p-6 shadow-xl relative animate-in fade-in zoom-in-95 duration-150">
    <button type="button" onclick="closeRestoreModal()" class="absolute top-4 right-4 text-on-surface-variant hover:text-on-surface">
      <span class="material-symbols-outlined text-xl">close</span>
    </button>
    <div class="flex items-center gap-3 text-primary mb-3">
      <span class="material-symbols-outlined text-2xl">settings_backup_restore</span>
      <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Confirm Snapshot Restore</h3>
    </div>
    <p class="text-sm text-on-surface-variant mb-4">
      You are restoring from: <code id="restoreFileName" class="px-1.5 py-0.5 rounded bg-surface-container font-mono text-xs font-bold text-on-surface"></code>.
      An emergency safety snapshot will automatically be taken before restoring.
    </p>

    <form method="POST" action="<?= e(url('admin/backup.php')) ?>" class="flex flex-col gap-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="restore_snapshot">
      <input type="hidden" name="filename" id="restoreFileHidden" value="">

      <label class="flex flex-col gap-1.5">
        <span class="font-label-nav text-label-nav uppercase tracking-wider text-on-surface font-bold">Admin Password</span>
        <input type="password" name="admin_password" required placeholder="Enter password to confirm"
               class="h-11 px-3 border border-outline-variant rounded-lg font-body-sm text-body-sm focus:border-primary outline-none">
      </label>

      <div class="flex items-center justify-end gap-2 pt-2">
        <button type="button" onclick="closeRestoreModal()" class="px-4 py-2.5 rounded-lg border border-outline-variant font-label-nav text-label-nav uppercase tracking-wider hover:bg-surface-container">
          Cancel
        </button>
        <button type="submit" class="px-4 py-2.5 bg-primary text-on-primary hover:bg-primary/90 font-label-nav text-label-nav uppercase tracking-wider rounded-lg font-bold">
          Proceed Restore
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: DATA WIPE CHALLENGE -->
<!-- ========================================================================= -->
<div id="wipeModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
  <div class="bg-surface rounded-2xl border-2 border-error/50 max-w-lg w-full p-6 shadow-2xl relative animate-in fade-in zoom-in-95 duration-150">
    <button type="button" onclick="closeWipeModal()" class="absolute top-4 right-4 text-on-surface-variant hover:text-on-surface">
      <span class="material-symbols-outlined text-xl">close</span>
    </button>
    <div class="flex items-center gap-3 text-error mb-3">
      <span class="material-symbols-outlined text-3xl">warning</span>
      <h3 id="wipeModalTitle" class="font-headline-sm text-headline-sm font-black text-on-surface"></h3>
    </div>
    <div id="wipeModalDesc" class="text-sm text-on-surface-variant mb-4 p-3 rounded-xl bg-error-container/20 border border-error/20"></div>

    <form method="POST" action="<?= e(url('admin/backup.php')) ?>" class="flex flex-col gap-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="wipe_data">
      <input type="hidden" name="wipe_type" id="wipeTypeHidden" value="">

      <label class="flex flex-col gap-1.5">
        <span class="font-label-nav text-label-nav uppercase tracking-wider text-on-surface font-bold">
          Type Confirmation Phrase: <code id="wipeRequiredPhrase" class="text-error font-mono select-all bg-surface-container px-1 py-0.5 rounded"></code>
        </span>
        <input type="text" name="confirm_phrase" id="wipePhraseInput" required autocomplete="off" placeholder="Type the phrase exactly as shown"
               class="h-11 px-3 border border-outline-variant rounded-lg font-mono text-sm focus:border-error outline-none">
      </label>

      <label class="flex flex-col gap-1.5">
        <span class="font-label-nav text-label-nav uppercase tracking-wider text-on-surface font-bold">Confirm Administrator Password</span>
        <input type="password" name="admin_password" required placeholder="Enter your admin password"
               class="h-11 px-3 border border-outline-variant rounded-lg font-body-sm text-body-sm focus:border-error outline-none">
      </label>

      <div class="flex items-center justify-end gap-2 pt-2">
        <button type="button" onclick="closeWipeModal()" class="px-4 py-2.5 rounded-lg border border-outline-variant font-label-nav text-label-nav uppercase tracking-wider hover:bg-surface-container">
          Cancel
        </button>
        <button type="submit" class="px-5 py-2.5 bg-error text-on-error hover:bg-error/90 font-label-nav text-label-nav uppercase tracking-wider rounded-lg font-black shadow-xs">
          Execute Data Wipe
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  // Tab switching logic
  (function () {
    const tabBtns = document.querySelectorAll('[data-tab-btn]');
    const tabContents = document.querySelectorAll('[data-tab-content]');

    function switchTab(name) {
      tabBtns.forEach(b => {
        const active = b.getAttribute('data-tab-btn') === name;
        b.classList.toggle('border-primary', active);
        b.classList.toggle('text-primary', active);
        b.classList.toggle('border-transparent', !active);
        b.classList.toggle('text-on-surface-variant', !active);
      });
      tabContents.forEach(c => {
        const show = c.getAttribute('data-tab-content') === name;
        c.classList.toggle('hidden', !show);
      });
      try {
        window.location.hash = name;
      } catch (e) {}
    }

    tabBtns.forEach(btn => {
      btn.addEventListener('click', () => switchTab(btn.getAttribute('data-tab-btn')));
    });

    // Check URL hash on page load
    const hash = window.location.hash.replace('#', '');
    if (hash && document.querySelector(`[data-tab-btn="${hash}"]`)) {
      switchTab(hash);
    }
  })();

  // Restore Modal
  function openRestoreModal(fileName) {
    document.getElementById('restoreFileName').textContent = fileName;
    document.getElementById('restoreFileHidden').value = fileName;
    document.getElementById('restoreModal').classList.remove('hidden');
  }
  function closeRestoreModal() {
    document.getElementById('restoreModal').classList.add('hidden');
  }

  // Wipe Modal
  function openWipeModal(type, title, phrase, desc) {
    document.getElementById('wipeTypeHidden').value = type;
    document.getElementById('wipeModalTitle').textContent = title;
    document.getElementById('wipeModalDesc').textContent = desc;
    document.getElementById('wipeRequiredPhrase').textContent = phrase;
    document.getElementById('wipePhraseInput').value = '';
    document.getElementById('wipeModal').classList.remove('hidden');
  }
  function closeWipeModal() {
    document.getElementById('wipeModal').classList.add('hidden');
  }

  // Confirm restore form
  function confirmRestore(form) {
    return confirm('Are you sure you want to restore the selected backup archive? All current data will be updated based on the backup content.');
  }

  // Escape key closes modals
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeRestoreModal();
      closeWipeModal();
    }
  });
</script>

<?php admin_foot(); ?>
