<?php
/** Create / edit a catalogue product. */

declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_slugs.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$product = $id > 0 ? db_one('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id > 0 && $product === null) {
    flash_set('error', 'That product does not exist.');
    header('Location: ' . url('admin/products.php'));
    exit;
}

$categories = db_all('SELECT id, name, department FROM categories ORDER BY department, name');
$brands     = db_all('SELECT id, name FROM brands ORDER BY name');
$errors     = [];
$formNewBrand    = '';
$formNewCategory = '';
$notes = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();

    $f = [
        'name'       => trim((string) ($_POST['name'] ?? '')),
        'sku'        => trim((string) ($_POST['sku'] ?? '')),
        'slug'       => trim((string) ($_POST['slug'] ?? '')),
        'department' => (string) ($_POST['department'] ?? 'lingerie'),
        'category'   => (int) ($_POST['category_id'] ?? 0),
        'brand_id'   => (int) ($_POST['brand_id'] ?? 0),
        'new_brand'  => trim((string) ($_POST['new_brand'] ?? '')),
        'new_category' => trim((string) ($_POST['new_category'] ?? '')),
        'price'      => (float) ($_POST['price'] ?? 0),
        'compare'    => trim((string) ($_POST['compare_at_price'] ?? '')),
        'stock'      => max(0, (int) ($_POST['stock'] ?? 0)),
        'badge'      => trim((string) ($_POST['badge'] ?? '')),
        'stock_label'=> trim((string) ($_POST['stock_label'] ?? '')),
        'short'      => trim((string) ($_POST['short_description'] ?? '')),
        'description'=> trim((string) ($_POST['description'] ?? '')),
        'image'      => trim((string) ($_POST['image_url'] ?? '')),
        'active'     => isset($_POST['is_active']) ? 1 : 0,
        'featured'   => isset($_POST['is_featured']) ? 1 : 0,
    ];
    $formNewBrand    = $f['new_brand'];
    $formNewCategory = $f['new_category'];

    if ($f['name'] === '') { $errors[] = 'Product name is required.'; }
    if (!in_array($f['department'], ['lingerie', 'instruments'], true)) { $errors[] = 'Choose a department.'; }
    if ($f['price'] <= 0) { $errors[] = 'Price must be greater than zero.'; }

    $slug = $f['slug'] !== '' ? admin_slugify($f['slug']) : admin_slugify($f['name']);
    $slug = admin_unique_slug($slug, $id);

    if ($f['sku'] === '') {
        $prefix = strtoupper(substr($f['department'], 0, 2));
        $seq    = ((int) db_val('SELECT COALESCE(MAX(id),0) FROM products', [], 0)) + 1;
        $f['sku'] = sprintf('VL-%s-%05d', $prefix, $seq);
        while (db_one('SELECT id FROM products WHERE sku = ?', [$f['sku']]) !== null) {
            $seq++;
            $f['sku'] = sprintf('VL-%s-%05d', $prefix, $seq);
        }
    }
    if (db_one('SELECT id FROM products WHERE sku = ?' . ($id > 0 ? ' AND id <> ?' : ''), $id > 0 ? [$f['sku'], $id] : [$f['sku']]) !== null) {
        $errors[] = 'That SKU is already in use.';
    }
    if ($f['category'] > 0 && db_one('SELECT id FROM categories WHERE id = ?', [$f['category']]) === null) {
        $errors[] = 'That category does not exist.';
    }
    if ($f['brand_id'] > 0 && db_one('SELECT id FROM brands WHERE id = ?', [$f['brand_id']]) === null) {
        $errors[] = 'That brand does not exist.';
    }

    if ($errors === []) {
        // Brand: the typed name wins (created on the spot), otherwise link the selection.
        $brandId = $f['brand_id'] > 0 ? $f['brand_id'] : null;
        $brandLabel = null;
        if ($brandId !== null) {
            $brandLabel = (string) db_val('SELECT name FROM brands WHERE id = ?', [$brandId], '');
        }
        if ($f['new_brand'] !== '') {
            $nb = admin_find_or_create_brand($f['new_brand']);
            $brandId = $nb['id'];
            $brandLabel = $nb['name'];
            $notes[] = ($nb['created'] ? 'Created brand "' : 'Linked existing brand "') . $nb['name'] . '".';
        }

        // Category: same treatment, filed under the selected department.
        if ($f['new_category'] !== '') {
            $nc = admin_find_or_create_category($f['new_category'], $f['department']);
            $f['category'] = $nc['id'];
            $notes[] = ($nc['created'] ? 'Created category "' : 'Linked existing category "') . $nc['name'] . '".';
        }

        $compare = $f['compare'] !== '' ? (float) $f['compare'] : null;
        $params = [
            $f['sku'], $slug, $f['category'] ?: null, $f['department'], $f['name'], $brandId, $brandLabel,
            $f['short'], $f['description'], $f['price'], $compare, $f['stock'],
            $f['active'], $f['featured'], $f['badge'], $f['stock_label'], $f['image'],
        ];
        if ($id > 0) {
            $sql = 'UPDATE products SET sku=?, slug=?, category_id=?, department=?, name=?, brand_id=?, brand_label=?,
                    short_description=?, description=?, price=?, compare_at_price=?, stock=?,
                    is_active=?, is_featured=?, badge=?, stock_label=?, image_url=? WHERE id=?';
            db_exec($sql, array_merge($params, [$id]));
            $newId = $id;
        } else {
            $sql = 'INSERT INTO products
                    (sku, slug, category_id, department, name, brand_id, brand_label,
                     short_description, description, price, compare_at_price, stock,
                     is_active, is_featured, badge, stock_label, image_url)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            db_exec($sql, $params);
            $newId = (int) db_val('SELECT id FROM products WHERE sku = ?', [$f['sku']], 0);
        }

        // Keep the gallery in step with the main image: the saved image always
        // leads the strip (added when missing, moved up when already present).
        $img = $f['image'];
        if ($img !== '') {
            $rows = db_all('SELECT id, url FROM product_images WHERE product_id = ? ORDER BY sort_order, id', [$newId]);
            $match = null;
            foreach ($rows as $row) {
                if ((string) $row['url'] === $img) {
                    $match = $row;
                    break;
                }
            }
            if ($match !== null) {
                db_exec('UPDATE product_images SET sort_order = sort_order + 100 WHERE product_id = ?', [$newId]);
                db_exec('UPDATE product_images SET sort_order = 1 WHERE id = ?', [$match['id']]);
            } elseif ($rows !== []) {
                db_exec('UPDATE product_images SET url = ? WHERE id = ?', [$img, $rows[0]['id']]);
            } else {
                db_exec('INSERT INTO product_images (product_id, url, alt, sort_order) VALUES (?,?,?,1)',
                    [$newId, $img, $f['name']]);
            }
        }

        // The confirmation travels in the query string: the ajax layer's fetch
        // follows this PRG redirect first and would consume a session flash
        // before the real navigation to the list could show it.
        $msg = ($id > 0 ? '"' . $f['name'] . '" updated. ' : '"' . $f['name'] . '" added to the catalogue. ')
            . implode(' ', $notes);
        header('Location: ' . url('admin/products.php') . '?flash=' . rawurlencode(trim($msg)));
        exit;
    }
    // re-render the form with the submitted values
    $product = array_merge($product ?? [], [
        'sku' => $f['sku'], 'slug' => $slug, 'department' => $f['department'], 'category_id' => $f['category'],
        'name' => $f['name'], 'brand_id' => $f['brand_id'], 'price' => $f['price'],
        'compare_at_price' => $compare, 'stock' => $f['stock'], 'is_active' => $f['active'],
        'is_featured' => $f['featured'], 'badge' => $f['badge'], 'stock_label' => $f['stock_label'],
        'short_description' => $f['short'], 'description' => $f['description'], 'image_url' => $f['image'],
        'id' => $id,
    ]);
}

$v = static fn (string $k, string $d = '') => e((string) ($product[$k] ?? $d));

admin_head($id > 0 ? 'Edit Product' : 'New Product', 'products');
?>
<div class="max-w-4xl">
  <nav class="font-label-nav text-label-nav text-on-surface-variant mb-3 flex items-center gap-2">
    <a class="hover:text-primary" href="<?= e(url('admin/index.php')) ?>">Dashboard</a>
    <span class="text-outline-variant">/</span>
    <a class="hover:text-primary" href="<?= e(url('admin/products.php')) ?>">Products</a>
    <span class="text-outline-variant">/</span>
    <span class="text-primary font-semibold"><?= $id > 0 ? 'Edit' : 'New' ?></span>
  </nav>

  <div class="flex items-end justify-between gap-3 mb-space-md">
    <div>
      <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Catalogue</span>
      <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold"><?= $id > 0 ? 'Edit product' : 'Add a product' ?></h1>
    </div>
    <?php if ($id > 0): ?>
      <a class="px-4 py-2 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface-variant" target="_blank" rel="noopener"
         href="<?= e(url('product.php?slug=' . urlencode($v('slug')))) ?>">View on storefront</a>
    <?php endif; ?>
  </div>

  <?php if ($errors !== []): ?>
    <div class="mb-4 px-4 py-3 rounded-lg bg-error-container text-on-error-container font-body-sm text-body-sm">
      <ul class="list-disc ml-5"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form data-ajax method="post" class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md flex flex-col gap-space-md">
    <?= csrf_field() ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Name <span class="text-error">*</span></span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="name" required value="<?= $v('name') ?>"/>
      </label>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">SKU</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm bg-surface-container focus:border-primary outline-none"
               name="sku" value="<?= $v('sku') ?>" placeholder="auto-generated when left blank"/>
      </label>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">URL slug</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
               name="slug" value="<?= $v('slug') ?>" placeholder="generated from the name when blank"/>
      </label>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Brand</span>
        <select class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="brand_id">
          <option value="0">— No brand —</option>
          <?php foreach ($brands as $b): ?>
            <option value="<?= (int) $b['id'] ?>" <?= (int) ($product['brand_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>>
              <?= e($b['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Add a new brand</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
               name="new_brand" value="<?= e($formNewBrand) ?>" placeholder="Not listed above? Type the name"/>
      </label>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Department <span class="text-error">*</span></span>
        <select class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="department">
          <option value="lingerie" <?= $v('department') === 'lingerie' ? 'selected' : '' ?>>Lingerie</option>
          <option value="instruments" <?= $v('department') === 'instruments' ? 'selected' : '' ?>>Instruments</option>
        </select>
      </label>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Category</span>
        <select class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="category_id">
          <option value="0">Uncategorised</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) ($product['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?> (<?= e($c['department']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Add a new category</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
               name="new_category" value="<?= e($formNewCategory) ?>" placeholder="Not listed above? Type the name"/>
      </label>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Price (GH₵) <span class="text-error">*</span></span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
               type="number" step="0.01" min="0" name="price" required value="<?= $v('price') ?>"/>
      </label>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Compare-at price</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
               type="number" step="0.01" min="0" name="compare_at_price" value="<?= $v('compare_at_price') ?>"/>
      </label>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Stock on hand</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
               type="number" min="0" name="stock" value="<?= $v('stock', '0') ?>"/>
      </label>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Badge</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
               name="badge" value="<?= $v('badge') ?>" placeholder="Sale, New, Authorized"/>
      </label>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Stock label</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
               name="stock_label" value="<?= $v('stock_label') ?>" placeholder="Only 3 left - ships tomorrow"/>
      </label>
      <?= upload_field('image_url', (string) ($product['image_url'] ?? ''), [
          'wrap'   => 'md:col-span-2',
          'label'  => 'Main image',
          'hint'   => 'JPG, PNG, WebP or GIF up to 8 MB - stored in storage/uploads/ and used as the lead gallery image.',
      ]) ?>
    </div>

    <label class="flex flex-col gap-1">
      <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Short description</span>
      <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
             name="short_description" maxlength="255" value="<?= $v('short_description') ?>"/>
    </label>

    <label class="flex flex-col gap-1">
      <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Full description</span>
      <textarea class="min-h-40 p-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                name="description"><?= $v('description') ?></textarea>
    </label>

    <div class="flex flex-wrap gap-5">
      <label class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface">
        <input type="checkbox" class="accent-primary" name="is_active" <?= (int) ($product['is_active'] ?? 1) ? 'checked' : '' ?>/> Live on the storefront
      </label>
      <label class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface">
        <input type="checkbox" class="accent-primary" name="is_featured" <?= (int) ($product['is_featured'] ?? 0) ? 'checked' : '' ?>/> Feature on the homepage
      </label>
    </div>

    <div class="flex gap-3 pt-2 border-t border-outline-variant/60">
      <button class="px-6 py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary" type="submit">
        <?= $id > 0 ? 'Save Changes' : 'Create Product' ?>
      </button>
      <a class="px-6 py-3 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface-variant" href="<?= e(url('admin/products.php')) ?>">Cancel</a>
    </div>
  </form>
</div>
<?php admin_foot(); ?>
