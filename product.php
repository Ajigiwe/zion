<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/product_card.php';

$slug = (string) ($_GET['slug'] ?? '');
$p = $slug !== ''
    ? db_one(
        'SELECT products.*, categories.name AS category_name, categories.slug AS category_slug,
                categories.parent_id, brands.name AS brand_name
         FROM products
         LEFT JOIN categories ON categories.id = products.category_id
         LEFT JOIN brands     ON brands.id     = products.brand_id
         WHERE products.slug = ? AND products.is_active = 1',
        [$slug]
    )
    : null;

if ($p === null) {
    http_response_code(404);
    set_title('Product not found | Zion Groups');
    render_head();
    echo '<main class="pt-32 max-w-[1360px] mx-auto px-margin py-16 text-center">'
       . '<h1 class="font-headline-lg text-headline-lg font-bold text-on-surface">Product not found</h1>'
       . '<p class="text-on-surface-variant mt-2">It may have sold out or been removed.</p>'
       . '<a class="inline-block mt-5 px-6 py-3 bg-primary-container text-on-primary rounded-lg font-bold uppercase tracking-wider" href="' . e(url('shop.php')) . '">Back to shop</a>'
       . '</main>';
    render_foot();
    exit;
}

$gallery = db_all('SELECT url FROM product_images WHERE product_id = ? ORDER BY sort_order', [(int) $p['id']]);
$gallery = array_values(array_filter(array_map(fn($r) => trim((string) $r['url']), $gallery)));
// The saved main image always leads the strip, even when the gallery differs.
$lead = trim((string) $p['image_url']);
if ($lead !== '') {
    $gallery = [$lead, ...array_values(array_filter($gallery, static fn($u) => $u !== $lead))];
} elseif ($gallery === []) {
    $gallery = [''];
}
$sizes  = db_all("SELECT value FROM product_variants WHERE product_id = ? AND type = 'size' ORDER BY sort_order", [(int) $p['id']]);
$colors = db_all("SELECT value, hex FROM product_variants WHERE product_id = ? AND type = 'color' ORDER BY sort_order", [(int) $p['id']]);
$specs  = db_all('SELECT label, value FROM product_specs WHERE product_id = ? ORDER BY sort_order', [(int) $p['id']]);
$bundles = db_all('SELECT item_name, item_desc, item_price FROM product_bundles WHERE product_id = ? ORDER BY sort_order', [(int) $p['id']]);

$isLingerie = $p['department'] === 'lingerie';
$compare    = $p['compare_at_price'] !== null ? (float) $p['compare_at_price'] : null;
$savePct    = $compare !== null && $compare > 0 ? (int) round((1 - (float) $p['price'] / $compare) * 100) : 0;
$inStock    = (int) $p['stock'] > 0;

// Honest ratings: aggregated live from the approved review rows.
$rv      = rating_summary((int) $p['id']);
$reviews = product_reviews((int) $p['id'], 20);
$reviewerName = current_user()['name'] ?? '';

$related = db_all(
    'SELECT * FROM products
     WHERE is_active = 1 AND id <> ?
       AND (category_id = ? OR department = ?)
     ORDER BY is_featured DESC, rating DESC
     LIMIT 4',
    [(int) $p['id'], (int) ($p['category_id'] ?? 0), $p['department']]
);

set_title($p['name'] . ' | Zion Groups');
$seoDesc = trim((string) ($p['short_description'] ?? '')) !== ''
    ? (string) $p['short_description']
    : mb_strimwidth(trim((string) $p['description']), 0, 150, '…');
if ($seoDesc !== '') {
    $seoDesc .= ' - ' . price($p['price']) . ($rv['count'] > 0 ? ' - ' . number_format($rv['avg'], 1) . '/5 from ' . plural($rv['count'], 'review', 'reviews') : '');
}
set_meta($seoDesc, $p['image_url'], false, 'product');
set_jsonld(array_filter([
    '@context' => 'https://schema.org',
    '@type'    => 'Product',
    'name'     => (string) $p['name'],
    'sku'      => (string) $p['sku'],
    'image'    => (string) absolute_image_url($p['image_url']),
    'description' => trim((string) $p['description']),
    'brand'    => ['@type' => 'Brand', 'name' => (string) ($p['brand_label'] ?? 'Zion Groups')],
    'aggregateRating' => $rv['count'] > 0 ? [
        '@type'       => 'AggregateRating',
        'ratingValue' => (string) $rv['avg'],
        'reviewCount' => (string) $rv['count'],
        'bestRating'  => '5',
    ] : null,
    'offers' => [
        '@type'         => 'Offer',
        'price'         => (string) (float) $p['price'],
        'priceCurrency' => 'GHS',
        'availability'  => (int) $p['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url'           => absolute_url('product.php?slug=' . urlencode((string) $p['slug'])),
    ],
], fn ($v) => $v !== null));
render_head();

$crumbs = [
    ['Home', url('index.php')],
    ['Shop', url('shop.php')],
];
if ($p['department'] === 'lingerie') {
    $crumbs[] = ['Lingerie', url('shop.php?dept=lingerie')];
    if ($p['category_slug'] !== null) {
        $crumbs[] = [$p['category_name'], url('category.php?slug=' . urlencode($p['category_slug']))];
    }
} else {
    $crumbs[] = ['Instruments', url('shop.php?dept=instruments')];
    if ($p['category_slug'] !== null) {
        $crumbs[] = [$p['category_name'], url('category.php?slug=' . urlencode($p['category_slug']))];
    }
}
?>
<main class="w-full pt-24 bg-surface min-h-[calc(100vh-280px)]">
  <div class="max-w-[1360px] mx-auto w-full px-margin py-space-lg">

    <nav aria-label="Breadcrumb" class="mb-space-lg flex flex-wrap items-center gap-2 font-label-nav text-label-nav text-on-surface-variant">
      <?php foreach ($crumbs as $i => [$label, $href]): ?>
        <?php if ($i > 0): ?><span class="text-outline-variant text-xs">/</span><?php endif; ?>
        <a class="hover:text-primary transition-colors" href="<?= e($href) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <span class="text-outline-variant text-xs">/</span>
      <span class="text-primary font-semibold"><?= e($p['name']) ?></span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-xl items-start">
      <!-- gallery -->
      <div class="lg:col-span-7 flex flex-col-reverse sm:flex-row gap-space-md lg:sticky lg:top-24">
        <div class="flex sm:flex-col gap-space-sm shrink-0 overflow-x-auto sm:overflow-visible pb-2 sm:pb-0" id="thumbStrip">
          <?php foreach ($gallery as $i => $img): ?>
            <button class="thumb-btn relative w-20 h-28 rounded-lg overflow-hidden bg-surface-container transition-all <?= $i === 0 ? 'ring-2 ring-primary' : 'opacity-70 hover:opacity-100' ?>"
                    data-index="<?= $i ?>" type="button" aria-label="View image <?= $i + 1 ?>">
              <img class="w-full h-full object-cover" src="<?= e(img_url($img)) ?>" alt="<?= e($p['name']) ?> view <?= $i + 1 ?>" loading="lazy"/>
            </button>
          <?php endforeach; ?>
        </div>

        <div class="relative flex-1 bg-surface-container-low rounded-xl overflow-hidden shadow-sm aspect-[3/4] group">
          <img class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105"
               id="mainProductImage" src="<?= e(img_url($gallery[0])) ?>" alt="<?= e($p['name']) ?>"/>
          <?php if ($p['badge'] !== null): ?>
            <div class="absolute top-4 left-4 bg-primary text-on-primary font-label-tag text-label-tag tracking-wider uppercase px-3 py-1.5 rounded-lg shadow-sm"><?= e($p['badge']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- buy box -->
      <div class="lg:col-span-5 flex flex-col gap-space-lg">
        <div class="flex flex-col gap-space-xs">
          <div class="flex items-center justify-between gap-2">
            <span class="font-label-tag text-label-tag uppercase tracking-widest text-primary font-bold"><?= e(strtoupper($p['brand_label'])) ?></span>
            <span class="font-label-tag text-label-tag px-2.5 py-0.5 rounded-full <?= $inStock ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-surface-container-high text-on-surface-variant' ?> font-semibold tracking-wider">
              <?= e($p['stock_label'] ?? ($inStock ? 'In Stock' : 'Backorder')) ?>
            </span>
          </div>

          <h1 class="font-headline-lg text-headline-lg text-on-surface tracking-tight"><?= e($p['name']) ?></h1>

          <div class="flex items-center gap-space-sm pt-1">
            <?php if ($rv['count'] > 0): ?>
              <?= stars((float) $rv['avg']) ?>
              <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary underline font-medium" href="#reviews" data-open-reviews>
                <?= e(number_format($rv['avg'], 1)) ?> (<?= plural($rv['count'], 'review', 'reviews') ?>)
              </a>
            <?php else: ?>
              <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary underline font-medium" href="#reviews" data-open-reviews>
                No reviews yet - be the first
              </a>
            <?php endif; ?>
          </div>

          <div class="flex items-baseline gap-space-sm mt-space-xs flex-wrap">
            <span class="font-display-hero text-headline-lg font-bold text-on-surface"><?= e(price($p['price'])) ?></span>
            <?php if ($compare !== null): ?>
              <span class="font-body-sm text-body-sm text-outline line-through"><?= e(price($compare)) ?></span>
              <span class="font-label-tag text-label-tag text-primary-container font-semibold bg-tertiary-fixed px-2 py-0.5 rounded">Save <?= $savePct ?>%</span>
            <?php endif; ?>
          </div>
        </div>

        <form method="post" action="<?= e(url('actions.php')) ?>" id="buyForm" data-add>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_to_cart"/>
          <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"/>
          <input type="hidden" name="return" value="<?= e(here_url()) ?>"/>
          <input type="hidden" name="qty" id="qtyInput" value="1"/>
          <input type="hidden" name="size" id="sizeInput" value="<?= e($sizes[0]['value'] ?? '') ?>"/>
          <input type="hidden" name="color" id="colorInput" value="<?= e($colors[0]['value'] ?? '') ?>"/>

          <?php if ($colors !== []): ?>
            <div class="flex flex-col gap-space-xs pt-space-xs">
              <div class="flex justify-between items-center font-body-sm text-body-sm">
                <span class="text-on-surface-variant font-medium">Color:
                  <strong class="text-on-surface" id="selectedColorLabel"><?= e($colors[0]['value']) ?></strong>
                </span>
                <span class="text-secondary font-medium"><?= $isLingerie ? 'Signature Shade' : 'Finish' ?></span>
              </div>
              <div class="flex items-center gap-3 pt-1 flex-wrap">
                <?php foreach ($colors as $i => $c): ?>
                  <button class="color-swatch w-9 h-9 rounded-full p-0.5 shadow-sm transition-transform hover:scale-110 <?= $i === 0 ? 'ring-2 ring-primary ring-offset-2 ring-offset-surface' : '' ?>"
                          style="background:<?= e($c['hex'] ?? '#999') ?>"
                          data-label="<?= e($c['value']) ?>" type="button" aria-label="<?= e($c['value']) ?>">
                    <span class="sr-only"><?= e($c['value']) ?></span>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($sizes !== []): ?>
            <div class="flex flex-col gap-space-xs pt-space-xs">
              <div class="flex justify-between items-center">
                <span class="font-body-sm text-body-sm text-on-surface-variant font-medium">Select Size:
                  <strong class="text-on-surface" id="activeSizeLabel"><?= e($sizes[0]['value']) ?></strong>
                </span>
                <a class="flex items-center gap-1 font-label-nav text-label-nav text-secondary hover:text-primary transition-colors" href="<?= e(url('page.php?slug=size-guide')) ?>">
                  <span class="material-symbols-outlined text-base">straighten</span>
                  <span class="underline">Size Guide</span>
                </a>
              </div>
              <div class="grid grid-cols-6 gap-2 pt-1" id="sizeGrid">
                <?php foreach ($sizes as $i => $s): ?>
                  <button class="size-btn py-2.5 text-center font-label-nav text-label-nav rounded-lg transition-colors <?= $i === 0 ? 'bg-primary text-on-primary font-bold shadow-sm' : 'bg-surface-container hover:bg-surface-container-high text-on-surface' ?>"
                          data-size="<?= e($s['value']) ?>" type="button"><?= e($s['value']) ?></button>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <div class="flex flex-col gap-space-sm pt-space-xs">
            <div class="flex items-center gap-space-sm">
              <div class="flex items-center bg-surface-container rounded-lg p-1 shrink-0 h-12 shadow-sm">
                <button aria-label="Decrease quantity" class="w-10 h-full flex items-center justify-center text-on-surface hover:text-primary transition-colors" data-qty="-1" type="button">
                  <span class="material-symbols-outlined text-lg">remove</span>
                </button>
                <span class="w-8 text-center font-label-price text-label-price text-on-surface" id="qtyVal">1</span>
                <button aria-label="Increase quantity" class="w-10 h-full flex items-center justify-center text-on-surface hover:text-primary transition-colors" data-qty="1" type="button">
                  <span class="material-symbols-outlined text-lg">add</span>
                </button>
              </div>
              <button class="flex-1 h-12 bg-primary-container text-on-primary font-label-nav text-label-nav uppercase tracking-widest font-bold rounded-lg shadow-md hover:bg-primary transition-all duration-300 flex items-center justify-center gap-2 group active:scale-[0.99] disabled:opacity-50"
                      type="submit" <?= $inStock ? '' : 'disabled' ?>>
                <span class="material-symbols-outlined text-lg transition-transform group-hover:scale-110">shopping_bag</span>
                <?= $inStock ? 'Add to Cart' : 'Notify Me When Back' ?>
              </button>
            </div>
          </div>
        </form>

        <form method="post" action="<?= e(url('actions.php')) ?>" data-wishlist>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="wishlist"/>
          <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"/>
          <input type="hidden" name="return" value="<?= e(here_url()) ?>"/>
          <button class="w-full py-2.5 flex items-center justify-center gap-2 font-label-nav text-label-nav transition-colors <?= in_wishlist((int) $p['id']) ? 'text-primary' : 'text-on-surface-variant hover:text-primary' ?>"
                  type="submit" data-wish-btn>
            <span class="material-symbols-outlined text-lg" data-wish-icon><?= in_wishlist((int) $p['id']) ? 'favorite' : 'favorite_border' ?></span>
            <span data-wish-label><?= in_wishlist((int) $p['id']) ? 'Saved to Wishlist' : 'Save to Wishlist' ?></span>
          </button>
        </form>

        <div class="grid grid-cols-3 gap-space-sm py-space-md bg-surface-container-low rounded-xl px-space-md">
          <?php if ($isLingerie): ?>
            <div class="flex flex-col items-center text-center gap-1.5">
              <span class="material-symbols-outlined text-primary text-2xl">shield</span>
              <span class="font-label-tag text-label-tag font-bold text-on-surface uppercase">Discreet Packaging</span>
              <span class="font-body-sm text-[11px] text-on-surface-variant leading-tight">100% unmarked luxury carton</span>
            </div>
            <div class="flex flex-col items-center text-center gap-1.5">
              <span class="material-symbols-outlined text-secondary text-2xl">cached</span>
              <span class="font-label-tag text-label-tag font-bold text-on-surface uppercase">7-Day Fit Guarantee</span>
              <span class="font-body-sm text-[11px] text-on-surface-variant leading-tight">Hassle-free size exchange</span>
            </div>
            <div class="flex flex-col items-center text-center gap-1.5">
              <span class="material-symbols-outlined text-primary-container text-2xl">verified_user</span>
              <span class="font-label-tag text-label-tag font-bold text-on-surface uppercase">Instant Mobile Pay</span>
              <span class="font-body-sm text-[11px] text-on-surface-variant leading-tight">MTN MoMo &amp; Telecel Cash</span>
            </div>
          <?php else: ?>
            <div class="flex flex-col items-center text-center gap-1.5">
              <span class="material-symbols-outlined text-primary text-2xl">verified_user</span>
              <span class="font-label-tag text-label-tag font-bold text-on-surface uppercase">2-Year Warranty</span>
              <span class="font-body-sm text-[11px] text-on-surface-variant leading-tight">Official regional coverage</span>
            </div>
            <div class="flex flex-col items-center text-center gap-1.5">
              <span class="material-symbols-outlined text-secondary text-2xl">store</span>
              <span class="font-label-tag text-label-tag font-bold text-on-surface uppercase"><?= e(showroom_label()) ?></span>
              <span class="font-body-sm text-[11px] text-on-surface-variant leading-tight">Same-day pickup available</span>
            </div>
            <div class="flex flex-col items-center text-center gap-1.5">
              <span class="material-symbols-outlined text-primary-container text-2xl">workspace_premium</span>
              <span class="font-label-tag text-label-tag font-bold text-on-surface uppercase">100% Authentic</span>
              <span class="font-body-sm text-[11px] text-on-surface-variant leading-tight">Direct authorised importer</span>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($bundles !== []): ?>
          <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-space-md">
            <div class="flex items-center justify-between mb-3">
              <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Pro Sound Package</h2>
              <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary bg-secondary-fixed px-2 py-1 rounded">Bundle &amp; Save</span>
            </div>
            <div class="flex flex-col gap-2">
              <?php foreach ($bundles as $b): ?>
                <div class="flex items-center justify-between gap-3 bg-surface-container rounded-lg px-3 py-2">
                  <div class="min-w-0">
                    <p class="font-body-sm text-body-sm text-on-surface font-semibold truncate"><?= e($b['item_name']) ?></p>
                    <p class="font-body-sm text-[11px] text-on-surface-variant truncate"><?= e($b['item_desc']) ?></p>
                  </div>
                  <span class="font-label-price text-label-price text-on-surface shrink-0">+ <?= e(price($b['item_price'])) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if ($p['bundle_price'] !== null): ?>
              <div class="flex items-center justify-between mt-3 pt-3 border-t border-outline-variant">
                <span class="font-body-sm text-body-sm text-on-surface-variant">Complete bundle</span>
                <span class="font-headline-sm text-headline-sm font-bold text-primary"><?= e(price($p['bundle_price'])) ?></span>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- tabs -->
        <div class="flex flex-col pt-space-xs">
          <div class="flex overflow-x-auto gap-space-md bg-surface-container-high/40 p-1 rounded-lg" role="tablist">
            <?php
            $tabs = [['desc', 'Description', true], ['spec', $isLingerie ? 'Materials &amp; Care' : 'Specifications', false],
                     ['ship', 'Shipping &amp; Returns', false], ['rev', 'Reviews (' . (int) $rv['count'] . ')', false]];
            foreach ($tabs as [$id, $label, $active]): ?>
              <button class="product-tab flex-1 py-2 text-center font-label-nav text-label-nav rounded whitespace-nowrap <?= $active ? 'bg-surface-container-lowest text-primary font-semibold shadow-sm' : 'font-medium text-on-surface-variant hover:text-on-surface' ?>"
                      data-tab="<?= e($id) ?>" type="button"><?= $label ?></button>
            <?php endforeach; ?>
          </div>

          <div class="pt-space-md min-h-[160px]">
            <div class="tab-pane flex flex-col gap-space-sm font-body-md text-body-md text-on-surface-variant" data-pane="desc">
              <?php foreach (preg_split('/\r?\n/', trim((string) $p['description'])) as $line): $line = trim($line); if ($line === '') continue; ?>
                <?php if (str_starts_with($line, '- ')): ?>
                  <div class="flex items-center gap-2 font-body-sm text-body-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary shrink-0"></span>
                    <span><?= e(substr($line, 2)) ?></span>
                  </div>
                <?php else: ?>
                  <p><?= nl2br(e($line)) ?></p>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>

            <div class="tab-pane hidden flex flex-col gap-space-sm font-body-md text-body-md text-on-surface-variant" data-pane="spec">
              <?php if ($specs !== []): ?>
                <div class="grid grid-cols-2 gap-space-sm bg-surface-container-low p-space-sm rounded-lg">
                  <?php foreach (array_slice($specs, 0, 4) as $s): ?>
                    <div>
                      <span class="font-label-tag text-label-tag uppercase text-outline"><?= e($s['label']) ?></span>
                      <p class="font-body-sm text-body-sm text-on-surface font-semibold mt-0.5"><?= e($s['value']) ?></p>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <p class="font-body-sm text-body-sm">
                <?= $isLingerie
                    ? 'Hand wash lukewarm with a gentle silk detergent. Do not wring or tumble dry. Dry flat away from direct sunlight to preserve the lace structure.'
                    : 'Covered by the manufacturer warranty. Free unboxing and setup assistance within Greater Accra and Tema. Service and spares handled through the Accra showroom.' ?>
              </p>
            </div>

            <div class="tab-pane hidden flex flex-col gap-space-sm font-body-md text-body-md text-on-surface-variant" data-pane="ship">
              <div class="flex items-start gap-2">
                <span class="material-symbols-outlined text-primary text-base mt-0.5">local_shipping</span>
                <div><strong class="text-on-surface">Greater Accra Same-Day &amp; Next-Day:</strong> Orders confirmed before 1:00 PM GMT dispatched via discreet courier (<?= e(CURRENCY) ?> 35 flat, or free above <?= e(price(FREE_SHIPPING_THRESHOLD)) ?>).</div>
              </div>
              <div class="flex items-start gap-2">
                <span class="material-symbols-outlined text-secondary text-base mt-0.5">location_on</span>
                <div><strong class="text-on-surface">Regional Deliveries (Kumasi, Takoradi, Tamale):</strong> 48 hours to doorstep or pick-up station.</div>
              </div>
              <div class="flex items-start gap-2">
                <span class="material-symbols-outlined text-primary-container text-base mt-0.5">lock</span>
                <div><strong class="text-on-surface">Total Discretion Policy:</strong> Outer package bears no retail branding. The waybill declares &ldquo;Household Goods&rdquo;.</div>
              </div>
            </div>

            <?php
            // Which reviewers actually bought it, and where they are? One query each.
            $verified = [];
            $places   = [];
            $ownerIds = array_values(array_unique(array_filter(array_map('intval', array_column($reviews, 'user_id')))));
            if ($ownerIds !== []) {
                $ph = implode(',', array_fill(0, count($ownerIds), '?'));
                $vrows = db_all(
                    "SELECT DISTINCT o.user_id
                       FROM order_items oi
                       JOIN orders o ON o.id = oi.order_id
                      WHERE oi.product_id = ? AND o.user_id IN ($ph) AND o.status <> 'cancelled'",
                    array_merge([(int) $p['id']], $ownerIds)
                );
                $verified = array_map('intval', array_column($vrows, 'user_id'));

                $prows = db_all(
                    "SELECT user_id, region FROM orders
                      WHERE user_id IN ($ph) ORDER BY id DESC",
                    $ownerIds
                );
                foreach ($prows as $pr) {
                    $uid = (int) $pr['user_id'];
                    if (!isset($places[$uid]) && $pr['region'] !== null && $pr['region'] !== '') {
                        $places[$uid] = (string) $pr['region'];
                    }
                }
            }
            ?>
            <div class="tab-pane hidden flex flex-col gap-space-md" data-pane="rev" id="reviews">

              <!-- aggregate -->
              <div class="flex flex-col sm:flex-row items-stretch gap-4 bg-surface-container-low p-4 rounded-lg">
                <div class="text-center sm:pr-6 sm:border-r border-outline-variant flex flex-col items-center justify-center">
                  <span class="font-headline-lg text-headline-lg font-bold text-primary"><?= $rv['count'] > 0 ? e(number_format($rv['avg'], 1)) : '-' ?></span>
                  <?= stars((float) $rv['avg']) ?>
                  <span class="font-body-sm text-[11px] text-outline"><?= $rv['count'] > 0 ? plural($rv['count'], 'rating', 'ratings') : 'No ratings yet' ?></span>
                </div>
                <div class="flex-1 flex flex-col gap-1.5 justify-center">
                  <?php for ($s = 5; $s >= 1; $s--):
                      $n    = (int) $rv['dist'][$s];
                      $pct  = $rv['count'] > 0 ? (int) round($n / $rv['count'] * 100) : 0; ?>
                    <div class="flex items-center gap-2 text-xs">
                      <span class="w-8 text-on-surface-variant"><?= $s ?> star</span>
                      <div class="flex-1 bg-surface-container h-1.5 rounded-full overflow-hidden">
                        <div class="bg-primary h-full" style="width:<?= $pct ?>%"></div>
                      </div>
                      <span class="w-8 text-right text-on-surface-variant"><?= $pct ?>%</span>
                    </div>
                  <?php endfor; ?>
                </div>
              </div>

              <!-- list -->
              <?php if ($reviews === []): ?>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                  No reviews on this piece yet. If you own it, share the fit and the finish - it helps the next customer choose.
                </p>
              <?php else: ?>
                <ul class="flex flex-col gap-4">
                  <?php foreach ($reviews as $r):
                      $isVerified = $r['user_id'] !== null && in_array((int) $r['user_id'], $verified, true);
                      $place = $places[(int) $r['user_id']] ?? null; ?>
                    <li class="flex flex-col gap-1 pb-4 border-b border-outline-variant/50 last:border-0 last:pb-0">
                      <div class="flex items-center justify-between gap-3">
                        <span class="font-body-sm text-body-sm font-semibold text-on-surface">
                          <?= e($r['reviewer_name']) ?><?= $place !== null ? ' <span class="font-normal text-outline">(' . e((string) $place) . ')</span>' : '' ?>
                        </span>
                        <span class="font-body-sm text-[11px] text-outline"><?= e(date('d M Y', strtotime((string) $r['created_at']))) ?></span>
                      </div>
                      <div class="flex items-center gap-2">
                        <?= stars((float) $r['rating']) ?>
                        <?php if ($isVerified): ?>
                          <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary">Verified purchase</span>
                        <?php endif; ?>
                      </div>
                      <?php if ($r['title'] !== null && $r['title'] !== ''): ?>
                        <p class="font-body-sm text-body-sm font-semibold text-on-surface"><?= e($r['title']) ?></p>
                      <?php endif; ?>
                      <p class="font-body-sm text-body-sm text-on-surface-variant"><?= nl2br(e($r['body'])) ?></p>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>

              <!-- write a review -->
              <form method="post" action="<?= e(url('actions.php')) ?>" class="flex flex-col gap-3 bg-surface-container-low p-4 rounded-lg" data-review-form>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="submit_review"/>
                <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"/>
                <input type="hidden" name="return" value="<?= e(here_url()) ?>"/>
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
                       class="absolute w-px h-px opacity-0 pointer-events-none" aria-hidden="true"/>

                <div class="flex flex-wrap items-center justify-between gap-2">
                  <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">Write a review</h3>
                  <span class="font-body-sm text-[11px] text-outline">Published after a quick moderation check</span>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                  <div class="flex flex-col gap-1">
                    <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Your rating <span class="text-error">*</span></span>
                    <div class="flex gap-1" id="ratingStars">
                      <?php for ($s = 1; $s <= 5; $s++): ?>
                        <label class="cursor-pointer" title="<?= $s ?> out of 5">
                          <input class="sr-only" type="radio" name="rating" value="<?= $s ?>" required data-star="<?= $s ?>"/>
                          <span class="material-symbols-outlined text-3xl text-outline-variant transition-colors" data-star-icon>star</span>
                        </label>
                      <?php endfor; ?>
                    </div>
                  </div>

                  <?php if ($reviewerName === ''): ?>
                    <label class="flex flex-col gap-1 flex-1 min-w-[180px]">
                      <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Your name <span class="text-error">*</span></span>
                      <input class="h-11 px-3 bg-surface-container-lowest border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                             name="reviewer_name" required maxlength="120" placeholder="Ama Mensah"/>
                    </label>
                  <?php else: ?>
                    <div class="flex flex-col gap-1">
                      <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Posting as</span>
                      <span class="font-body-sm text-body-sm text-on-surface font-semibold"><?= e($reviewerName) ?></span>
                    </div>
                  <?php endif; ?>
                </div>

                <label class="flex flex-col gap-1">
                  <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Headline (optional)</span>
                  <input class="h-11 px-3 bg-surface-container-lowest border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                         name="title" maxlength="160" placeholder="Beautifully made, true to size"/>
                </label>

                <label class="flex flex-col gap-1">
                  <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Your review <span class="text-error">*</span></span>
                  <textarea class="px-3 py-2.5 bg-surface-container-lowest border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none min-h-[96px]"
                            name="body" required minlength="10" maxlength="2000"
                            placeholder="Fit, fabric, sound, delivery - what stood out?"></textarea>
                </label>

                <button class="self-start px-5 py-2.5 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary transition-colors"
                        type="submit">Submit review</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($related !== []): ?>
      <div class="w-full my-space-xl pt-space-lg flex items-center justify-between">
        <div class="flex flex-col">
          <span class="font-label-tag text-label-tag text-secondary uppercase tracking-widest"><?= $isLingerie ? 'Complete The Wardrobe' : 'Complete The Rig' ?></span>
          <h2 class="font-headline-md text-headline-md text-on-surface mt-0.5">You May Also Like</h2>
        </div>
        <a class="hidden sm:inline-flex items-center gap-1 font-label-nav text-label-nav text-primary hover:text-primary-container font-semibold" href="<?= e(url('shop.php?dept=' . $p['department'])) ?>">
          <span>Explore Collection</span><span class="material-symbols-outlined text-base">arrow_forward</span>
        </a>
      </div>
      <div class="grid <?= e(product_grid_classes()) ?> gap-space-md mb-space-xl">
        <?php foreach ($related as $r): ?>
          <?php product_card($r); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<script>
(function () {
  var gallery = <?= json_encode(array_map(fn($u) => img_url($u), $gallery), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var main = document.getElementById('mainProductImage');

  document.querySelectorAll('#thumbStrip .thumb-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      main.src = gallery[Number(btn.dataset.index)];
      document.querySelectorAll('#thumbStrip .thumb-btn').forEach(function (b) {
        b.classList.remove('ring-2', 'ring-primary');
        b.classList.add('opacity-70');
      });
      btn.classList.add('ring-2', 'ring-primary');
      btn.classList.remove('opacity-70');
    });
  });

  document.querySelectorAll('.color-swatch').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('colorInput').value = btn.dataset.label;
      document.getElementById('selectedColorLabel').textContent = btn.dataset.label;
      document.querySelectorAll('.color-swatch').forEach(function (b) {
        b.classList.remove('ring-2', 'ring-primary', 'ring-offset-2', 'ring-offset-surface');
      });
      btn.classList.add('ring-2', 'ring-primary', 'ring-offset-2', 'ring-offset-surface');
    });
  });

  document.querySelectorAll('#sizeGrid .size-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('sizeInput').value = btn.dataset.size;
      document.getElementById('activeSizeLabel').textContent = btn.dataset.size;
      document.querySelectorAll('#sizeGrid .size-btn').forEach(function (b) {
        b.classList.remove('bg-primary', 'text-on-primary', 'font-bold', 'shadow-sm');
        b.classList.add('bg-surface-container', 'hover:bg-surface-container-high', 'text-on-surface');
      });
      btn.classList.remove('bg-surface-container', 'hover:bg-surface-container-high', 'text-on-surface');
      btn.classList.add('bg-primary', 'text-on-primary', 'font-bold', 'shadow-sm');
    });
  });

  var qty = 1;
  document.querySelectorAll('[data-qty]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      qty = Math.max(1, Math.min(20, qty + Number(btn.dataset.qty)));
      document.getElementById('qtyVal').textContent = qty;
      document.getElementById('qtyInput').value = qty;
    });
  });

  document.querySelectorAll('.product-tab').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.product-tab').forEach(function (b) {
        b.classList.remove('bg-surface-container-lowest', 'text-primary', 'font-semibold', 'shadow-sm');
        b.classList.add('font-medium', 'text-on-surface-variant');
      });
      btn.classList.add('bg-surface-container-lowest', 'text-primary', 'font-semibold', 'shadow-sm');
      btn.classList.remove('font-medium', 'text-on-surface-variant');
      document.querySelectorAll('.tab-pane').forEach(function (p) { p.classList.add('hidden'); });
      document.querySelector('.tab-pane[data-pane="' + btn.dataset.tab + '"]').classList.remove('hidden');
    });
  });

  function openTab(tab) {
    var btn = document.querySelector('.product-tab[data-tab="' + tab + '"]');
    if (btn) btn.click();
  }

  document.querySelectorAll('[data-open-reviews]').forEach(function (link) {
    link.addEventListener('click', function () { openTab('rev'); });
  });
  if (location.hash === '#reviews') openTab('rev');
  window.addEventListener('hashchange', function () {
    if (location.hash === '#reviews') openTab('rev');
  });

  // star picker for the review form
  function paintStars(n) {
    document.querySelectorAll('#ratingStars [data-star-icon]').forEach(function (icon, i) {
      var on = i < n;
      icon.textContent = on ? 'star' : 'star_outline';
      icon.classList.toggle('text-secondary', on);
      icon.classList.toggle('text-outline-variant', !on);
    });
  }
  document.querySelectorAll('#ratingStars input[data-star]').forEach(function (input) {
    input.addEventListener('change', function () { paintStars(Number(input.value)); });
  });
  paintStars(0);
})();
</script>
<?php render_foot(); ?>
