<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/product_card.php';

$dept     = in_array($_GET['dept'] ?? '', ['lingerie', 'instruments'], true) ? $_GET['dept'] : '';
$catSlug  = trim((string) ($_GET['cat'] ?? ''));
$onSale   = ($_GET['sale'] ?? '') === '1';
$sort     = (string) ($_GET['sort'] ?? 'featured');
$q        = trim((string) ($_GET['q'] ?? ''));
$maxPrice = isset($_GET['max']) && is_numeric($_GET['max']) ? (float) $_GET['max'] : null;
$minPrice = isset($_GET['min']) && is_numeric($_GET['min']) ? (float) $_GET['min'] : null;

$category = $catSlug !== '' ? db_one('SELECT * FROM categories WHERE slug = ?', [$catSlug]) : null;
if ($catSlug !== '' && $category === null) {
    http_response_code(404);
    set_title('Category not found | Zion Groups');
    render_head();
    echo '<main class="pt-24 max-w-[1360px] mx-auto px-margin py-16 text-center">';
    echo '<h1 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-3">Category not found</h1>';
    echo '<a class="text-primary font-bold" href="' . e(url('shop.php')) . '">Back to all products</a></main>';
    render_foot();
    exit;
}
if ($category !== null && $dept === '') {
    $dept = $category['department'];
}

$sortSql = match ($sort) {
    'price_asc'  => 'products.price ASC',
    'price_desc' => 'products.price DESC',
    'rating'     => 'products.rating DESC, products.review_count DESC',
    'newest'     => 'products.created_at DESC, products.id DESC',
    'name'       => 'products.name ASC',
    default      => 'products.is_featured DESC, products.rating DESC, products.id ASC',
};

$where  = ['products.is_active = 1'];
$params = [];
if ($dept !== '') {
    $where[] = 'products.department = ?';
    $params[] = $dept;
}
if ($category !== null) {
    $where[] = 'products.category_id = ?';
    $params[] = (int) $category['id'];
}
if ($onSale) {
    $where[] = 'products.compare_at_price IS NOT NULL';
}
if ($q !== '') {
    $where[] = '(products.name LIKE ? OR products.brand_label LIKE ? OR products.short_description LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($minPrice !== null) {
    $where[] = 'products.price >= ?';
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $where[] = 'products.price <= ?';
    $params[] = $maxPrice;
}

$whereSql = implode(' AND ', $where);
$total    = (int) db_val("SELECT COUNT(*) FROM products WHERE $whereSql", $params, 0);

$products = db_all(
    "SELECT products.*, categories.name AS category_name, categories.slug AS category_slug
     FROM products
     LEFT JOIN categories ON categories.id = products.category_id
     WHERE $whereSql
     ORDER BY $sortSql",
    $params
);


$heading = $category !== null
    ? $category['name']
    : ($dept === 'lingerie' ? 'Lingerie' : ($dept === 'instruments' ? 'Musical Instruments' : 'All Products'));
$eyebrow = $category !== null
    ? ($category['department'] === 'lingerie' ? 'Exquisite Intimates' : 'Master Sound & Gear')
    : 'The Full Curation';

set_title($heading . ' | Zion Groups');
$seoDesc = $category !== null && ($category['description'] ?? '') !== ''
    ? (string) $category['description']
    : ($dept === 'lingerie'
        ? 'Luxury lingerie in Accra - balconettes, bodysuits, sleepwear and matching sets, shipped discreetly nationwide.'
        : ($dept === 'instruments'
            ? 'Keyboards, synthesizers, guitars, microphones and studio gear from the Zion Groups showroom in Accra.'
            : 'Browse every product in the Zion Groups catalogue - lingerie, instruments and audio gear.'));
set_meta($seoDesc);
render_head();
?>
<main class="w-full pt-24 bg-surface min-h-[calc(100vh-280px)]">
  <div class="max-w-[1360px] mx-auto px-margin py-space-lg">

    <!-- Breadcrumb -->
    <nav class="font-body-sm text-body-sm text-on-surface-variant mb-space-md flex flex-wrap items-center gap-1.5" aria-label="Breadcrumb">
      <a class="hover:text-primary" href="<?= e(url('index.php')) ?>">Home</a>
      <span class="material-symbols-outlined text-sm">chevron_right</span>
      <a class="hover:text-primary" href="<?= e(url('shop.php')) ?>">Shop</a>
      <?php if ($dept !== ''): ?>
        <span class="material-symbols-outlined text-sm">chevron_right</span>
        <a class="hover:text-primary" href="<?= e(url('shop.php?dept=' . $dept)) ?>"><?= e($dept === 'lingerie' ? 'Lingerie' : 'Instruments') ?></a>
      <?php endif; ?>
      <?php if ($category !== null && $category['parent_id'] !== null): ?>
        <span class="material-symbols-outlined text-sm">chevron_right</span>
        <span class="text-on-surface"><?= e($category['name']) ?></span>
      <?php endif; ?>
    </nav>

    <div class="flex flex-col lg:flex-row gap-space-lg">
      <!-- ---------------- filters ---------------- -->
      <aside class="hidden lg:flex lg:w-64 shrink-0 flex-col gap-space-md">
        <?php shop_filters_form('s'); ?>
      </aside>

      <!-- ---------------- grid ---------------- -->
      <div class="lg:flex-1 flex flex-col gap-6 min-w-0">
        <div class="bg-surface-container-lowest p-4 rounded-xl shadow-xs flex flex-wrap items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <p class="font-body-md text-body-md text-on-surface">
              Showing <span class="font-bold text-primary"><?= $total ?></span> items
              <?php if ($heading !== 'All Products'): ?>
                in <span class="font-semibold italic"><?= e($heading) ?></span>
              <?php endif; ?>
            </p>
            <?php if ($q !== ''): ?>
              <span class="inline-block w-1.5 h-1.5 rounded-full bg-outline-variant"></span>
              <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary font-bold">Search: &ldquo;<?= e($q) ?>&rdquo;</span>
            <?php endif; ?>
          </div>
          <div class="flex items-center gap-3">
            <label class="font-label-nav text-label-nav text-on-surface-variant uppercase" for="sort">Sort By:</label>
            <form method="get" action="<?= e(url('shop.php')) ?>">
              <?php foreach (['dept' => $dept, 'cat' => $catSlug, 'q' => $q, 'min' => $minPrice, 'max' => $maxPrice, 'sale' => $onSale ? '1' : ''] as $k => $v): ?>
                <?php if ($v !== '' && $v !== null): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>"/><?php endif; ?>
              <?php endforeach; ?>
              <select id="sort" name="sort" onchange="this.form.submit()"
                      class="appearance-none bg-surface-container px-4 py-2 pr-9 rounded-lg font-body-sm text-body-sm text-on-surface font-medium cursor-pointer focus:outline-none focus:ring-1 focus:ring-primary">
                <?php foreach (['featured' => 'Featured Editorial', 'newest' => 'Newest Arrivals', 'price_asc' => 'Price: Low to High', 'price_desc' => 'Price: High to Low', 'rating' => 'Customer Rating', 'name' => 'Name A-Z'] as $k => $label): ?>
                  <option value="<?= e($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
        </div>

        <?php if ($products === []): ?>
          <div class="bg-surface-container-lowest rounded-xl shadow-xs p-12 text-center">
            <span class="material-symbols-outlined text-4xl text-outline">search_off</span>
            <h2 class="font-headline-md text-headline-md text-on-surface font-bold mt-3">No products match those filters</h2>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">Try widening the price range or clearing the department filter.</p>
            <a class="inline-block mt-5 px-6 py-3 bg-primary-container text-on-primary font-label-nav text-label-nav font-bold uppercase tracking-wider rounded-lg hover:bg-primary" href="<?= e(url('shop.php')) ?>">Browse everything</a>
          </div>
        <?php else: ?>
          <div class="grid <?= e(product_grid_classes()) ?> gap-6">
            <?php foreach ($products as $p): ?>
              <?php product_card($p); ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>
<?php render_foot(); ?>
