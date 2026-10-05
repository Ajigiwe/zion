<?php
/**
 * Zion Groups of Companies - Bulk Product Import & Waiting Room Staging Center.
 *
 * Provides high-speed CSV catalogue imports, an interactive visual Waiting Room
 * to attach and upload product images before committing to the live database,
 * inline metadata editing, and catalogue exports.
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
$executionReport = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    // 1. UPLOAD CSV & ENTER WAITING ROOM
    if ($action === 'upload_to_waiting_room') {
        $csvContent = '';

        if (isset($_FILES['csv_file']) && is_array($_FILES['csv_file']) && ($_FILES['csv_file']['error'] ?? 0) === UPLOAD_ERR_OK) {
            $tmpPath = (string) $_FILES['csv_file']['tmp_name'];
            if (is_uploaded_file($tmpPath)) {
                $csvContent = (string) file_get_contents($tmpPath);
            }
        } elseif (!empty($_POST['csv_text'])) {
            $csvContent = (string) $_POST['csv_text'];
        }

        if (trim($csvContent) === '') {
            flash_set('error', 'Please select a CSV file or paste valid CSV content.');
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
        
        // Populate waiting room in session
        $_SESSION['bulk_waiting_room'] = [
            'timestamp'   => time(),
            'source_name' => $_FILES['csv_file']['name'] ?? 'Pasted Data (' . count($parsed['rows']) . ' items)',
            'items'       => $preview['results'],
        ];

        flash_set('success', sprintf('Loaded %d products into the Waiting Room. You can now add images, edit details, and commit when ready.', count($preview['results'])));
        header('Location: ' . url('admin/bulk_products.php?tab=waiting_room'));
        exit;
    }

    // 2. COMMIT WAITING ROOM TO LIVE CATALOGUE
    if ($action === 'commit_waiting_room') {
        $postedItems = (array) ($_POST['items'] ?? []);
        if ($postedItems === []) {
            flash_set('error', 'No products found in the submission.');
            header('Location: ' . url('admin/bulk_products.php?tab=waiting_room'));
            exit;
        }

        $mode          = (string) ($_POST['import_mode'] ?? 'upsert');
        $autoTaxonomy  = !empty($_POST['auto_taxonomy']);

        // Convert posted rows into raw associative array for validation & execution
        $existingMap = bulk_get_existing_skus_map();
        $validatedList = [];
        $rawRows = [];

        foreach ($postedItems as $idx => $item) {
            $rowNum = $idx + 1;
            $rawRow = [
                '_row_num'          => (string) $rowNum,
                'sku'               => trim((string) ($item['sku'] ?? '')),
                'name'              => trim((string) ($item['name'] ?? '')),
                'department'        => trim((string) ($item['department'] ?? 'lingerie')),
                'category'          => trim((string) ($item['category'] ?? '')),
                'brand'             => trim((string) ($item['brand'] ?? '')),
                'price'             => trim((string) ($item['price'] ?? '0')),
                'compare_at_price'  => trim((string) ($item['compare_at_price'] ?? '')),
                'bundle_price'      => trim((string) ($item['bundle_price'] ?? '')),
                'stock'             => trim((string) ($item['stock'] ?? '0')),
                'stock_label'       => trim((string) ($item['stock_label'] ?? '')),
                'badge'             => trim((string) ($item['badge'] ?? '')),
                'short_description' => trim((string) ($item['short_description'] ?? '')),
                'description'       => trim((string) ($item['description'] ?? '')),
                'image_url'         => trim((string) ($item['image_url'] ?? '')),
                'gallery_images'    => trim((string) ($item['gallery_images'] ?? '')),
                'is_active'         => isset($item['is_active']) ? '1' : '0',
                'is_featured'       => isset($item['is_featured']) ? '1' : '0',
                'variants'          => trim((string) ($item['variants'] ?? '')),
                'specs'             => trim((string) ($item['specs'] ?? '')),
                'bundles'           => trim((string) ($item['bundles'] ?? '')),
            ];

            $val = bulk_validate_row($rawRow, $existingMap);
            $validatedList[] = $val;
            $rawRows[] = $rawRow;
        }

        $result = bulk_execute_import($validatedList, [
            'mode'          => $mode,
            'auto_taxonomy' => $autoTaxonomy,
        ]);

        if ($result['failed'] > 0 && $result['errors'] !== []) {
            $_SESSION['bulk_last_error_csv'] = bulk_generate_error_report_csv($result['errors'], $rawRows);
        } else {
            unset($_SESSION['bulk_last_error_csv']);
        }

        $_SESSION['bulk_last_result'] = $result;
        unset($_SESSION['bulk_waiting_room']);

        header('Location: ' . url('admin/bulk_products.php?tab=result'));
        exit;
    }

    // 3. DISCARD WAITING ROOM
    if ($action === 'clear_waiting_room') {
        unset($_SESSION['bulk_waiting_room']);
        flash_set('info', 'Waiting Room cleared.');
        header('Location: ' . url('admin/bulk_products.php?tab=import'));
        exit;
    }
}

// Retrieve session states
$waitingRoom = $_SESSION['bulk_waiting_room'] ?? null;
if ($tab === 'result' && isset($_SESSION['bulk_last_result'])) {
    $executionReport = $_SESSION['bulk_last_result'];
}

// Auto-redirect to waiting room tab if waiting room has items and user opened default tab
if ($tab === 'import' && $waitingRoom && !empty($waitingRoom['items'])) {
    $tab = 'waiting_room';
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
            Upload CSV catalogues, attach product images in the Waiting Room, and publish with complete control.
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
       class="pb-3 font-label-nav text-label-nav uppercase tracking-wider font-bold border-b-2 transition-colors inline-flex items-center gap-2 <?= ($tab === 'import') ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface' ?>">
      <span class="material-symbols-outlined text-lg">upload_file</span>
      Upload CSV
    </a>

    <?php if ($waitingRoom && !empty($waitingRoom['items'])): ?>
      <a href="<?= e(url('admin/bulk_products.php?tab=waiting_room')) ?>"
         class="pb-3 font-label-nav text-label-nav uppercase tracking-wider font-bold border-b-2 transition-colors inline-flex items-center gap-2 <?= ($tab === 'waiting_room') ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface' ?>">
        <span class="material-symbols-outlined text-lg">hourglass_top</span>
        Waiting Room
        <span class="px-2 py-0.5 rounded-full bg-primary text-on-primary text-xs font-mono font-bold"><?= count($waitingRoom['items']) ?></span>
      </a>
    <?php endif; ?>

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

  <!-- TAB CONTENT 2: LIVE WAITING ROOM (STAGING AREA WITH IMAGE ATTACHMENT) -->
  <?php elseif ($tab === 'waiting_room' && $waitingRoom && !empty($waitingRoom['items'])): ?>
    <?php
    $items = $waitingRoom['items'];
    $withImg = 0;
    $noImg   = 0;
    $lingerieCount = 0;
    $instCount     = 0;

    foreach ($items as $it) {
        $hasPhoto = !empty($it['data']['image_url']);
        if ($hasPhoto) $withImg++; else $noImg++;
        if (($it['data']['department'] ?? '') === 'lingerie') $lingerieCount++; else $instCount++;
    }
    ?>

    <section class="space-y-6">
      <!-- Waiting Room Top Banner & Filter Controls -->
      <div class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <span class="px-2.5 py-0.5 rounded-full bg-primary-container text-on-primary font-label-tag text-label-tag font-bold uppercase tracking-wider">
                Waiting Room
              </span>
              <span class="font-body-sm text-body-sm text-on-surface-variant font-medium">Source: <?= e((string) $waitingRoom['source_name']) ?></span>
            </div>
            <h2 class="font-headline-md text-headline-md text-on-surface font-bold mt-1">Attach Images &amp; Polish Catalogue Items</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
              Upload photos directly onto any product card, edit details in place, and commit only when you're 100% satisfied.
            </p>
          </div>

          <form method="post" action="<?= e(url('admin/bulk_products.php')) ?>" onsubmit="return confirm('Clear the waiting room and discard uncommitted items?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear_waiting_room" />
            <button type="submit" class="px-4 py-2 border border-outline-variant text-error hover:bg-error-container/20 rounded-lg font-label-nav text-label-nav uppercase font-bold transition-colors">
              Clear Waiting Room
            </button>
          </form>
        </div>

        <!-- Filter Bar & Search -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-outline-variant">
          <div class="flex flex-wrap items-center gap-2" id="waitingFilters">
            <button type="button" data-filter="all" class="filter-btn active px-3.5 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-primary text-on-primary transition-colors">
              All (<span id="countAll"><?= count($items) ?></span>)
            </button>
            <button type="button" data-filter="missing_img" class="filter-btn px-3.5 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-surface-container hover:bg-surface-container-high text-on-surface transition-colors">
              Needs Photo (<span id="countNoImg"><?= $noImg ?></span>)
            </button>
            <button type="button" data-filter="has_img" class="filter-btn px-3.5 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-surface-container hover:bg-surface-container-high text-on-surface transition-colors">
              Has Photo (<span id="countWithImg"><?= $withImg ?></span>)
            </button>
            <button type="button" data-filter="lingerie" class="filter-btn px-3.5 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-surface-container hover:bg-surface-container-high text-on-surface transition-colors">
              Lingerie (<?= $lingerieCount ?>)
            </button>
            <button type="button" data-filter="instruments" class="filter-btn px-3.5 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider bg-surface-container hover:bg-surface-container-high text-on-surface transition-colors">
              Instruments (<?= $instCount ?>)
            </button>
          </div>

          <div class="relative w-72">
            <span class="material-symbols-outlined absolute left-3 top-2.5 text-on-surface-variant text-lg">search</span>
            <input type="search" id="waitingSearch" placeholder="Search staged items..." class="w-full h-10 pl-9 pr-3 border border-outline-variant rounded-lg font-body-sm text-body-sm bg-surface-container-lowest focus:border-primary outline-none" />
          </div>
        </div>
      </div>

      <!-- Staged Items Form List -->
      <form id="waitingRoomForm" method="post" action="<?= e(url('admin/bulk_products.php')) ?>" class="space-y-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="commit_waiting_room" />

        <div class="space-y-4" id="waitingItemsContainer">
          <?php foreach ($items as $idx => $item): ?>
            <?php
            $d = $item['data'];
            $img = trim((string) $d['image_url']);
            $hasImg = $img !== '';
            $galleryStr = implode(' | ', $d['gallery_images'] ?? []);
            ?>
            <div class="staged-card bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-5 transition-all hover:border-primary/50"
                 data-dept="<?= e($d['department']) ?>"
                 data-has-img="<?= $hasImg ? '1' : '0' ?>"
                 data-name="<?= e(strtolower($d['name'])) ?>"
                 data-sku="<?= e(strtolower($d['sku'])) ?>">

              <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- 1. Interactive Image Upload Zone (3 cols) -->
                <div class="lg:col-span-3 space-y-2">
                  <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider block font-bold">Product Image</span>
                  
                  <div class="image-dropzone relative border-2 border-dashed border-outline-variant rounded-xl p-3 text-center bg-surface-container/40 hover:bg-surface-container transition-colors group cursor-pointer"
                       data-item-index="<?= $idx ?>">
                    
                    <!-- Preview Image -->
                    <div class="img-preview-box w-full h-40 rounded-lg overflow-hidden bg-surface-container-high flex items-center justify-center mb-2 relative <?= $hasImg ? '' : 'hidden' ?>">
                      <img class="w-full h-full object-cover preview-img" src="<?= $hasImg ? e(img_url($img)) : '' ?>" alt="" />
                      <button type="button" class="btn-remove-img absolute top-2 right-2 w-7 h-7 rounded-full bg-black/70 text-white flex items-center justify-center hover:bg-error transition-colors" title="Remove photo">
                        <span class="material-symbols-outlined text-sm">close</span>
                      </button>
                    </div>

                    <!-- Placeholder when no image -->
                    <div class="img-placeholder-box py-6 space-y-2 <?= $hasImg ? 'hidden' : '' ?>">
                      <span class="material-symbols-outlined text-3xl text-outline-variant group-hover:text-primary transition-colors">add_photo_alternate</span>
                      <div class="text-xs font-semibold text-on-surface-variant block">
                        Drop photo or <span class="text-primary underline">browse</span>
                      </div>
                      <span class="text-[10px] text-outline block">JPG, PNG, WebP up to 8MB</span>
                    </div>

                    <!-- File input -->
                    <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="file-uploader absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                    
                    <!-- Upload status overlay -->
                    <div class="upload-spinner absolute inset-0 bg-black/60 rounded-xl flex items-center justify-center text-white text-xs font-bold gap-2 hidden">
                      <span class="material-symbols-outlined animate-spin text-lg">progress_activity</span> Uploading...
                    </div>
                  </div>

                  <!-- Hidden and manual image input -->
                  <input type="hidden" name="items[<?= $idx ?>][image_url]" value="<?= e($img) ?>" class="input-image-url" />
                  
                  <details class="text-xs group">
                    <summary class="text-outline hover:text-primary cursor-pointer font-medium select-none">Or paste external URL</summary>
                    <input type="text" placeholder="https://..." value="<?= e($img) ?>"
                           class="w-full mt-1.5 h-8 px-2 border border-outline-variant rounded font-mono text-[11px] bg-surface-container-lowest focus:border-primary outline-none input-manual-url" />
                  </details>
                </div>

                <!-- 2. Product Details & Taxonomy (7 cols) -->
                <div class="lg:col-span-7 space-y-4">
                  <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold uppercase bg-surface-container-high text-on-surface-variant">
                      Item #<?= $idx + 1 ?>
                    </span>
                    <span class="photo-badge px-2 py-0.5 rounded text-[11px] font-bold uppercase <?= $hasImg ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-surface-container text-outline' ?>">
                      <?= $hasImg ? 'Photo Attached' : 'No Photo' ?>
                    </span>
                  </div>

                  <!-- Product Name & Department -->
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                      <label class="block font-label-nav text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-1">Product Title *</label>
                      <input type="text" name="items[<?= $idx ?>][name]" value="<?= e($d['name']) ?>" required
                             class="w-full h-10 px-3 border border-outline-variant rounded-lg font-body-sm text-body-sm font-semibold text-on-surface bg-surface-container-lowest focus:border-primary outline-none" />
                    </div>
                    <div>
                      <label class="block font-label-nav text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-1">Department *</label>
                      <select name="items[<?= $idx ?>][department]" class="w-full h-10 px-3 border border-outline-variant rounded-lg font-body-sm text-body-sm bg-surface-container-lowest focus:border-primary outline-none">
                        <option value="lingerie" <?= ($d['department'] === 'lingerie') ? 'selected' : '' ?>>Lingerie</option>
                        <option value="instruments" <?= ($d['department'] === 'instruments') ? 'selected' : '' ?>>Instruments</option>
                      </select>
                    </div>
                  </div>

                  <!-- Price, Compare At, Stock & SKU -->
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                      <label class="block font-label-nav text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-1">Price (GH&#8373;) *</label>
                      <input type="number" step="0.01" min="0.01" name="items[<?= $idx ?>][price]" value="<?= e((string) $d['price']) ?>" required
                             class="w-full h-10 px-3 border border-outline-variant rounded-lg font-mono text-sm font-bold text-on-surface bg-surface-container-lowest focus:border-primary outline-none" />
                    </div>
                    <div>
                      <label class="block font-label-nav text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-1">MSRP / Compare</label>
                      <input type="number" step="0.01" min="0" name="items[<?= $idx ?>][compare_at_price]" value="<?= e((string) $d['compare_at_price']) ?>"
                             class="w-full h-10 px-3 border border-outline-variant rounded-lg font-mono text-sm text-on-surface-variant bg-surface-container-lowest focus:border-primary outline-none" />
                    </div>
                    <div>
                      <label class="block font-label-nav text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-1">Stock Units</label>
                      <input type="number" min="0" name="items[<?= $idx ?>][stock]" value="<?= e((string) $d['stock']) ?>"
                             class="w-full h-10 px-3 border border-outline-variant rounded-lg font-mono text-sm text-on-surface bg-surface-container-lowest focus:border-primary outline-none" />
                    </div>
                    <div>
                      <label class="block font-label-nav text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-1">SKU</label>
                      <input type="text" name="items[<?= $idx ?>][sku]" value="<?= e($d['sku']) ?>" placeholder="Auto-generate"
                             class="w-full h-10 px-3 border border-outline-variant rounded-lg font-mono text-xs text-on-surface bg-surface-container-lowest focus:border-primary outline-none" />
                    </div>
                  </div>

                  <!-- Category, Brand & Badge -->
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                      <label class="block font-label-nav text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-1">Category</label>
                      <input type="text" name="items[<?= $idx ?>][category]" value="<?= e($d['category']) ?>" placeholder="e.g. Bras, Guitars"
                             class="w-full h-9 px-3 border border-outline-variant rounded-lg font-body-sm text-xs bg-surface-container-lowest focus:border-primary outline-none" />
                    </div>
                    <div>
                      <label class="block font-label-nav text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-1">Brand</label>
                      <input type="text" name="items[<?= $idx ?>][brand]" value="<?= e($d['brand']) ?>" placeholder="e.g. Velora, Yamaha"
                             class="w-full h-9 px-3 border border-outline-variant rounded-lg font-body-sm text-xs bg-surface-container-lowest focus:border-primary outline-none" />
                    </div>
                    <div>
                      <label class="block font-label-nav text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-1">Badge</label>
                      <input type="text" name="items[<?= $idx ?>][badge]" value="<?= e($d['badge']) ?>" placeholder="e.g. Bestseller, New"
                             class="w-full h-9 px-3 border border-outline-variant rounded-lg font-body-sm text-xs bg-surface-container-lowest focus:border-primary outline-none" />
                    </div>
                  </div>

                  <!-- Optional descriptions & attributes collapsible -->
                  <details class="text-xs group pt-1">
                    <summary class="text-primary font-semibold cursor-pointer select-none inline-flex items-center gap-1">
                      <span>More details (Gallery images, Short description, Variants &amp; Specs)</span>
                      <span class="material-symbols-outlined text-sm group-open:rotate-180 transition-transform">expand_more</span>
                    </summary>
                    <div class="pt-3 space-y-3 bg-surface-container/50 p-3 rounded-xl mt-2 border border-outline-variant/40">
                      <div>
                        <label class="block font-label-nav text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-1">Additional Gallery Images (| separated)</label>
                        <input type="text" name="items[<?= $idx ?>][gallery_images]" value="<?= e($galleryStr) ?>" placeholder="img2.jpg | img3.jpg"
                               class="w-full h-9 px-3 border border-outline-variant rounded-lg font-mono text-xs bg-surface-container-lowest focus:border-primary outline-none" />
                      </div>
                      <div>
                        <label class="block font-label-nav text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-1">Short Description</label>
                        <input type="text" name="items[<?= $idx ?>][short_description]" value="<?= e($d['short_description']) ?>"
                               class="w-full h-9 px-3 border border-outline-variant rounded-lg text-xs bg-surface-container-lowest focus:border-primary outline-none" />
                      </div>
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                          <label class="block font-label-nav text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-1">Variants (type:val:stock)</label>
                          <input type="text" name="items[<?= $idx ?>][variants]" value="<?= e(implode(' | ', array_map(static fn($v) => $v['type'] . ':' . $v['value'] . ':' . $v['stock'] . (!empty($v['hex']) ? ':' . $v['hex'] : ''), $d['variants'] ?? []))) ?>"
                                 class="w-full h-9 px-3 border border-outline-variant rounded-lg font-mono text-xs bg-surface-container-lowest focus:border-primary outline-none" />
                        </div>
                        <div>
                          <label class="block font-label-nav text-[11px] uppercase tracking-wider text-on-surface-variant font-bold mb-1">Specs (Label:Value)</label>
                          <input type="text" name="items[<?= $idx ?>][specs]" value="<?= e(implode(' | ', array_map(static fn($s) => $s['label'] . ':' . $s['value'], $d['specs'] ?? []))) ?>"
                                 class="w-full h-9 px-3 border border-outline-variant rounded-lg font-mono text-xs bg-surface-container-lowest focus:border-primary outline-none" />
                        </div>
                      </div>
                    </div>
                  </details>
                </div>

                <!-- 3. Card Action Column (2 cols) -->
                <div class="lg:col-span-2 flex flex-col justify-between items-end h-full gap-4 pt-1">
                  <button type="button" class="btn-delete-card p-2 text-on-surface-variant hover:text-error hover:bg-error-container/20 rounded-lg transition-colors" title="Remove item from waiting room">
                    <span class="material-symbols-outlined text-lg">delete</span>
                  </button>

                  <div class="space-y-2 w-full text-right">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                      <input type="checkbox" name="items[<?= $idx ?>][is_active]" value="1" <?= $d['is_active'] ? 'checked' : '' ?> class="w-4 h-4 text-primary rounded border-outline-variant focus:ring-primary" />
                      <span class="text-xs font-semibold text-on-surface">Publish</span>
                    </label>
                  </div>
                </div>

              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Sticky Floating Publishing Footer -->
        <div class="sticky bottom-4 bg-surface-container-lowest/95 backdrop-blur border border-outline-variant rounded-2xl p-4 shadow-xl flex flex-wrap items-center justify-between gap-4">
          <div class="flex items-center gap-4">
            <div>
              <span class="font-headline-sm text-headline-sm font-bold text-on-surface block">
                <span id="stagedCount"><?= count($items) ?></span> Products Ready
              </span>
              <span class="text-xs text-on-surface-variant">
                Strategy: Upsert (updates matching SKUs, registers new items).
              </span>
            </div>
            <input type="hidden" name="import_mode" value="upsert" />
            <input type="hidden" name="auto_taxonomy" value="1" />
          </div>

          <div class="flex items-center gap-3">
            <button type="submit" class="px-6 py-3 bg-primary-container text-on-primary hover:bg-primary rounded-xl font-label-nav text-label-nav font-bold uppercase tracking-wider inline-flex items-center gap-2 shadow-xs transition-colors">
              <span class="material-symbols-outlined text-lg">publish</span>
              Commit &amp; Publish to Live Catalogue
            </button>
          </div>
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
            <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Step 1</span>
            <h2 class="font-headline-md text-headline-md text-on-surface font-bold">Upload CSV to Waiting Room</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
              Upload your product list. The items will be loaded into the <strong>Waiting Room</strong> where you can visually inspect them, attach images to each item, and publish when ready.
            </p>
          </div>

          <form method="post" enctype="multipart/form-data" action="<?= e(url('admin/bulk_products.php')) ?>" class="space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload_to_waiting_room" />

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
              <span class="material-symbols-outlined text-lg">meeting_room</span>
              Enter Waiting Room
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
          <h3 class="font-label-nav text-label-nav uppercase tracking-wider text-secondary font-bold">How Waiting Room Works</h3>
          <ul class="font-body-sm text-body-sm text-on-surface-variant space-y-2 list-disc list-inside">
            <li><strong>No Automatic Commits:</strong> Uploading the file creates a staging space first.</li>
            <li><strong>Add Images Easily:</strong> Drag or pick images for each product card right in the browser.</li>
            <li><strong>Edit Before Live:</strong> Fix titles, adjust prices, change categories before pushing live.</li>
            <li><strong>Publish at Once:</strong> When done, click Commit to save all products and linked images to the database.</li>
          </ul>
        </div>
      </div>
    </div>

  <!-- TAB CONTENT 4: BULK EXPORT & BACKUP -->
  <?php elseif ($tab === 'export'): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <section class="bg-surface-container-lowest rounded-2xl shadow-xs border border-outline-variant/60 p-6 space-y-5 flex flex-col justify-between">
        <div class="space-y-3">
          <div class="w-12 h-12 rounded-xl bg-primary-container/15 text-primary flex items-center justify-center">
            <span class="material-symbols-outlined text-3xl">inventory_2</span>
          </div>
          <h2 class="font-headline-md text-headline-md text-on-surface font-bold">Export Complete Catalogue</h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant">
            Export all <?= number_format($totalProducts) ?> products currently stored in your database into a single, fully structured CSV file.
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
             class="w-full py-2.5 border border-outline-variant text-on-surface hover:border-primary hover:text-primary rounded-xl font-label-nav text-label-nav font-bold uppercase tracking-wider inline-flex items-between px-4 transition-colors">
            <span>Export Lingerie Catalogue (<?= number_format($totalLingerie) ?>)</span>
            <span class="material-symbols-outlined text-base">download</span>
          </a>
          <a href="<?= e(url('admin/bulk_products.php?action=export&dept=instruments&csrf=' . csrf_token())) ?>"
             class="w-full py-2.5 border border-outline-variant text-on-surface hover:border-primary hover:text-primary rounded-xl font-label-nav text-label-nav font-bold uppercase tracking-wider inline-flex items-between px-4 transition-colors">
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

  // Waiting Room Interactive Logic
  document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = '<?= e(csrf_token()) ?>';
    const uploadUrl = '<?= e(url('admin/upload.php')) ?>';

    // 1. AJAX Image Upload on each Staged Card
    document.querySelectorAll('.image-dropzone').forEach(function (zone) {
      const fileInput    = zone.querySelector('.file-uploader');
      const hiddenInput  = zone.closest('.staged-card').querySelector('.input-image-url');
      const manualInput  = zone.closest('.staged-card').querySelector('.input-manual-url');
      const previewBox   = zone.querySelector('.img-preview-box');
      const previewImg   = zone.querySelector('.preview-img');
      const placeBox     = zone.querySelector('.img-placeholder-box');
      const spinner      = zone.querySelector('.upload-spinner');
      const removeBtn    = zone.querySelector('.btn-remove-img');
      const card         = zone.closest('.staged-card');
      const photoBadge   = card.querySelector('.photo-badge');

      if (!fileInput) return;

      fileInput.addEventListener('change', function () {
        if (!this.files || !this.files[0]) return;
        const file = this.files[0];

        spinner.classList.remove('hidden');

        const formData = new FormData();
        formData.append('file', file);
        formData.append('csrf', csrfToken);

        fetch(uploadUrl, {
          method: 'POST',
          body: formData,
          headers: { 'Accept': 'application/json' }
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          spinner.classList.add('hidden');
          if (data && data.ok) {
            hiddenInput.value = data.path;
            if (manualInput) manualInput.value = data.path;
            previewImg.src = data.url;
            previewBox.classList.remove('hidden');
            placeBox.classList.add('hidden');
            card.setAttribute('data-has-img', '1');
            if (photoBadge) {
              photoBadge.textContent = 'Photo Attached';
              photoBadge.className = 'photo-badge px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-secondary-fixed text-on-secondary-fixed';
            }
            updateCounters();
          } else {
            alert('Upload failed: ' + (data.error || 'Server error'));
          }
        })
        .catch(function (err) {
          spinner.classList.add('hidden');
          alert('Upload failed: ' + err.message);
        });
      });

      // Remove photo button
      if (removeBtn) {
        removeBtn.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          hiddenInput.value = '';
          if (manualInput) manualInput.value = '';
          previewImg.src = '';
          previewBox.classList.add('hidden');
          placeBox.classList.remove('hidden');
          card.setAttribute('data-has-img', '0');
          if (photoBadge) {
            photoBadge.textContent = 'No Photo';
            photoBadge.className = 'photo-badge px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-surface-container text-outline';
          }
          updateCounters();
        });
      }

      // Manual URL input sync
      if (manualInput) {
        manualInput.addEventListener('input', function () {
          const val = this.value.trim();
          hiddenInput.value = val;
          if (val !== '') {
            previewImg.src = val.startsWith('http') || val.startsWith('/') ? val : '<?= e(url('')) ?>/' + val;
            previewBox.classList.remove('hidden');
            placeBox.classList.add('hidden');
            card.setAttribute('data-has-img', '1');
            if (photoBadge) {
              photoBadge.textContent = 'Photo Attached';
              photoBadge.className = 'photo-badge px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-secondary-fixed text-on-secondary-fixed';
            }
          } else {
            previewBox.classList.add('hidden');
            placeBox.classList.remove('hidden');
            card.setAttribute('data-has-img', '0');
            if (photoBadge) {
              photoBadge.textContent = 'No Photo';
              photoBadge.className = 'photo-badge px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-surface-container text-outline';
            }
          }
          updateCounters();
        });
      }
    });

    // 2. Delete Card from Waiting Room
    document.querySelectorAll('.btn-delete-card').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const card = this.closest('.staged-card');
        if (card && confirm('Remove this product from the waiting room?')) {
          card.remove();
          updateCounters();
        }
      });
    });

    // 3. Update Counters & Metrics
    function updateCounters() {
      const allCards = document.querySelectorAll('.staged-card');
      let withImg = 0;
      let noImg = 0;
      allCards.forEach(function (c) {
        if (c.getAttribute('data-has-img') === '1') withImg++; else noImg++;
      });

      const countAll = document.getElementById('countAll');
      const countWith = document.getElementById('countWithImg');
      const countNo = document.getElementById('countNoImg');
      const stagedCount = document.getElementById('stagedCount');

      if (countAll) countAll.textContent = allCards.length;
      if (countWith) countWith.textContent = withImg;
      if (countNo) countNo.textContent = noImg;
      if (stagedCount) stagedCount.textContent = allCards.length;
    }

    // 4. Filtering in Waiting Room
    const filterButtons = document.querySelectorAll('#waitingFilters .filter-btn');
    const searchInput   = document.getElementById('waitingSearch');

    function applyFilters() {
      const activeBtn = document.querySelector('#waitingFilters .filter-btn.active');
      const filterMode = activeBtn ? activeBtn.getAttribute('data-filter') : 'all';
      const searchVal  = searchInput ? searchInput.value.toLowerCase().trim() : '';

      document.querySelectorAll('.staged-card').forEach(function (card) {
        const hasImg = card.getAttribute('data-has-img') === '1';
        const dept   = card.getAttribute('data-dept');
        const name   = card.getAttribute('data-name') || '';
        const sku    = card.getAttribute('data-sku') || '';

        let matchFilter = true;
        if (filterMode === 'missing_img') matchFilter = !hasImg;
        else if (filterMode === 'has_img') matchFilter = hasImg;
        else if (filterMode === 'lingerie') matchFilter = (dept === 'lingerie');
        else if (filterMode === 'instruments') matchFilter = (dept === 'instruments');

        let matchSearch = true;
        if (searchVal !== '') {
          matchSearch = name.includes(searchVal) || sku.includes(searchVal);
        }

        if (matchFilter && matchSearch) {
          card.classList.remove('hidden');
        } else {
          card.classList.add('hidden');
        }
      });
    }

    filterButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        filterButtons.forEach(function (b) {
          b.classList.remove('active', 'bg-primary', 'text-on-primary');
          b.classList.add('bg-surface-container', 'text-on-surface');
        });
        this.classList.add('active', 'bg-primary', 'text-on-primary');
        this.classList.remove('bg-surface-container', 'text-on-surface');
        applyFilters();
      });
    });

    if (searchInput) {
      searchInput.addEventListener('input', applyFilters);
    }
  });
</script>
