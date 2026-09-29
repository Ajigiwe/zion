<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/product_card.php';

$q    = trim((string) ($_GET['q'] ?? ''));
$dept = in_array($_GET['dept'] ?? '', ['lingerie', 'instruments'], true) ? $_GET['dept'] : '';

$where  = ['products.is_active = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(products.name LIKE ? OR products.brand_label LIKE ? OR products.short_description LIKE ? OR products.description LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($dept !== '') {
    $where[] = 'products.department = ?';
    $params[] = $dept;
}
$whereSql = implode(' AND ', $where);

$products = db_all(
    "SELECT products.*, categories.slug AS category_slug, categories.name AS category_name
     FROM products
     LEFT JOIN categories ON categories.id = products.category_id
     WHERE $whereSql
     ORDER BY products.is_featured DESC, products.rating DESC
     LIMIT 60",
    $params
);

set_title(($q !== '' ? 'Search: ' . $q : 'Search') . ' | Zion Groups');
set_meta($q !== '' ? 'Search results for ' . $q . ' in the Zion Groups catalogue.' : 'Search the Zion Groups catalogue.', null, true);
render_head();
?>
<main class="w-full pt-24 bg-surface min-h-[calc(100vh-280px)]">
  <div class="max-w-[1360px] mx-auto px-margin py-space-lg">
    <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Search</span>
    <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-space-md">
      <?php if ($q !== ''): ?>
        Results for &ldquo;<span class="text-primary"><?= e($q) ?></span>&rdquo;
      <?php else: ?>
        What are you looking for?
      <?php endif; ?>
    </h1>

    <form method="get" action="<?= e(url('search.php')) ?>" class="flex gap-2 mb-space-lg max-w-xl">
      <input class="flex-1 h-11 px-4 bg-surface-container-lowest border border-outline-variant rounded-lg font-body-sm text-body-sm focus:border-primary outline-none"
             type="search" name="q" value="<?= e($q) ?>" placeholder="Search lingerie, instruments, audio..."/>
      <button class="px-6 h-11 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary" type="submit">Search</button>
    </form>

    <?php if ($q !== '' && $products === []): ?>
      <div class="bg-surface-container-lowest rounded-xl shadow-xs p-12 text-center">
        <span class="material-symbols-outlined text-4xl text-outline">search_off</span>
        <h2 class="font-headline-md text-headline-md text-on-surface font-bold mt-3">Nothing matched &ldquo;<?= e($q) ?>&rdquo;</h2>
        <p class="font-body-md text-body-md text-on-surface-variant mt-1">Try a brand name, a category such as &ldquo;keyboards&rdquo;, or a product line.</p>
        <div class="flex flex-wrap justify-center gap-3 mt-5">
          <?php foreach (['Lingerie', 'Keyboards', 'Guitars', 'Microphones'] as $sug): ?>
            <a class="px-4 py-2 border border-outline-variant rounded-full font-label-nav text-label-nav uppercase text-on-surface-variant hover:bg-surface-container"
               href="<?= e(url('search.php?q=' . urlencode($sug))) ?>"><?= e($sug) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="grid <?= e(product_grid_classes()) ?> gap-space-lg">
        <?php foreach ($products as $p): ?>
          <?php product_card($p); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php render_foot(); ?>
