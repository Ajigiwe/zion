<?php
/**
 * Reusable product card + batch loaders for card metadata.
 *
 * Usage:  require_once __DIR__ . '/includes/product_card.php';
 *         product_card($product);
 */

declare(strict_types=1);

/** Star row rendered from a 0-5 rating. */
function stars(float $rating): void
{
    $full = (int) floor($rating);
    $half = ($rating - $full) >= 0.5;
    echo '<div class="flex items-center text-secondary">';
    for ($i = 0; $i < 5; $i++) {
        $glyph = $i < $full ? 'star' : ($i === $full && $half ? 'star_half' : 'star');
        $fill  = $i < $full || ($i === $full && $half) ? " style=\"font-variation-settings: 'FILL' 1;\"" : '';
        echo '<span class="material-symbols-outlined text-sm"' . $fill . '>' . $glyph . '</span>';
    }
    echo '</div>';
}

/**
 * @param array $p product row (must include id, slug, name, image_url, price, ...)
 */
function product_card(array $p): void
{
    $isLingerie = $p['department'] === 'lingerie';
    $href       = url('product.php?slug=' . urlencode($p['slug']));
    $badge      = $p['badge'] ?? '';
    $badgeClass = match (true) {
        $badge === 'Sale'                => 'bg-error-container text-on-error-container',
        $badge === 'New', $badge === 'New Arrival' => 'bg-secondary-fixed text-on-secondary-fixed',
        $badge !== '' && $isLingerie     => 'bg-primary-container text-on-primary',
        $badge !== ''                    => 'bg-inverse-surface text-secondary-fixed',
        default                          => 'bg-surface-container-highest text-on-surface',
    };
    ?>
    <article class="group bg-surface-container-lowest rounded-xl overflow-hidden shadow-xs hover:shadow-lg transition-all duration-300 flex flex-col">
      <div class="relative <?= e(card_ratio_classes($p['department'])) ?> bg-surface-container overflow-hidden">
        <a href="<?= e($href) ?>" class="block w-full h-full">
          <img class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500"
               src="<?= e(img_url($p['image_url'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy"/>
        </a>
        <?php if ($badge !== ''): ?>
          <span class="absolute top-3 left-3 <?= e($badgeClass) ?> font-label-tag text-label-tag uppercase px-2.5 py-1 rounded tracking-wider shadow-sm font-bold"><?= e($badge) ?></span>
        <?php endif; ?>

        <form method="post" action="<?= e(url('actions.php')) ?>" class="absolute top-3 right-3" data-wishlist>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="wishlist"/>
          <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"/>
          <input type="hidden" name="return" value="<?= e(here_url()) ?>"/>
          <button type="submit" aria-label="Add to Wishlist" data-wish-btn
                  class="w-8 h-8 rounded-full bg-surface-container-lowest/80 backdrop-blur-sm hover:bg-surface-container-lowest transition-colors flex items-center justify-center shadow-xs <?= in_wishlist((int) $p['id']) ? 'text-primary' : 'text-on-surface-variant hover:text-primary' ?>">
            <span class="material-symbols-outlined text-base" data-wish-icon><?= in_wishlist((int) $p['id']) ? 'favorite' : 'favorite_border' ?></span>
          </button>
        </form>

        <form method="post" action="<?= e(url('actions.php')) ?>" class="absolute inset-x-3 bottom-3 opacity-0 group-hover:opacity-100 transition-opacity duration-200" data-add>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_to_cart"/>
          <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"/>
          <input type="hidden" name="return" value="<?= e(here_url()) ?>"/>
          <input type="hidden" name="qty" value="1"/>
          <button type="submit" class="w-full py-2.5 bg-inverse-surface/90 backdrop-blur text-surface hover:bg-primary-container text-label-nav font-label-nav uppercase tracking-wider rounded font-medium transition-colors shadow-sm flex items-center justify-center gap-1.5">
            <span class="material-symbols-outlined text-base"><?= $isLingerie ? 'shopping_bag' : 'shopping_cart' ?></span>
            <?= $isLingerie ? 'Quick Add' : 'Add to Cart' ?>
          </button>
        </form>
      </div>

      <div class="p-4 flex flex-col flex-1 gap-3">
        <div>
          <div class="flex items-center justify-between text-body-sm mb-1 gap-2">
            <span class="font-label-tag text-label-tag text-secondary uppercase tracking-widest font-semibold truncate"><?= e($p['brand_label']) ?></span>
            <div class="flex items-center gap-1 text-secondary shrink-0">
              <?= stars((float) $p['rating']) ?>
              <span class="font-body-sm text-body-sm font-semibold text-on-surface"><?= e(number_format((float) $p['rating'], 1)) ?></span>
              <span class="text-outline text-xs">(<?= (int) $p['review_count'] ?>)</span>
            </div>
          </div>

          <a href="<?= e($href) ?>">
            <h3 class="font-headline-sm text-headline-sm text-base text-on-surface group-hover:text-primary transition-colors line-clamp-1"><?= e($p['name']) ?></h3>
          </a>

          <p class="font-label-price text-label-price font-bold mt-1 <?= $isLingerie ? 'text-primary' : 'text-on-surface' ?>">
            <?= e(price($p['price'])) ?>
            <?php if ($p['compare_at_price'] !== null): ?>
              <span class="text-outline font-normal text-body-sm line-through ml-1"><?= e(price($p['compare_at_price'])) ?></span>
            <?php endif; ?>
          </p>
        </div>

        <div class="mt-auto pt-2 border-t border-outline-variant/60 flex items-center justify-between gap-2 min-h-[30px]">
          <span class="font-body-sm text-body-sm text-outline truncate"><?= e($p['stock_label'] ?? '') ?></span>
          <span class="text-xs <?= $isLingerie ? 'text-primary bg-primary-fixed' : 'text-secondary-fixed-variant bg-secondary-fixed' ?> font-label-tag font-semibold uppercase tracking-wider px-2 py-1 rounded shrink-0">
            <?= ((int) $p['stock'] > 0) ? 'In Stock' : 'Backorder' ?>
          </span>
        </div>
      </div>
    </article>
    <?php
}
