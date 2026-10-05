<?php
/** Catalogue management: list, search, publish toggles, delete. */

declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $act    = (string) ($_POST['act'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);
    $return = 'admin/products.php';

    if ($act === 'toggle') {
        $field = ($_POST['field'] ?? '') === 'featured' ? 'is_featured' : 'is_active';
        db_exec("UPDATE products SET $field = 1 - $field WHERE id = ?", [$id]);
        flash_set('success', 'Product updated.');
    } elseif ($act === 'delete') {
        $name = (string) db_val('SELECT name FROM products WHERE id = ?', [$id], '');
        db_exec('DELETE FROM product_images WHERE product_id = ?', [$id]);
        db_exec('DELETE FROM product_specs WHERE product_id = ?', [$id]);
        db_exec('DELETE FROM product_variants WHERE product_id = ?', [$id]);
        db_exec('DELETE FROM wishlist WHERE product_id = ?', [$id]);
        db_exec('DELETE FROM cart_items WHERE product_id = ?', [$id]);
        db_exec('DELETE FROM products WHERE id = ?', [$id]);
        flash_set('success', '"' . $name . '" removed from the catalogue.');
    }
    header('Location: ' . url($return));
    exit;
}

// Save confirmations arrive in the query string (see product_form.php).
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['flash'] ?? '') !== '') {
    flash_set('success', mb_substr(trim((string) $_GET['flash']), 0, 300));
}

$q    = trim((string) ($_GET['q'] ?? ''));
$dept = in_array($_GET['dept'] ?? '', ['lingerie', 'instruments'], true) ? $_GET['dept'] : '';

$where  = ['1 = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.slug LIKE ? OR p.sku LIKE ? OR p.brand_label LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($dept !== '') {
    $where[] = 'p.department = ?';
    $params[] = $dept;
}
$whereSql = implode(' AND ', $where);

$products = db_all(
    "SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE $whereSql
     ORDER BY p.is_active DESC, p.id DESC
     LIMIT 200",
    $params
);
$totalSku = (int) db_val('SELECT COUNT(*) FROM products', [], 0);
$inactive = (int) db_val('SELECT COUNT(*) FROM products WHERE is_active = 0', [], 0);

admin_head('Products', 'products');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-space-md">
  <div>
    <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Catalogue</span>
    <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Products</h1>
    <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
      <?= e(plural($totalSku, 'SKU', 'SKUs')) ?> on file &middot; <?= e(plural($inactive, 'draft', 'drafts')) ?> hidden from the storefront.
    </p>
  </div>
  <div class="flex flex-wrap items-center gap-2">
    <a class="px-4 py-2.5 bg-surface-container-lowest border border-outline-variant text-on-surface hover:border-primary hover:text-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider inline-flex items-center gap-1.5 transition-colors"
       href="<?= e(url('admin/bulk_products.php')) ?>">
      <span class="material-symbols-outlined text-base">upload_file</span> Bulk Actions
    </a>
    <a class="px-5 py-2.5 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary transition-colors"
       href="<?= e(url('admin/product_form.php')) ?>">+ New product</a>
  </div>
</div>

<form method="get" class="flex flex-wrap gap-2 mb-space-md">
  <input class="h-11 w-64 px-3 border border-outline-variant rounded-lg font-body-sm text-body-sm focus:border-primary outline-none"
         type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, slug, SKU or brand"/>
  <select class="h-11 px-3 border border-outline-variant rounded-lg font-body-sm text-body-sm focus:border-primary outline-none" name="dept">
    <option value="">All departments</option>
    <option value="lingerie" <?= $dept === 'lingerie' ? 'selected' : '' ?>>Lingerie</option>
    <option value="instruments" <?= $dept === 'instruments' ? 'selected' : '' ?>>Instruments</option>
  </select>
  <button class="h-11 px-5 bg-inverse-surface text-surface rounded-lg font-label-nav text-label-nav uppercase" type="submit">Filter</button>
  <?php if ($q !== '' || $dept !== ''): ?>
    <a class="h-11 px-4 flex items-center border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface-variant" href="<?= e(url('admin/products.php')) ?>">Reset</a>
  <?php endif; ?>
</form>

<section class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
  <div class="overflow-x-auto">
    <table class="w-full text-left">
      <thead>
        <tr class="border-b border-outline-variant">
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Product</th>
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Department</th>
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Price</th>
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Stock</th>
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Flags</th>
          <th class="py-2 font-label-nav text-label-nav uppercase text-on-surface-variant text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($products === []): ?>
          <tr><td class="py-8 font-body-sm text-body-sm text-on-surface-variant text-center" colspan="6">No products match that filter.</td></tr>
        <?php else: foreach ($products as $p): ?>
          <tr class="border-b border-outline-variant/50 hover:bg-surface-container">
            <td class="py-2.5 pr-3">
              <div class="flex items-center gap-3">
                <img class="w-9 h-11 object-cover rounded bg-surface-container" src="<?= e(img_url((string) $p['image_url'])) ?>" alt=""/>
                <div class="min-w-0">
                  <a class="font-body-sm text-body-sm font-semibold text-on-surface hover:text-primary block truncate max-w-xs" href="<?= e(url('admin/product_form.php?id=' . (int) $p['id'])) ?>"><?= e($p['name']) ?></a>
                  <span class="font-body-sm text-[11px] text-outline"><?= e($p['sku']) ?> &middot; <?= e((string) ($p['category_name'] ?? 'Uncategorised')) ?></span>
                </div>
              </div>
            </td>
            <td class="py-2.5 pr-3 font-body-sm text-body-sm text-on-surface-variant capitalize"><?= e($p['department']) ?></td>
            <td class="py-2.5 pr-3 font-label-price text-label-price text-on-surface"><?= e(price($p['price'])) ?></td>
            <td class="py-2.5 pr-3">
              <span class="font-body-sm text-body-sm font-semibold <?= (int) $p['stock'] <= 5 ? 'text-error' : 'text-on-surface' ?>"><?= (int) $p['stock'] ?></span>
            </td>
            <td class="py-2.5 pr-3">
              <form data-ajax method="post" class="flex gap-1.5">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="toggle"/>
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>"/>
                <input type="hidden" name="field" value="active"/>
                <button class="font-label-tag text-label-tag uppercase px-2 py-0.5 rounded <?= (int) $p['is_active'] ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-surface-container-high text-on-surface-variant' ?>"
                        type="submit" title="Toggle storefront visibility"><?= (int) $p['is_active'] ? 'Live' : 'Draft' ?></button>
                <button class="font-label-tag text-label-tag uppercase px-2 py-0.5 rounded <?= (int) $p['is_featured'] ? 'bg-primary-fixed text-primary' : 'bg-surface-container-high text-on-surface-variant' ?>"
                        type="submit" name="field" value="featured" title="Toggle featured placement">Featured</button>
              </form>
            </td>
            <td class="py-2.5 text-right whitespace-nowrap">
              <a class="font-label-nav text-label-nav uppercase text-primary hover:underline mr-3" href="<?= e(url('admin/product_form.php?id=' . (int) $p['id'])) ?>">Edit</a>
              <a class="font-label-nav text-label-nav uppercase text-on-surface-variant hover:underline" target="_blank" rel="noopener" href="<?= e(url('product.php?slug=' . urlencode($p['slug']))) ?>">View</a>
              <form data-ajax method="post" class="inline" onsubmit="return confirm('Delete <?= e($p['name']) ?> permanently?');">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="delete"/>
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>"/>
                <button class="font-label-nav text-label-nav uppercase text-error hover:underline ml-3" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</section>
<script>
  (function () {
    if (!/[?&]flash=/.test(location.search)) { return; }
    var u = new URL(location.href);
    u.searchParams.delete('flash');
    history.replaceState(null, '', u.pathname + u.search + u.hash);
  })();
</script>
<?php admin_foot(); ?>
