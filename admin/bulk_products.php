<?php
/**
 * Zion Groups of Companies - Bulk Product Import & Export Administration Center.
 *
 * Provides high-speed CSV catalogue imports, validation dry-runs, interactive previews,
 * automated taxonomy resolution, and full catalogue exports.
 */

declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/product_bulk_service.php';

require_admin();
$admin = current_user();

// ------------------------------------------------------------- GET: Direct CSV Streams
if (isset($_GET['action'])) {
    $act  = (string) $_GET['action'];
    $csrf = (string) ($_GET['csrf'] ?? '');

    if ($act === 'template') {
        $csv = bulk_generate_sample_csv();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="velora_products_template.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $csv;
        exit;
    }

    if ($act === 'export') {
        if (!hash_equals(csrf_token(), $csrf)) {
            flash_set('error', 'Session token expired. Please try exporting again.');
            header('Location: ' . url('admin/bulk_products.php?tab=export'));
            exit;
        }
        $dept = in_array($_GET['dept'] ?? '', ['lingerie', 'instruments'], true) ? (string) $_GET['dept'] : '';
        $csv = bulk_export_products_csv(['department' => $dept]);
        $filename = 'velora_catalogue_' . ($dept !== '' ? $dept . '_' : 'all_') . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $csv;
        exit;
    }

    if ($act === 'download_errors') {
        $errReport = $_SESSION['bulk_last_error_csv'] ?? '';
        if ($errReport === '') {
            flash_set('error', 'No recent error report found.');
            header('Location: ' . url('admin/bulk_products.php'));
            exit;
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="velora_bulk_import_errors_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $errReport;
        exit;
    }
}

// ------------------------------------------------------------- POST Action Handlers
$tab = (string) ($_GET['tab'] ?? 'import');
$previewData = null;
$executionReport = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    // 1. STAGE 1: UPLOAD & DRY-RUN PREVIEW
    if ($action === 'upload_preview') {
        $csvContent = '';

        // Check file upload
        if (isset($_FILES['csv_file']) && is_array($_FILES['csv_file']) && ($_FILES['csv_file']['error'] ?? 0) === UPLOAD_ERR_OK) {
            $tmpPath = (string) $_FILES['csv_file']['tmp_name'];
            if (is_uploaded_file($tmpPath)) {
                $csvContent = (string) file_get_contents($tmpPath);
            }
        } elseif (!empty($_POST['csv_text'])) {
            $csvContent = (string) $_POST['csv_text'];
        }

        if (trim($csvContent) === '') {
            flash_set('error', 'Please select a CSV file or paste valid CSV content to import.');
            header('Location: ' . url('admin/bulk_products.php?tab=import'));
            exit;
        }

        $parsed = bulk_parse_csv_string($csvContent);
        if (isset($parsed['error']) || $parsed['rows'] === []) {
            flash_set('error', $parsed['error'] ?? 'Could not parse any valid rows from the provided CSV.');
            header('Location: ' . url('admin/bulk_products.php?tab=import'));
            exit;
        }

        $preview = bulk_preview_validation($parsed['rows']);
        $_SESSION['bulk_preview_state'] = [
            'timestamp'   => time(),
            'raw_rows'    => $parsed['rows'],
            'preview'     => $preview,
            'source_name' => $_FILES['csv_file']['name'] ?? 'Pasted Data',
        ];

        header('Location: ' . url('admin/bulk_products.php?tab=preview'));
        exit;
    }

    // 2. STAGE 2: EXECUTE CONFIRMED IMPORT
    if ($action === 'execute_import') {
        $previewState = $_SESSION['bulk_preview_state'] ?? null;
        if (!$previewState || !isset($previewState['preview']['results'])) {
            flash_set('error', 'The import preview has expired. Please re-upload your CSV file.');
            header('Location: ' . url('admin/bulk_products.php?tab=import'));
            exit;
        }

        $mode          = (string) ($_POST['import_mode'] ?? 'upsert');
        $autoTaxonomy  = !empty($_POST['auto_taxonomy']);
        $validatedRows = $previewState['preview']['results'];

        $result = bulk_execute_import($validatedRows, [
            'mode'          => $mode,
            'auto_taxonomy' => $autoTaxonomy,
        ]);

        // Generate error report CSV if there were errors
        if ($result['failed'] > 0 && $result['errors'] !== []) {
            $_SESSION['bulk_last_error_csv'] = bulk_generate_error_report_csv($result['errors'], $previewState['raw_rows']);
        } else {
            unset($_SESSION['bulk_last_error_csv']);
        }

        $_SESSION['bulk_last_result'] = $result;
        unset($_SESSION['bulk_preview_state']);

        header('Location: ' . url('admin/bulk_products.php?tab=result'));
        exit;
    }

    // 3. CANCEL PREVIEW
    if ($action === 'cancel_preview') {
        unset($_SESSION['bulk_preview_state']);
        flash_set('info', 'Bulk import operation cancelled.');
        header('Location: ' . url('admin/bulk_products.php?tab=import'));
        exit;
    }
}

// Retrieve session states for rendering
if ($tab === 'preview' && isset($_SESSION['bulk_preview_state'])) {
    $previewData = $_SESSION['bulk_preview_state'];
} elseif ($tab === 'result' && isset($_SESSION['bulk_last_result'])) {
    $executionReport = $_SESSION['bulk_last_result'];
}

$schema = bulk_product_schema();
$totalProducts = (int) db_val('SELECT COUNT(*) FROM products', [], 0);
$totalLingerie = (int) db_val('SELECT COUNT(*) FROM products WHERE department = "lingerie"', [], 0);
$totalInstruments = (int) db_val('SELECT COUNT(*) FROM products WHERE department = "instruments"', [], 0);

admin_head('Bulk Product Management', 'bulk_products');
?>

<div class="max-w-7xl mx-auto space-y-6">
  <!-- Breadcrumb & Header -->
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <nav class="font-label-nav text-label-nav text-on-surface-variant mb-2 flex items-center gap-2">
        <a class="hover:text-primary transition-colors" href="<?= e(url('admin/index.php')) ?>">Dashboard</a>
        <span class="text-outline-variant">/</span>
        <a class="hover:text-primary transition-colors" href="<?= e(url('admin/products.php')) ?>">Products</a>
        <span class="text-outline-variant">/</span>
        <span class="text-primary font-semibold">Bulk Operations</span>
      </nav>
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-primary-container/15 text-primary flex items-center justify-center">
          <span class="material-symbols-outlined text-2xl">dataset</span>
        </div>
        <div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Bulk Product Management</h1>
          <p class="font-body-sm text-body-sm text-on-surface-variant">
            Import, update, validate, and export catalogue items in bulk via Excel / CSV format.
          </p>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="flex flex-wrap items-center gap-2">
      <a href="<?= e(url('admin/bulk_products.php?action=template')) ?>"
         class="px-4 py-2.5 bg-surface-container-lowest border border-outline-variant text-on-surface hover:border-primary hover:text-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider inline-flex items-center gap-2 transition-colors">
        <span class="material-symbols-outlined text-base">download</span>
        Download Sample CSV
      </a>
      <a href="<?= e(url('admin/products.php')) ?>"
         class="px-4 py-2.5 bg-inverse-surface text-surface hover:bg-black rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider inline-flex items-center gap-2 transition-colors">
        <span class="material-symbols-outlined text-base">arrow_back</span>
        Products Catalogue
      </a>
    </div>
  </div>

  <!-- Tab Navigation -->
  <div class="border-b border-outline-variant flex gap-6">
    <a href="<?= e(url('admin/bulk_products.php?tab=import')) ?>"
       class="pb-3 font-label-nav text-label-nav uppercase tracking-wider font-bold border-b-2 transition-colors inline-flex items-center gap-2 <?= in_array($tab, ['import', 'preview', 'result'], true) ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface' ?>">
      <span class="material-symbols-outlined text-lg">upload_file</span>
      Bulk Import <?= $previewData ? '(Preview Active)' : '' ?>
    </a>
    <a href="<?= e(url('admin/bulk_products.php?tab=export')) ?>"
       class="pb-3 font-label-nav text-label-nav uppercase tracking-wider font-bold border-b-2 transition-colors inline-flex items-center gap-2 <?= $tab === 'export' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface' ?>">
      <span class="material-symbols-outlined text-lg">file_download</span>
      Bulk Export & Backup
    </a>
    <a href="<?= e(url('admin/bulk_products.php?tab=guide')) ?>"
       class="pb-3 font-label-nav text-label-nav uppercase tracking-wider font-bold border-b-2 transition-colors inline-flex items-center gap-2 <?= $tab === 'guide' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface' ?>">
      <span class="material-symbols-outlined text-lg">menu_book</span>
      CSV Schema & Guide
    </a>
  </div>

  <!-- TAB CONTENT 1: EXECUTION RESULT SCREEN -->
  <?php if ($tab === 'result' && $executionReport): ?>
    <section class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-4 border-b border-outline-variant pb-4">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-xl <?= $executionReport['failed'] === 0 ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-error-container text-on-error-container' ?> flex items-center justify-center">
            <span class="material-symbols-outlined text-3xl"><?= $executionReport['failed'] === 0 ? 'check_circle' : 'warning' ?></span>
          </div>
          <div>
            <h2 class="font-headline-md text-headline-md text-on-surface font-bold">
              <?= $executionReport['failed'] === 0 ? 'Bulk Import Completed Successfully!' : 'Bulk Import Completed with Warnings/Errors' ?>
            </h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
              Detailed breakdown of created, updated, and skipped catalogue records.
            </p>
          </div>
        </div>

        <?php if (!empty($_SESSION['bulk_last_error_csv'])): ?>
          <a href="<?= e(url('admin/bulk_products.php?action=download_errors')) ?>"
             class="px-4 py-2.5 bg-error-container text-on-error-container hover:opacity-90 rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider inline-flex items-center gap-2 transition-opacity">
            <span class="material-symbols-outlined text-base">download</span>
            Download Error Report CSV
          </a>
        <?php endif; ?>
      </div>

      <!-- Result Metrics Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl bg-surface-container border border-outline-variant/50">
          <span class="font-label-tag text-label-tag text-secondary uppercase tracking-wider block mb-1">New Products Added</span>
          <span class="font-headline-lg text-headline-lg font-bold text-on-surface"><?= number_format($executionReport['created']) ?></span>
        </div>
        <div class="p-4 rounded-xl bg-surface-container border border-outline-variant/50">
          <span class="font-label-tag text-label-tag text-primary uppercase tracking-wider block mb-1">Products Updated</span>
          <span class="font-headline-lg text-headline-lg font-bold text-on-surface"><?= number_format($executionReport['updated']) ?></span>
        </div>
        <div class="p-4 rounded-xl bg-surface-container border border-outline-variant/50">
          <span class="font-label-tag text-label-tag text-outline uppercase tracking-wider block mb-1">Rows Skipped</span>
          <span class="font-headline-lg text-headline-lg font-bold text-on-surface"><?= number_format($executionReport['skipped']) ?></span>
        </div>
        <div class="p-4 rounded-xl bg-surface-container border border-outline-variant/50">
          <span class="font-label-tag text-label-tag text-error uppercase tracking-wider block mb-1">Failed Rows</span>
          <span class="font-headline-lg text-headline-lg font-bold text-on-surface"><?= number_format($executionReport['failed']) ?></span>
        </div>
      </div>

      <!-- Execution Log -->
      <div class="space-y-2">
        <h3 class="font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant font-bold">Execution Activity Log</h3>
        <div class="max-h-64 overflow-y-auto bg-surface-container-high rounded-xl p-4 font-mono text-xs text-on-surface space-y-1">
          <?php foreach ($executionReport['log'] as $logLine): ?>
            <div class="leading-relaxed"><?= $logLine ?></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="flex items-center gap-3 pt-2">
        <a href="<?= e(url('admin/products.php')) ?>"
           class="px-5 py-2.5 bg-primary text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary-container transition-colors">
          View Catalogue
        </a>
        <a href="<?= e(url('admin/bulk_products.php?tab=import')) ?>"
           class="px-5 py-2.5 border border-outline-variant text-on-surface rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-surface-container transition-colors">
          Import Another File
        </a>
      </div>
    </section>

  <!-- TAB CONTENT 2: PREVIEW DRY-RUN SCREEN -->
  <?php elseif ($tab === 'preview' && $previewData): ?>
    <?php $prev = $previewData['preview']; ?>
    <section class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-4 border-b border-outline-variant pb-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full bg-primary-fixed text-primary font-label-tag text-label-tag font-bold uppercase tracking-wider">Dry-Run Preview</span>
            <span class="font-body-sm text-body-sm text-on-surface-variant">Source: <?= e((string) $previewData['source_name']) ?></span>
          </div>
          <h2 class="font-headline-md text-headline-md text-on-surface font-bold mt-1">Review & Confirm Catalogue Import</h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant">
            Please verify the parsed data before committing changes to the live catalogue.
          </p>
        </div>

        <!-- Metrics Chips -->
        <div class="flex flex-wrap items-center gap-2 font-label-tag text-label-tag">
          <span class="px-3 py-1.5 rounded-lg bg-surface-container font-semibold text-on-surface">Total: <?= $prev['total'] ?> rows</span>
          <span class="px-3 py-1.5 rounded-lg bg-secondary-fixed text-on-secondary-fixed font-semibold">Valid: <?= $prev['valid_count'] ?></span>
          <span class="px-3 py-1.5 rounded-lg bg-primary-container text-on-primary font-semibold">New: <?= $prev['insert_count'] ?></span>
          <span class="px-3 py-1.5 rounded-lg bg-surface-container-high text-primary font-semibold">Updates: <?= $prev['update_count'] ?></span>
          <?php if ($prev['error_count'] > 0): ?>
            <span class="px-3 py-1.5 rounded-lg bg-error-container text-on-error-container font-semibold">Errors: <?= $prev['error_count'] ?></span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Preview Table -->
      <div class="overflow-x-auto border border-outline-variant/60 rounded-xl max-h-[500px] overflow-y-auto">
        <table class="w-full text-left text-body-sm">
          <thead class="sticky top-0 bg-surface-container-high border-b border-outline-variant text-on-surface-variant font-label-nav text-label-nav uppercase">
            <tr>
              <th class="py-3 px-3">Row</th>
              <th class="py-3 px-3">Action</th>
              <th class="py-3 px-3">SKU</th>
              <th class="py-3 px-3">Name</th>
              <th class="py-3 px-3">Dept</th>
              <th class="py-3 px-3">Category</th>
              <th class="py-3 px-3">Brand</th>
              <th class="py-3 px-3">Price</th>
              <th class="py-3 px-3">Stock</th>
              <th class="py-3 px-3">Variants / Specs</th>
              <th class="py-3 px-3">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-outline-variant/40 font-body-sm">
            <?php foreach ($prev['results'] as $idx => $r): ?>
              <?php
              $d = $r['data'];
              $rowBg = !$r['valid'] ? 'bg-error-container/10' : ($r['action'] === 'update' ? 'bg-primary-container/5' : '');
              ?>
              <tr class="<?= $rowBg ?> hover:bg-surface-container transition-colors">
                <td class="py-2.5 px-3 font-mono text-xs text-on-surface-variant"><?= $d['row_num'] ?></td>
                <td class="py-2.5 px-3">
                  <?php if (!$r['valid']): ?>
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-error-container text-on-error-container">Invalid</span>
                  <?php elseif ($r['action'] === 'update'): ?>
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-surface-container-highest text-primary">Update</span>
                  <?php else: ?>
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-secondary-fixed text-on-secondary-fixed">Create</span>
                  <?php endif; ?>
                </td>
                <td class="py-2.5 px-3 font-mono text-xs">
                  <?= $d['sku'] !== '' ? e($d['sku']) : '<span class="text-outline italic">Auto-generate</span>' ?>
                </td>
                <td class="py-2.5 px-3 font-medium text-on-surface max-w-[200px] truncate" title="<?= e($d['name']) ?>">
                  <?= e($d['name']) ?>
                </td>
                <td class="py-2.5 px-3">
                  <span class="capitalize text-xs font-semibold px-2 py-0.5 rounded bg-surface-container text-on-surface-variant">
                    <?= e($d['department']) ?>
                  </span>
                </td>
                <td class="py-2.5 px-3 text-xs text-on-surface-variant">
                  <?= $d['category'] !== '' ? e($d['category']) : '<span class="text-outline">-</span>' ?>
                </td>
                <td class="py-2.5 px-3 text-xs text-on-surface-variant">
                  <?= $d['brand'] !== '' ? e($d['brand']) : '<span class="text-outline">-</span>' ?>
                </td>
                <td class="py-2.5 px-3 font-semibold text-on-surface">
                  <?= format_money((float) $d['price']) ?>
                </td>
                <td class="py-2.5 px-3 text-xs">
                  <span class="<?= $d['stock'] > 0 ? 'text-on-surface' : 'text-error font-semibold' ?>">
                    <?= number_format($d['stock']) ?>
                  </span>
                </td>
                <td class="py-2.5 px-3 text-xs text-on-surface-variant">
                  <?php
                  $metaCounts = [];
                  if ($d['variants'] !== []) $metaCounts[] = count($d['variants']) . ' variants';
                  if ($d['specs'] !== []) $metaCounts[] = count($d['specs']) . ' specs';
                  if ($d['bundles'] !== []) $metaCounts[] = count($d['bundles']) . ' bundles';
                  echo $metaCounts !== [] ? implode(', ', $metaCounts) : '<span class="text-outline">-</span>';
                  ?>
                </td>
                <td class="py-2.5 px-3 text-xs">
                  <?php if (!$r['valid']): ?>
                    <span class="text-error font-medium" title="<?= e(implode('; ', $r['errors'])) ?>">
                      <?= e(implode('; ', $r['errors'])) ?>
                    </span>
                  <?php elseif ($r['warnings'] !== []): ?>
                    <span class="text-amber-600 font-medium" title="<?= e(implode('; ', $r['warnings'])) ?>">
                      <?= count($r['warnings']) ?> warning(s)
                    </span>
                  <?php else: ?>
                    <span class="text-secondary font-semibold">Ready</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Execution Form -->
      <form method="post" action="<?= e(url('admin/bulk_products.php')) ?>" class="space-y-6 pt-2 border-t border-outline-variant">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="execute_import" />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-surface-container/60 p-5 rounded-xl border border-outline-variant/50">
          <div>
            <label class="block font-label-nav text-label-nav uppercase tracking-wider text-on-surface font-bold mb-2">Import Strategy</label>
            <select name="import_mode" class="w-full h-11 px-3 border border-outline-variant rounded-lg font-body-sm text-body-sm bg-surface-container-lowest focus:border-primary outline-none">
              <option value="upsert" selected>Upsert: Create new products and update existing matching SKUs (Recommended)</option>
              <option value="insert_only">Insert Only: Add new items, skip existing matching SKUs</option>
              <option value="update_only">Update Only: Modify existing matching SKUs, ignore new items</option>
            </select>
            <p class="font-body-xs text-[12px] text-on-surface-variant mt-1.5">
              Determines how products are handled when an identical SKU is found in your catalogue.
            </p>
          </div>

          <div class="flex flex-col justify-center">
            <label class="inline-flex items-center gap-3 cursor-pointer select-none">
              <input type="checkbox" name="auto_taxonomy" value="1" checked class="w-5 h-5 text-primary rounded border-outline-variant focus:ring-primary" />
              <div>
                <span class="font-body-sm text-body-sm font-bold text-on-surface block">Auto-create Missing Categories & Brands</span>
                <span class="font-body-xs text-[12px] text-on-surface-variant block">Automatically register new categories and brands under their specified department.</span>
              </div>
            </label>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
          <button type="submit" class="px-6 py-3 bg-primary-container text-on-primary hover:bg-primary rounded-xl font-label-nav text-label-nav font-bold uppercase tracking-wider inline-flex items-center gap-2 shadow-xs transition-colors">
            <span class="material-symbols-outlined text-lg">cloud_sync</span>
            Confirm & Import <?= $prev['valid_count'] ?> Products
          </button>

          <form method="post" action="<?= e(url('admin/bulk_products.php')) ?>" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel_preview" />
            <button type="submit" class="px-5 py-3 border border-outline-variant text-on-surface hover:bg-surface-container rounded-xl font-label-nav text-label-nav font-bold uppercase tracking-wider transition-colors">
              Cancel & Start Over
            </button>
          </form>
        </div>
      </form>
    </section>

  <!-- TAB CONTENT 3: IMPORT CSV UPLOAD -->
  <?php elseif ($tab === 'import'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Upload Box (2 cols) -->
      <div class="lg:col-span-2 space-y-6">
        <section class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-6">
          <div>
            <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Step 1 of 2</span>
            <h2 class="font-headline-md text-headline-md text-on-surface font-bold">Upload CSV Spreadsheet</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
              Select your prepared catalogue file or paste raw comma-separated values. A preview will be generated for your review before any database changes are made.
            </p>
          </div>

          <form method="post" enctype="multipart/form-data" action="<?= e(url('admin/bulk_products.php')) ?>" class="space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload_preview" />

            <!-- Drag & Drop Zone -->
            <div id="dropZone" class="border-2 border-dashed border-outline-variant rounded-2xl p-8 text-center hover:border-primary transition-colors cursor-pointer bg-surface-container/30 relative">
              <input type="file" id="csvFileInput" name="csv_file" accept=".csv,text/csv,application/vnd.ms-excel" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
              <div class="space-y-3 pointer-events-none">
                <div class="w-14 h-14 mx-auto rounded-full bg-primary-container/15 text-primary flex items-center justify-center">
                  <span class="material-symbols-outlined text-3xl">upload_file</span>
                </div>
                <div>
                  <span class="font-headline-sm text-headline-sm font-bold text-on-surface block" id="dropFileName">
                    Drop your CSV file here, or <span class="text-primary underline">browse</span>
                  </span>
                  <span class="font-body-xs text-xs text-on-surface-variant mt-1 block">
                    Supports .CSV formatted from Microsoft Excel, Google Sheets, or Apple Numbers (UTF-8, max 10MB)
                  </span>
                </div>
              </div>
            </div>

            <!-- Alternative: Paste CSV text -->
            <details class="group bg-surface-container rounded-xl p-4 border border-outline-variant/50">
              <summary class="font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant font-bold cursor-pointer select-none flex items-center justify-between">
                <span>Or Paste Raw CSV Text</span>
                <span class="material-symbols-outlined text-base group-open:rotate-180 transition-transform">expand_more</span>
              </summary>
              <div class="pt-3">
                <textarea name="csv_text" rows="6" placeholder="sku,name,department,category,brand,price,stock&#10;VL-LI-00101,Aura Bodysuit,lingerie,Bodysuits,Velora,89.00,25"
                          class="w-full p-3 font-mono text-xs border border-outline-variant rounded-lg bg-surface-container-lowest focus:border-primary outline-none"></textarea>
              </div>
            </details>

            <button type="submit" class="w-full py-3.5 bg-primary-container text-on-primary hover:bg-primary rounded-xl font-label-nav text-label-nav font-bold uppercase tracking-wider inline-flex items-center justify-center gap-2 shadow-xs transition-colors">
              <span class="material-symbols-outlined text-lg">search_check</span>
              Parse & Preview Catalogue Rows
            </button>
          </form>
        </section>
      </div>

      <!-- Side Info Cards (1 col) -->
      <div class="space-y-6">
        <!-- Quick Starter Template Card -->
        <div class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-4">
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-primary text-2xl">file_present</span>
            <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">Standard Template</h3>
          </div>
          <p class="font-body-sm text-body-sm text-on-surface-variant">
            Download the pre-configured spreadsheet template containing sample products for both <strong>Lingerie</strong> and <strong>Instruments</strong> with variants and specs.
          </p>
          <a href="<?= e(url('admin/bulk_products.php?action=template')) ?>"
             class="w-full py-2.5 bg-surface-container text-on-surface hover:bg-surface-container-high rounded-xl font-label-nav text-label-nav font-bold uppercase tracking-wider text-center block transition-colors">
            Download .CSV Template
          </a>
        </div>

        <!-- Import Guidelines Card -->
        <div class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-3">
          <h3 class="font-label-nav text-label-nav uppercase tracking-wider text-secondary font-bold">Quick Guidelines</h3>
          <ul class="font-body-sm text-body-sm text-on-surface-variant space-y-2 list-disc list-inside">
            <li><strong>Required columns:</strong> <code>name</code>, <code>department</code>, <code>price</code>.</li>
            <li><strong>Department:</strong> Must be <code>lingerie</code> or <code>instruments</code>.</li>
            <li><strong>SKU:</strong> Leave empty to auto-generate unique SKUs.</li>
            <li><strong>Taxonomies:</strong> Missing categories or brands are automatically registered under the chosen department.</li>
            <li><strong>Images:</strong> Enter relative paths (e.g. <code>storage/uploads/img.jpg</code>) or valid web URLs.</li>
          </ul>
        </div>
      </div>
    </div>

  <!-- TAB CONTENT 4: BULK EXPORT & BACKUP -->
  <?php elseif ($tab === 'export'): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Export All Catalogue -->
      <section class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-5 flex flex-col justify-between">
        <div class="space-y-3">
          <div class="w-12 h-12 rounded-xl bg-primary-container/15 text-primary flex items-center justify-center">
            <span class="material-symbols-outlined text-3xl">inventory_2</span>
          </div>
          <h2 class="font-headline-md text-headline-md text-on-surface font-bold">Export Complete Catalogue</h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant">
            Export all <?= number_format($totalProducts) ?> products currently stored in your database into a single, fully structured CSV file. Ideal for bulk inventory and price updates in Excel.
          </p>
          <div class="flex items-center gap-4 text-xs font-semibold text-on-surface-variant pt-2">
            <span><?= number_format($totalLingerie) ?> Lingerie items</span>
            <span>&middot;</span>
            <span><?= number_format($totalInstruments) ?> Instruments items</span>
          </div>
        </div>

        <a href="<?= e(url('admin/bulk_products.php?action=export&csrf=' . csrf_token())) ?>"
           class="w-full py-3 bg-primary-container text-on-primary hover:bg-primary rounded-xl font-label-nav text-label-nav font-bold uppercase tracking-wider text-center inline-flex items-center justify-center gap-2 transition-colors">
          <span class="material-symbols-outlined text-lg">download</span>
          Export Full Catalogue CSV
        </a>
      </section>

      <!-- Department-Filtered Exports -->
      <section class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-5 flex flex-col justify-between">
        <div class="space-y-3">
          <div class="w-12 h-12 rounded-xl bg-secondary-fixed text-on-secondary-fixed flex items-center justify-center">
            <span class="material-symbols-outlined text-3xl">filter_alt</span>
          </div>
          <h2 class="font-headline-md text-headline-md text-on-surface font-bold">Department Specific Exports</h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant">
            Download isolated product lists filtered specifically by retail division.
          </p>
        </div>

        <div class="space-y-3">
          <a href="<?= e(url('admin/bulk_products.php?action=export&dept=lingerie&csrf=' . csrf_token())) ?>"
             class="w-full py-2.5 border border-outline-variant text-on-surface hover:border-primary hover:text-primary rounded-xl font-label-nav text-label-nav font-bold uppercase tracking-wider inline-flex items-center justify-between px-4 transition-colors">
            <span>Export Lingerie Catalogue (<?= number_format($totalLingerie) ?>)</span>
            <span class="material-symbols-outlined text-base">download</span>
          </a>
          <a href="<?= e(url('admin/bulk_products.php?action=export&dept=instruments&csrf=' . csrf_token())) ?>"
             class="w-full py-2.5 border border-outline-variant text-on-surface hover:border-primary hover:text-primary rounded-xl font-label-nav text-label-nav font-bold uppercase tracking-wider inline-flex items-center justify-between px-4 transition-colors">
            <span>Export Instruments Catalogue (<?= number_format($totalInstruments) ?>)</span>
            <span class="material-symbols-outlined text-base">download</span>
          </a>
        </div>
      </section>
    </div>

  <!-- TAB CONTENT 5: CSV SCHEMA & GUIDE -->
  <?php elseif ($tab === 'guide'): ?>
    <section class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-4 border-b border-outline-variant pb-4">
        <div>
          <h2 class="font-headline-md text-headline-md text-on-surface font-bold">Catalogue CSV Field Reference</h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
            Standard column definitions, formatting rules, and example values accepted by the bulk importer.
          </p>
        </div>
        <a href="<?= e(url('admin/bulk_products.php?action=template')) ?>"
           class="px-4 py-2 bg-primary-container text-on-primary hover:bg-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider inline-flex items-center gap-2 transition-colors">
          <span class="material-symbols-outlined text-base">download</span>
          Download Sample Template
        </a>
      </div>

      <div class="overflow-x-auto border border-outline-variant/60 rounded-xl">
        <table class="w-full text-left text-body-sm">
          <thead class="bg-surface-container-high border-b border-outline-variant text-on-surface-variant font-label-nav text-label-nav uppercase">
            <tr>
              <th class="py-3 px-4">Column Header</th>
              <th class="py-3 px-4">Requirement</th>
              <th class="py-3 px-4">Example Value</th>
              <th class="py-3 px-4">Description & Rules</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-outline-variant/40">
            <?php foreach ($schema as $key => $meta): ?>
              <tr class="hover:bg-surface-container transition-colors">
                <td class="py-3 px-4 font-mono font-bold text-primary"><?= e($key) ?></td>
                <td class="py-3 px-4">
                  <?php if ($meta['required']): ?>
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-error-container text-on-error-container">Required</span>
                  <?php else: ?>
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-surface-container text-on-surface-variant">Optional</span>
                  <?php endif; ?>
                </td>
                <td class="py-3 px-4 font-mono text-xs text-on-surface-variant max-w-[250px] truncate" title="<?= e($meta['sample']) ?>">
                  <?= e($meta['sample']) ?>
                </td>
                <td class="py-3 px-4 text-on-surface-variant text-xs leading-relaxed">
                  <?= e($meta['description']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
</div>

<script>
  // Dynamic Dropzone filename update
  const dropInput = document.getElementById('csvFileInput');
  const dropLabel = document.getElementById('dropFileName');
  if (dropInput && dropLabel) {
    dropInput.addEventListener('change', function () {
      if (this.files && this.files.length > 0) {
        dropLabel.innerHTML = 'Selected: <span class="text-primary font-bold">' + this.files[0].name + '</span> (' + (this.files[0].size / 1024).toFixed(1) + ' KB)';
      }
    });
  }
</script>
