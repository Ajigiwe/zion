<?php
/**
 * Zion Groups of Companies - Bulk Product Import & Export Service.
 *
 * Provides high-performance, robust CSV parsing, validation dry-runs,
 * transactional batch upserts, taxonomy auto-linking, variants/specs handling,
 * and standard catalogue export.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin/_slugs.php';

/**
 * Returns all recognized CSV headers with human metadata and validation rules.
 *
 * @return array<string, array{label: string, required: bool, sample: string, description: string}>
 */
function bulk_product_schema(): array
{
    return [
        'sku' => [
            'label'       => 'SKU',
            'required'    => false,
            'sample'      => 'VL-LI-00101',
            'description' => 'Unique product code. If blank on creation, auto-generated (VL-LI-XXXXX / VL-IN-XXXXX). Used to match existing items on update.',
        ],
        'name' => [
            'label'       => 'Product Name',
            'required'    => true,
            'sample'      => 'Aura Satin & Lace Bodysuit',
            'description' => 'Display title of the product.',
        ],
        'department' => [
            'label'       => 'Department',
            'required'    => true,
            'sample'      => 'lingerie',
            'description' => 'Store department: must be "lingerie" or "instruments".',
        ],
        'category' => [
            'label'       => 'Category',
            'required'    => false,
            'sample'      => 'Bodysuits',
            'description' => 'Category name or slug. Auto-matched or auto-created under the specified department.',
        ],
        'brand' => [
            'label'       => 'Brand',
            'required'    => false,
            'sample'      => 'Velora Atelier',
            'description' => 'Brand name or manufacturer. Auto-matched or auto-created.',
        ],
        'price' => [
            'label'       => 'Price',
            'required'    => true,
            'sample'      => '89.00',
            'description' => 'Regular selling price in standard decimal format (e.g. 89.00 or 89).',
        ],
        'compare_at_price' => [
            'label'       => 'Compare At Price',
            'required'    => false,
            'sample'      => '120.00',
            'description' => 'Original/MSRP strike-through price for sale discount display.',
        ],
        'bundle_price' => [
            'label'       => 'Bundle Price',
            'required'    => false,
            'sample'      => '150.00',
            'description' => 'Special discounted bundle package price if bundles are configured.',
        ],
        'stock' => [
            'label'       => 'Stock Quantity',
            'required'    => false,
            'sample'      => '45',
            'description' => 'Available inventory units (default: 0).',
        ],
        'stock_label' => [
            'label'       => 'Stock Status Label',
            'required'    => false,
            'sample'      => 'In Stock',
            'description' => 'Custom storefront badge (e.g., "In Stock", "Made to Order", "Only 3 left").',
        ],
        'badge' => [
            'label'       => 'Product Badge',
            'required'    => false,
            'sample'      => 'Bestseller',
            'description' => 'Ribbon badge text (e.g. "New", "Bestseller", "20% OFF", "Limited").',
        ],
        'short_description' => [
            'label'       => 'Short Description',
            'required'    => false,
            'sample'      => 'Luxurious floral stretch lace with contouring satin paneling.',
            'description' => 'Brief overview displayed in listings and product summary.',
        ],
        'description' => [
            'label'       => 'Full Description',
            'required'    => false,
            'sample'      => 'Crafted from Italian stretch satin and delicate corded lace...',
            'description' => 'Comprehensive product details, features, and care guidelines.',
        ],
        'image_url' => [
            'label'       => 'Main Image URL/Path',
            'required'    => false,
            'sample'      => 'storage/uploads/products/aura-bodysuit-1.jpg',
            'description' => 'Primary hero image path (relative storage/uploads/... or full HTTPS URL).',
        ],
        'gallery_images' => [
            'label'       => 'Additional Gallery Images',
            'required'    => false,
            'sample'      => 'storage/uploads/products/aura-2.jpg | storage/uploads/products/aura-3.jpg',
            'description' => 'Multiple image URLs separated by pipe (|) or comma (,).',
        ],
        'is_active' => [
            'label'       => 'Active / Published',
            'required'    => false,
            'sample'      => '1',
            'description' => '1 / yes / true to publish to catalogue; 0 / no / false for draft.',
        ],
        'is_featured' => [
            'label'       => 'Featured',
            'required'    => false,
            'sample'      => '1',
            'description' => '1 / yes / true to feature on department homepages.',
        ],
        'variants' => [
            'label'       => 'Variants',
            'required'    => false,
            'sample'      => 'size:S:10 | size:M:20 | size:L:15 | color:Midnight Black:#0a0a0a:45',
            'description' => 'Variants in format: type:value:stock[:hex_code] separated by pipe (|). Example: size:S:10 | color:Red:#ff0000:15',
        ],
        'specs' => [
            'label'       => 'Specifications',
            'required'    => false,
            'sample'      => 'Material:90% Silk, 10% Elastane | Lining:100% Cotton | Origin:Italy',
            'description' => 'Key-value specifications separated by pipe (|). Format: Label:Value',
        ],
        'bundles' => [
            'label'       => 'Bundle Items',
            'required'    => false,
            'sample'      => 'Matching Silk Robe:Coordinating drape robe:45.00 | Garment Bag:Travel protection bag:15.00',
            'description' => 'Bundle package items. Format: ItemName:Description:Price separated by pipe (|).',
        ],
    ];
}

/**
 * Detect delimiter, strip UTF-8 BOM, and parse CSV content into raw header/row array.
 *
 * @param string $content Raw CSV text.
 * @return array{headers: string[], rows: array<int, array<string, string>>, error?: string}
 */
function bulk_parse_csv_string(string $content): array
{
    // Remove UTF-8 BOM if present
    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $content = substr($content, 3);
    }
    $content = trim($content);
    if ($content === '') {
        return ['headers' => [], 'rows' => [], 'error' => 'The uploaded CSV file is empty.'];
    }

    // Delimiter detection from first line
    $firstLine = strtok($content, "\r\n") ?: '';
    $delimiters = [',', ';', "\t", '|'];
    $chosenDelimiter = ',';
    $maxCount = 0;
    foreach ($delimiters as $delim) {
        $count = substr_count($firstLine, $delim);
        if ($count > $maxCount) {
            $maxCount = $count;
            $chosenDelimiter = $delim;
        }
    }

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $content);
    rewind($stream);

    $rawHeader = fgetcsv($stream, 0, $chosenDelimiter);
    if ($rawHeader === false || $rawHeader === [null] || $rawHeader === []) {
        fclose($stream);
        return ['headers' => [], 'rows' => [], 'error' => 'Could not read header row from CSV.'];
    }

    // Normalize header keys: trim, lowercase, remove whitespace/punctuation variations
    $normalizedHeaders = [];
    foreach ($rawHeader as $idx => $h) {
        $clean = strtolower(trim((string) $h));
        $clean = preg_replace('/[\s\-_]+/', '_', $clean) ?? $clean;
        $clean = trim($clean, '_');
        
        // Map common aliases
        $clean = match ($clean) {
            'product_name', 'title', 'item_name' => 'name',
            'dept', 'division' => 'department',
            'cat', 'category_name' => 'category',
            'brand_name', 'manufacturer' => 'brand',
            'sale_price', 'regular_price', 'unit_price' => 'price',
            'compare_price', 'msrp', 'original_price', 'rrp' => 'compare_at_price',
            'qty', 'quantity', 'inventory', 'stock_quantity' => 'stock',
            'image', 'thumbnail', 'photo', 'main_image' => 'image_url',
            'images', 'gallery', 'additional_images' => 'gallery_images',
            'active', 'published', 'status' => 'is_active',
            'featured' => 'is_featured',
            'short_desc', 'short_description', 'excerpt', 'summary', 'brief', 'short' => 'short_description',
            'desc', 'description', 'full_desc', 'full_description', 'product_desc', 'product_description', 'long_description', 'long_desc', 'body', 'details', 'content', 'about' => 'description',
            default => $clean,
        };
        $normalizedHeaders[$idx] = $clean;
    }

    $rows = [];
    $lineNum = 1;
    while (($row = fgetcsv($stream, 0, $chosenDelimiter)) !== false) {
        $lineNum++;
        if ($row === [null] || empty(array_filter($row, static fn($v) => trim((string) $v) !== ''))) {
            continue; // skip blank line
        }

        $assoc = [];
        foreach ($normalizedHeaders as $idx => $colName) {
            if ($colName === '') {
                continue;
            }
            $assoc[$colName] = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
        }
        $assoc['_row_num'] = (string) $lineNum;
        $rows[] = $assoc;
    }
    fclose($stream);

    return [
        'headers' => array_values(array_filter($normalizedHeaders)),
        'rows'    => $rows,
    ];
}

/**
 * Helper to parse boolean inputs (1/0, yes/no, true/false).
 */
function bulk_parse_bool(mixed $value, int $default = 0): int
{
    if ($value === null || $value === '') {
        return $default;
    }
    $v = strtolower(trim((string) $value));
    if (in_array($v, ['1', 'true', 'yes', 'y', 'active', 'published', 'enabled', 'on'], true)) {
        return 1;
    }
    if (in_array($v, ['0', 'false', 'no', 'n', 'draft', 'inactive', 'disabled', 'off'], true)) {
        return 0;
    }
    return $default;
}

/**
 * Validate and sanitize a single product record from CSV.
 *
 * @param array<string, string> $row Raw row associative array.
 * @param array<string, int> $existingSkus Cached lookup of SKU => product_id.
 * @return array{
 *   valid: bool,
 *   action: 'insert'|'update'|'error',
 *   product_id: int|null,
 *   errors: string[],
 *   warnings: string[],
 *   data: array<string, mixed>
 * }
 */
function bulk_validate_row(array $row, array $existingSkus = []): array
{
    $errors   = [];
    $warnings = [];

    $rowNum = (int) ($row['_row_num'] ?? 0);
    $sku    = trim($row['sku'] ?? '');
    $name   = trim($row['name'] ?? '');
    $dept   = strtolower(trim($row['department'] ?? ''));

    // Validate Required Name
    if ($name === '') {
        $errors[] = 'Product name is required.';
    }

    // Validate Department
    if ($dept === '') {
        $errors[] = 'Department is required (must be "lingerie" or "instruments").';
    } elseif (!in_array($dept, ['lingerie', 'instruments'], true)) {
        $errors[] = sprintf('Invalid department "%s". Allowed values: "lingerie" or "instruments".', e($dept));
    }

    // Validate Price
    $priceRaw = str_replace([',', '$', '€', '£', '₦'], '', trim($row['price'] ?? ''));
    if ($priceRaw === '' || !is_numeric($priceRaw)) {
        $errors[] = 'Price is required and must be a valid number.';
        $price = 0.0;
    } else {
        $price = (float) $priceRaw;
        if ($price <= 0) {
            $errors[] = 'Price must be greater than zero.';
        }
    }

    // Validate Compare At Price
    $compareRaw = str_replace([',', '$', '€', '£', '₦'], '', trim($row['compare_at_price'] ?? ''));
    $compareAtPrice = null;
    if ($compareRaw !== '') {
        if (!is_numeric($compareRaw)) {
            $warnings[] = 'Compare at price is not a valid number; ignored.';
        } else {
            $val = (float) $compareRaw;
            if ($val > 0) {
                $compareAtPrice = $val;
                if ($compareAtPrice <= $price) {
                    $warnings[] = sprintf('Compare price (%.2f) is <= standard price (%.2f).', $compareAtPrice, $price);
                }
            }
        }
    }

    // Validate Bundle Price
    $bundleRaw = str_replace([',', '$', '€', '£', '₦'], '', trim($row['bundle_price'] ?? ''));
    $bundlePrice = null;
    if ($bundleRaw !== '') {
        if (!is_numeric($bundleRaw)) {
            $warnings[] = 'Bundle price is not a valid number; ignored.';
        } else {
            $val = (float) $bundleRaw;
            if ($val > 0) {
                $bundlePrice = $val;
            }
        }
    }

    // Validate Stock
    $stockRaw = trim($row['stock'] ?? '0');
    $stock = 0;
    if ($stockRaw !== '') {
        if (!is_numeric($stockRaw)) {
            $warnings[] = 'Stock quantity was not numeric; defaulting to 0.';
        } else {
            $stock = max(0, (int) $stockRaw);
        }
    }

    // Parse Variants string
    // Format: type:value:stock[:hex] | type:value:stock
    $variants = [];
    $rawVariants = trim($row['variants'] ?? '');
    if ($rawVariants !== '') {
        $chunks = preg_split('/[|;]+/', $rawVariants) ?: [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') continue;
            $parts = explode(':', $chunk);
            if (count($parts) < 2) {
                $warnings[] = sprintf('Variant "%s" is invalid (expected format "type:value:stock[:hex]").', e($chunk));
                continue;
            }
            $vType  = strtolower(trim($parts[0]));
            if (!in_array($vType, ['size', 'color'], true)) {
                $vType = 'size';
            }
            $vVal   = trim($parts[1]);
            $vStock = isset($parts[2]) && is_numeric(trim($parts[2])) ? max(0, (int) trim($parts[2])) : $stock;
            $vHex   = isset($parts[3]) && preg_match('/^#?[a-f0-9]{6}$/i', trim($parts[3]))
                ? (str_starts_with(trim($parts[3]), '#') ? trim($parts[3]) : '#' . trim($parts[3]))
                : null;

            if ($vVal !== '') {
                $variants[] = [
                    'type'  => $vType,
                    'value' => $vVal,
                    'stock' => $vStock,
                    'hex'   => $vHex,
                ];
            }
        }
    }

    // Parse Specs string
    // Format: Label:Value | Label:Value
    $specs = [];
    $rawSpecs = trim($row['specs'] ?? '');
    if ($rawSpecs !== '') {
        $chunks = preg_split('/[|]+/', $rawSpecs) ?: [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') continue;
            $parts = explode(':', $chunk, 2);
            if (count($parts) === 2) {
                $sLabel = trim($parts[0]);
                $sValue = trim($parts[1]);
                if ($sLabel !== '' && $sValue !== '') {
                    $specs[] = ['label' => $sLabel, 'value' => $sValue];
                }
            }
        }
    }

    // Parse Bundles string
    // Format: ItemName:ItemDesc:Price | ItemName:ItemDesc:Price
    $bundles = [];
    $rawBundles = trim($row['bundles'] ?? '');
    if ($rawBundles !== '') {
        $chunks = preg_split('/[|]+/', $rawBundles) ?: [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') continue;
            $parts = explode(':', $chunk);
            if (count($parts) >= 2) {
                $bName  = trim($parts[0]);
                $bDesc  = count($parts) >= 3 ? trim($parts[1]) : '';
                $bPrice = (float) (count($parts) >= 3 ? trim($parts[2]) : trim($parts[1]));
                if ($bName !== '') {
                    $bundles[] = [
                        'item_name'  => $bName,
                        'item_desc'  => $bDesc !== '' ? $bDesc : null,
                        'item_price' => max(0, $bPrice),
                    ];
                }
            }
        }
    }

    // Parse Gallery Images
    $gallery = [];
    $rawGallery = trim($row['gallery_images'] ?? '');
    if ($rawGallery !== '') {
        $gChunks = preg_split('/[|,]+/', $rawGallery) ?: [];
        foreach ($gChunks as $gUrl) {
            $gUrl = trim($gUrl);
            if ($gUrl !== '') {
                $gallery[] = $gUrl;
            }
        }
    }

    // Determine Action: Insert vs Update
    $action = 'insert';
    $existingId = null;
    if ($sku !== '') {
        if (isset($existingSkus[$sku])) {
            $action = 'update';
            $existingId = $existingSkus[$sku];
        }
    }

    $isActive   = bulk_parse_bool($row['is_active'] ?? null, 1);
    $isFeatured = bulk_parse_bool($row['is_featured'] ?? null, 0);

    return [
        'valid'      => ($errors === []),
        'action'     => ($errors === []) ? $action : 'error',
        'product_id' => $existingId,
        'errors'     => $errors,
        'warnings'   => $warnings,
        'data'       => [
            'row_num'           => $rowNum,
            'sku'               => $sku,
            'name'              => $name,
            'department'        => $dept,
            'category'          => trim($row['category'] ?? ''),
            'brand'             => trim($row['brand'] ?? ''),
            'price'             => $price,
            'compare_at_price'  => $compareAtPrice,
            'bundle_price'      => $bundlePrice,
            'stock'             => $stock,
            'stock_label'       => trim($row['stock_label'] ?? ''),
            'badge'             => trim($row['badge'] ?? ''),
            'short_description' => trim($row['short_description'] ?? ''),
            'description'       => trim($row['description'] ?? ''),
            'image_url'         => trim($row['image_url'] ?? ''),
            'gallery_images'    => $gallery,
            'is_active'         => $isActive,
            'is_featured'       => $isFeatured,
            'variants'          => $variants,
            'specs'             => $specs,
            'bundles'           => $bundles,
        ],
    ];
}

/**
 * Load all existing SKUs into a fast in-memory map [sku => id].
 *
 * @return array<string, int>
 */
function bulk_get_existing_skus_map(): array
{
    try {
        $rows = db_all('SELECT id, sku FROM products WHERE sku IS NOT NULL AND sku <> ""');
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['sku']] = (int) $r['id'];
        }
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Preview and dry-run validate parsed CSV rows.
 *
 * @param array<int, array<string, string>> $rows
 * @param array{mode?: string} $options
 * @return array{
 *   total: int,
 *   valid_count: int,
 *   error_count: int,
 *   insert_count: int,
 *   update_count: int,
 *   results: array<int, array<string, mixed>>
 * }
 */
function bulk_preview_validation(array $rows, array $options = []): array
{
    $existingMap = bulk_get_existing_skus_map();
    $results = [];
    $validCount  = 0;
    $errorCount  = 0;
    $insertCount = 0;
    $updateCount = 0;

    // Track duplicate SKUs appearing multiple times within the same upload file
    $fileSkus = [];

    foreach ($rows as $row) {
        $validated = bulk_validate_row($row, $existingMap);

        $sku = $validated['data']['sku'];
        if ($sku !== '') {
            if (isset($fileSkus[$sku])) {
                $validated['warnings'][] = sprintf('Duplicate SKU "%s" also appeared on row %d of this file.', e($sku), $fileSkus[$sku]);
            } else {
                $fileSkus[$sku] = (int) ($row['_row_num'] ?? 0);
            }
        }

        if ($validated['valid']) {
            $validCount++;
            if ($validated['action'] === 'update') {
                $updateCount++;
            } else {
                $insertCount++;
            }
        } else {
            $errorCount++;
        }

        $results[] = $validated;
    }

    return [
        'total'        => count($rows),
        'valid_count'  => $validCount,
        'error_count'  => $errorCount,
        'insert_count' => $insertCount,
        'update_count' => $updateCount,
        'results'      => $results,
    ];
}

/**
 * Execute bulk import of validated items.
 *
 * @param array<int, array<string, mixed>> $validatedRows
 * @param array{
 *   mode?: 'upsert'|'insert_only'|'update_only',
 *   auto_taxonomy?: bool,
 *   stop_on_error?: bool
 * } $options
 * @return array{
 *   success: bool,
 *   created: int,
 *   updated: int,
 *   skipped: int,
 *   failed: int,
 *   errors: array<int, array{row: int, sku: string, message: string}>,
 *   log: string[]
 * }
 */
function bulk_execute_import(array $validatedRows, array $options = []): array
{
    $mode         = $options['mode'] ?? 'upsert';
    $autoTaxonomy = !empty($options['auto_taxonomy']);
    
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $failed  = 0;
    $errors  = [];
    $log     = [];

    // Cache categories & brands lookup to minimize DB roundtrips
    $catRows = db_all('SELECT id, name, slug, department FROM categories');
    $brandRows = db_all('SELECT id, name, slug FROM brands');

    $catMap = [];
    foreach ($catRows as $c) {
        $catMap[strtolower((string) $c['department']) . ':' . strtolower((string) $c['name'])] = (int) $c['id'];
        $catMap[strtolower((string) $c['department']) . ':' . strtolower((string) $c['slug'])] = (int) $c['id'];
    }

    $brandMap = [];
    foreach ($brandRows as $b) {
        $brandMap[strtolower((string) $b['name'])] = (int) $b['id'];
        $brandMap[strtolower((string) $b['slug'])] = (int) $b['id'];
    }

    // Refresh existing SKUs map
    $existingMap = bulk_get_existing_skus_map();

    // Sequence tracking for auto-SKU generation
    $deptSeqs = [
        'lingerie'    => (int) db_val('SELECT COALESCE(MAX(id), 0) FROM products WHERE department = "lingerie"', [], 0),
        'instruments' => (int) db_val('SELECT COALESCE(MAX(id), 0) FROM products WHERE department = "instruments"', [], 0),
    ];

    foreach ($validatedRows as $item) {
        if (!$item['valid']) {
            $failed++;
            $errors[] = [
                'row'     => (int) $item['data']['row_num'],
                'sku'     => (string) $item['data']['sku'],
                'message' => implode('; ', $item['errors']),
            ];
            continue;
        }

        $d = $item['data'];
        $rowNum = (int) $d['row_num'];
        $sku = (string) $d['sku'];
        $action = $item['action'];
        $existingId = $item['product_id'];

        // Filter based on selected mode
        if ($mode === 'insert_only' && $action === 'update') {
            $skipped++;
            $log[] = sprintf('Row %d: Skipped SKU "%s" (already exists, insert-only mode).', $rowNum, e($sku));
            continue;
        }
        if ($mode === 'update_only' && $action === 'insert') {
            $skipped++;
            $log[] = sprintf('Row %d: Skipped "%s" (does not exist, update-only mode).', $rowNum, e($d['name']));
            continue;
        }

        try {
            db_tx(function () use (
                &$d, &$sku, &$action, &$existingId, $rowNum, $autoTaxonomy,
                &$catMap, &$brandMap, &$existingMap, &$deptSeqs,
                &$created, &$updated, &$log
            ) {
                // 1. Resolve Category
                $categoryId = null;
                $catName = (string) $d['category'];
                if ($catName !== '') {
                    $catKey = strtolower($d['department']) . ':' . strtolower($catName);
                    if (isset($catMap[$catKey])) {
                        $categoryId = $catMap[$catKey];
                    } elseif ($autoTaxonomy) {
                        $nc = admin_find_or_create_category($catName, $d['department']);
                        $categoryId = $nc['id'];
                        $catMap[$catKey] = $categoryId;
                    }
                }

                // 2. Resolve Brand
                $brandId = null;
                $brandLabel = null;
                $brandName = (string) $d['brand'];
                if ($brandName !== '') {
                    $bKey = strtolower($brandName);
                    if (isset($brandMap[$bKey])) {
                        $brandId = $brandMap[$bKey];
                        $brandLabel = $brandName;
                    } elseif ($autoTaxonomy) {
                        $nb = admin_find_or_create_brand($brandName);
                        $brandId = $nb['id'];
                        $brandLabel = $nb['name'];
                        $brandMap[$bKey] = $brandId;
                    } else {
                        $brandLabel = $brandName;
                    }
                }

                // 3. Resolve SKU
                if ($sku === '') {
                    $prefix = strtoupper(substr($d['department'], 0, 2));
                    $deptSeqs[$d['department']]++;
                    $sku = sprintf('VL-%s-%05d', $prefix, $deptSeqs[$d['department']]);
                    while (isset($existingMap[$sku]) || db_one('SELECT id FROM products WHERE sku = ?', [$sku]) !== null) {
                        $deptSeqs[$d['department']]++;
                        $sku = sprintf('VL-%s-%05d', $prefix, $deptSeqs[$d['department']]);
                    }
                }

                // 4. Resolve Unique Slug
                $baseSlug = admin_slugify((string) $d['name']);
                $slug = admin_unique_slug($baseSlug, $existingId ?? 0);

                // 5. Insert or Update Product
                $productId = 0;
                $params = [
                    $sku,
                    $slug,
                    $categoryId,
                    $brandId,
                    $d['department'],
                    $d['name'],
                    $brandLabel,
                    $d['short_description'] !== '' ? $d['short_description'] : null,
                    $d['description'] !== '' ? $d['description'] : null,
                    $d['price'],
                    $d['compare_at_price'],
                    $d['bundle_price'],
                    $d['stock'],
                    $d['is_active'],
                    $d['is_featured'],
                    $d['badge'] !== '' ? $d['badge'] : null,
                    $d['stock_label'] !== '' ? $d['stock_label'] : null,
                    $d['image_url'] !== '' ? $d['image_url'] : null,
                ];

                if ($action === 'update' && $existingId > 0) {
                    $sql = 'UPDATE products SET
                            sku = ?, slug = ?, category_id = ?, brand_id = ?, department = ?, name = ?, brand_label = ?,
                            short_description = ?, description = ?, price = ?, compare_at_price = ?, bundle_price = ?,
                            stock = ?, is_active = ?, is_featured = ?, badge = ?, stock_label = ?, image_url = ?
                            WHERE id = ?';
                    db_exec($sql, array_merge($params, [$existingId]));
                    $productId = $existingId;
                    $updated++;
                    $log[] = sprintf('Row %d: Updated product "%s" (SKU: %s, ID: %d).', $rowNum, e($d['name']), e($sku), $productId);
                } else {
                    $sql = 'INSERT INTO products
                            (sku, slug, category_id, brand_id, department, name, brand_label,
                             short_description, description, price, compare_at_price, bundle_price,
                             stock, is_active, is_featured, badge, stock_label, image_url)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    db_exec($sql, $params);
                    $productId = (int) db_val('SELECT id FROM products WHERE sku = ?', [$sku], 0);
                    $existingMap[$sku] = $productId;
                    $created++;
                    $log[] = sprintf('Row %d: Created product "%s" (SKU: %s, ID: %d).', $rowNum, e($d['name']), e($sku), $productId);
                }

                if ($productId <= 0) {
                    throw new RuntimeException('Failed to retrieve product ID after saving.');
                }

                // 6. Sync Product Gallery Images
                $images = [];
                if ($d['image_url'] !== '') {
                    $images[] = $d['image_url'];
                }
                foreach ($d['gallery_images'] as $gImg) {
                    if ($gImg !== '' && !in_array($gImg, $images, true)) {
                        $images[] = $gImg;
                    }
                }

                if ($images !== []) {
                    // Remove old images if updating or rewrite
                    db_exec('DELETE FROM product_images WHERE product_id = ?', [$productId]);
                    $sortOrder = 1;
                    foreach ($images as $imgUrl) {
                        db_exec(
                            'INSERT INTO product_images (product_id, url, alt, sort_order) VALUES (?, ?, ?, ?)',
                            [$productId, $imgUrl, $d['name'], $sortOrder++]
                        );
                    }
                }

                // 7. Sync Variants
                if ($d['variants'] !== []) {
                    db_exec('DELETE FROM product_variants WHERE product_id = ?', [$productId]);
                    $vSort = 1;
                    foreach ($d['variants'] as $variant) {
                        db_exec(
                            'INSERT INTO product_variants (product_id, type, value, hex, stock, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
                            [$productId, $variant['type'], $variant['value'], $variant['hex'], $variant['stock'], $vSort++]
                        );
                    }
                }

                // 8. Sync Specifications
                if ($d['specs'] !== []) {
                    db_exec('DELETE FROM product_specs WHERE product_id = ?', [$productId]);
                    $sSort = 1;
                    foreach ($d['specs'] as $spec) {
                        db_exec(
                            'INSERT INTO product_specs (product_id, label, value, sort_order) VALUES (?, ?, ?, ?)',
                            [$productId, $spec['label'], $spec['value'], $sSort++]
                        );
                    }
                }

                // 9. Sync Bundle Packages
                if ($d['bundles'] !== []) {
                    db_exec('DELETE FROM product_bundles WHERE product_id = ?', [$productId]);
                    $bSort = 1;
                    foreach ($d['bundles'] as $bundle) {
                        db_exec(
                            'INSERT INTO product_bundles (product_id, item_name, item_desc, item_price, sort_order) VALUES (?, ?, ?, ?, ?)',
                            [$productId, $bundle['item_name'], $bundle['item_desc'], $bundle['item_price'], $bSort++]
                        );
                    }
                }
            });
        } catch (Throwable $e) {
            $failed++;
            $errors[] = [
                'row'     => $rowNum,
                'sku'     => $sku,
                'message' => $e->getMessage(),
            ];
            $log[] = sprintf('Row %d: FAILED (%s).', $rowNum, $e->getMessage());
        }
    }

    return [
        'success' => ($failed === 0),
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'failed'  => $failed,
        'errors'  => $errors,
        'log'     => $log,
    ];
}

/**
 * Generate a complete, ready-to-import sample CSV content.
 */
function bulk_generate_sample_csv(): string
{
    $schema = bulk_product_schema();
    $headers = array_keys($schema);

    $stream = fopen('php://memory', 'r+');
    // Prepend UTF-8 BOM for seamless Excel compatibility
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, $headers);

    $sampleRows = [
        [
            'sku'               => 'VL-LI-00101',
            'name'              => 'Aura Satin & French Lace Bodysuit',
            'department'        => 'lingerie',
            'category'          => 'Bodysuits',
            'brand'             => 'Velora Atelier',
            'price'             => '89.00',
            'compare_at_price'  => '120.00',
            'bundle_price'      => '145.00',
            'stock'             => '35',
            'stock_label'       => 'In Stock',
            'badge'             => 'Bestseller',
            'short_description' => 'Luxurious floral stretch lace with contouring satin paneling and gold hardware.',
            'description'       => 'Indulge in pure elegance. Handcrafted with delicate Chantilly lace and ultra-soft silk satin for an impeccable silhouette.',
            'image_url'         => 'assets/products/lingerie-bodysuit-1.jpg',
            'gallery_images'    => 'assets/products/lingerie-bodysuit-2.jpg | assets/products/lingerie-bodysuit-3.jpg',
            'is_active'         => '1',
            'is_featured'       => '1',
            'variants'          => 'size:XS:5 | size:S:10 | size:M:12 | size:L:8 | color:Noir Black:#0d0d0d:20 | color:Crimson Red:#990011:15',
            'specs'             => 'Fabric:92% Silk Satin, 8% Elastane | Lace:100% French Chantilly | Closure:Snap gusset | Care:Hand wash cold',
            'bundles'           => 'Matching Satin Robe:Kimono cut silk drape robe:55.00 | Velvet Storage Pouch:Protective dust bag:12.00',
        ],
        [
            'sku'               => 'VL-IN-00204',
            'name'              => 'Stentor Master Concert Cello 4/4',
            'department'        => 'instruments',
            'category'          => 'Cellos',
            'brand'             => 'Stentor Orchestral',
            'price'             => '1250.00',
            'compare_at_price'  => '1450.00',
            'bundle_price'      => '1399.00',
            'stock'             => '8',
            'stock_label'       => 'Limited Stock',
            'badge'             => 'Orchestral Pro',
            'short_description' => 'Solid carved spruce top with flamed maple back and ebony fittings.',
            'description'       => 'The Stentor Master Concert Cello offers rich resonant tones and effortless projection, complete with professional hand-applied oil varnish.',
            'image_url'         => 'assets/products/cello-master-1.jpg',
            'gallery_images'    => 'assets/products/cello-master-2.jpg | assets/products/cello-master-3.jpg',
            'is_active'         => '1',
            'is_featured'       => '1',
            'variants'          => 'size:4/4 Full:5 | size:3/4 Student:3',
            'specs'             => 'Top:Solid Carved Spruce | Back & Sides:Selected Flamed Maple | Fingerboard:Solid Ebony | Tailpiece:Wittner with 4 adjusters',
            'bundles'           => 'Carbon Fiber Cello Bow:Precision balanced braided carbon bow:120.00 | Padded Travel Gig Bag:20mm high-density foam case:75.00',
        ],
        [
            'sku'               => '', // Tests auto-SKU generation
            'name'              => 'Silk Charmeuse Chemise & Robe Set',
            'department'        => 'lingerie',
            'category'          => 'Sleepwear',
            'brand'             => 'Velora Atelier',
            'price'             => '115.00',
            'compare_at_price'  => '140.00',
            'bundle_price'      => '',
            'stock'             => '20',
            'stock_label'       => 'New Arrival',
            'badge'             => 'New',
            'short_description' => 'Lightweight pure mulberry silk chemise with delicate scalloped hems.',
            'description'       => 'Designed for restful nights and slow mornings, crafted from 19mm grade 6A mulberry silk.',
            'image_url'         => 'assets/products/silk-chemise-1.jpg',
            'gallery_images'    => 'assets/products/silk-chemise-2.jpg',
            'is_active'         => '1',
            'is_featured'       => '0',
            'variants'          => 'size:S:8 | size:M:8 | size:L:4 | color:Champagne Pearl:#f4ebd9:10 | color:Midnight Navy:#1a233a:10',
            'specs'             => 'Material:100% 19mm Mulberry Silk | Straps:Adjustable cross-back | Origin:Hand-finished in Portugal',
            'bundles'           => '',
        ],
    ];

    foreach ($sampleRows as $row) {
        $ordered = [];
        foreach ($headers as $h) {
            $ordered[] = $row[$h] ?? '';
        }
        fputcsv($stream, $ordered);
    }

    rewind($stream);
    $csv = stream_get_contents($stream) ?: '';
    fclose($stream);
    return $csv;
}

/**
 * Export active or filtered catalogue to CSV string.
 *
 * @param array{department?: string, search?: string} $filters
 * @return string CSV text content
 */
function bulk_export_products_csv(array $filters = []): string
{
    $where = ['1 = 1'];
    $params = [];

    if (!empty($filters['department']) && in_array($filters['department'], ['lingerie', 'instruments'], true)) {
        $where[] = 'p.department = ?';
        $params[] = $filters['department'];
    }
    if (!empty($filters['search'])) {
        $like = '%' . trim($filters['search']) . '%';
        $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.slug LIKE ? OR p.brand_label LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    $whereSql = implode(' AND ', $where);
    $sql = "SELECT p.*, c.name AS category_name, b.name AS brand_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN brands b ON b.id = p.brand_id
            WHERE $whereSql
            ORDER BY p.department ASC, p.id ASC";

    $products = [];
    try {
        $products = db_all($sql, $params);
    } catch (Throwable $e) {
        $products = [];
    }

    $schema = bulk_product_schema();
    $headers = array_keys($schema);

    $stream = fopen('php://memory', 'r+');
    // Prepend UTF-8 BOM
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, $headers);

    // Fetch related items in batch for performance
    $productIds = array_map(static fn($p) => (int) $p['id'], $products);
    
    $imagesByProduct   = [];
    $variantsByProduct = [];
    $specsByProduct    = [];
    $bundlesByProduct  = [];

    if ($productIds !== []) {
        $inClause = implode(',', array_fill(0, count($productIds), '?'));

        // Gallery Images
        $imgRows = db_all(
            "SELECT product_id, url FROM product_images WHERE product_id IN ($inClause) ORDER BY sort_order ASC, id ASC",
            $productIds
        );
        foreach ($imgRows as $img) {
            $pid = (int) $img['product_id'];
            $imagesByProduct[$pid][] = (string) $img['url'];
        }

        // Variants
        $varRows = db_all(
            "SELECT product_id, type, value, hex, stock FROM product_variants WHERE product_id IN ($inClause) ORDER BY sort_order ASC, id ASC",
            $productIds
        );
        foreach ($varRows as $v) {
            $pid = (int) $v['product_id'];
            $piece = $v['type'] . ':' . $v['value'] . ':' . $v['stock'];
            if (!empty($v['hex'])) {
                $piece .= ':' . $v['hex'];
            }
            $variantsByProduct[$pid][] = $piece;
        }

        // Specs
        $specRows = db_all(
            "SELECT product_id, label, value FROM product_specs WHERE product_id IN ($inClause) ORDER BY sort_order ASC, id ASC",
            $productIds
        );
        foreach ($specRows as $s) {
            $pid = (int) $s['product_id'];
            $specsByProduct[$pid][] = $s['label'] . ':' . $s['value'];
        }

        // Bundles
        $bundleRows = db_all(
            "SELECT product_id, item_name, item_desc, item_price FROM product_bundles WHERE product_id IN ($inClause) ORDER BY sort_order ASC, id ASC",
            $productIds
        );
        foreach ($bundleRows as $b) {
            $pid = (int) $b['product_id'];
            $bundlesByProduct[$pid][] = $b['item_name'] . ':' . ($b['item_desc'] ?? '') . ':' . number_format((float) $b['item_price'], 2, '.', '');
        }
    }

    foreach ($products as $p) {
        $pid = (int) $p['id'];
        $gallery = $imagesByProduct[$pid] ?? [];
        // Filter out main image from gallery if already set as image_url
        if (!empty($p['image_url'])) {
            $gallery = array_values(array_filter($gallery, static fn($u) => $u !== $p['image_url']));
        }

        $row = [
            'sku'               => (string) $p['sku'],
            'name'              => (string) $p['name'],
            'department'        => (string) $p['department'],
            'category'          => (string) ($p['category_name'] ?? ''),
            'brand'             => (string) ($p['brand_name'] ?? $p['brand_label'] ?? ''),
            'price'             => number_format((float) $p['price'], 2, '.', ''),
            'compare_at_price'  => $p['compare_at_price'] !== null ? number_format((float) $p['compare_at_price'], 2, '.', '') : '',
            'bundle_price'      => $p['bundle_price'] !== null ? number_format((float) $p['bundle_price'], 2, '.', '') : '',
            'stock'             => (string) $p['stock'],
            'stock_label'       => (string) ($p['stock_label'] ?? ''),
            'badge'             => (string) ($p['badge'] ?? ''),
            'short_description' => (string) ($p['short_description'] ?? ''),
            'description'       => (string) ($p['description'] ?? ''),
            'image_url'         => (string) ($p['image_url'] ?? ''),
            'gallery_images'    => implode(' | ', $gallery),
            'is_active'         => (string) $p['is_active'],
            'is_featured'       => (string) $p['is_featured'],
            'variants'          => implode(' | ', $variantsByProduct[$pid] ?? []),
            'specs'             => implode(' | ', $specsByProduct[$pid] ?? []),
            'bundles'           => implode(' | ', $bundlesByProduct[$pid] ?? []),
        ];

        $ordered = [];
        foreach ($headers as $h) {
            $ordered[] = $row[$h] ?? '';
        }
        fputcsv($stream, $ordered);
    }

    rewind($stream);
    $csv = stream_get_contents($stream) ?: '';
    fclose($stream);
    return $csv;
}

/**
 * Generate an error report CSV containing failed rows along with their error messages.
 *
 * @param array<int, array{row: int, sku: string, message: string}> $errors
 * @param array<int, array<string, string>> $rawRows
 * @return string CSV text content
 */
function bulk_generate_error_report_csv(array $errors, array $rawRows): string
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, "\xEF\xBB\xBF");

    // Index error messages by row number
    $errorMap = [];
    foreach ($errors as $err) {
        $errorMap[$err['row']] = $err['message'];
    }

    $firstRow = reset($rawRows) ?: [];
    $headers = array_keys(array_filter($firstRow, static fn($k) => $k !== '_row_num', ARRAY_FILTER_USE_KEY));
    fputcsv($stream, array_merge(['_row_number', '_error_reason'], $headers));

    foreach ($rawRows as $row) {
        $rowNum = (int) ($row['_row_num'] ?? 0);
        if (isset($errorMap[$rowNum])) {
            $cleanRow = [];
            $cleanRow[] = $rowNum;
            $cleanRow[] = $errorMap[$rowNum];
            foreach ($headers as $h) {
                $cleanRow[] = $row[$h] ?? '';
            }
            fputcsv($stream, $cleanRow);
        }
    }

    rewind($stream);
    $csv = stream_get_contents($stream) ?: '';
    fclose($stream);
    return $csv;
}
