<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/product_card.php';

$u = current_user();

set_title('My Wishlist | Zion Groups');
set_meta(null, null, true);  // account/order pages are noindex
render_head();
?>
<main class="w-full pt-24 bg-surface min-h-[calc(100vh-280px)]">
  <div class="max-w-[1360px] mx-auto px-margin py-space-lg">
    <nav class="font-label-nav text-label-nav text-on-surface-variant mb-4 flex items-center gap-2" aria-label="Breadcrumb">
      <a class="hover:text-primary" href="<?= e(url('index.php')) ?>">Home</a>
      <span class="text-outline-variant">/</span>
      <span class="text-primary font-semibold">Wishlist</span>
    </nav>

    <?php if ($u === null): ?>
      <div class="max-w-lg mx-auto bg-surface-container-lowest rounded-xl shadow-xs p-12 text-center">
        <span class="material-symbols-outlined text-5xl text-outline">favorite</span>
        <h1 class="font-headline-md text-headline-md text-on-surface font-bold mt-3">Sign in to keep your favourites</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-2">
          Your saved pieces are tied to your Zion account, so they follow you across every device and are ready for private checkout.
        </p>
        <div class="flex flex-wrap justify-center gap-3 mt-5">
          <a class="px-6 py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary"
             href="<?= e(url('login.php?next=' . urlencode('wishlist.php'))) ?>">Sign in</a>
          <a class="px-6 py-3 border border-outline-variant text-on-surface rounded-lg font-label-nav text-label-nav uppercase"
             href="<?= e(url('register.php?next=' . urlencode('wishlist.php'))) ?>">Create account</a>
        </div>
      </div>
    <?php else:
      $ids = wishlist_ids();
      $products = $ids === [] ? [] : db_all(
        'SELECT products.*, categories.slug AS category_slug, categories.name AS category_name
         FROM products
         LEFT JOIN categories ON categories.id = products.category_id
         WHERE products.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
         ORDER BY products.is_featured DESC, products.name',
        $ids
      );
    ?>
      <div class="flex flex-wrap items-end justify-between gap-3 mb-space-md">
        <div>
          <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Saved for later</span>
          <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">My Wishlist</h1>
        </div>
        <span class="font-body-sm text-body-sm text-on-surface-variant"><?= plural(count($products), 'saved piece', 'saved pieces') ?></span>
      </div>

      <?php if ($products === []): ?>
        <div class="bg-surface-container-lowest rounded-xl shadow-xs p-12 text-center">
          <span class="material-symbols-outlined text-4xl text-outline">favorite_border</span>
          <h2 class="font-headline-md text-headline-md text-on-surface font-bold mt-3">Nothing saved yet</h2>
          <p class="font-body-md text-body-md text-on-surface-variant mt-2">Tap the heart on any product to keep it here.</p>
          <div class="flex flex-wrap justify-center gap-3 mt-5">
            <a class="px-6 py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider" href="<?= e(url('shop.php?dept=lingerie')) ?>">Shop Lingerie</a>
            <a class="px-6 py-3 bg-inverse-surface text-surface rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider" href="<?= e(url('shop.php?dept=instruments')) ?>">Shop Instruments</a>
          </div>
        </div>
      <?php else: ?>
        <div class="grid <?= e(product_grid_classes()) ?> gap-space-lg">
          <?php foreach ($products as $p): ?>
            <?php product_card($p); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>
<?php render_foot(); ?>
