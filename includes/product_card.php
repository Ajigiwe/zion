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

        <div class="absolute top-3 right-3 flex gap-2">
          <form method="post" action="<?= e(url('actions.php')) ?>" data-wishlist>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="wishlist"/>
            <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"/>
            <input type="hidden" name="return" value="<?= e(here_url()) ?>"/>
            <button type="submit" aria-label="Add to Wishlist" data-wish-btn
                    class="w-8 h-8 rounded-full bg-surface-container-lowest/80 backdrop-blur-sm hover:bg-surface-container-lowest transition-colors flex items-center justify-center shadow-xs <?= in_wishlist((int) $p['id']) ? 'text-primary' : 'text-on-surface-variant hover:text-primary' ?>">
              <span class="material-symbols-outlined text-base" data-wish-icon><?= in_wishlist((int) $p['id']) ? 'favorite' : 'favorite_border' ?></span>
            </button>
          </form>
          <button type="button" aria-label="Share this product" data-share
                  data-share-url="<?= e(absolute_url('product.php?slug=' . urlencode($p['slug']))) ?>"
                  data-share-title="<?= e($p['name'] . ' | Zion Groups') ?>"
                  class="w-8 h-8 rounded-full bg-surface-container-lowest/80 backdrop-blur-sm hover:bg-surface-container-lowest transition-colors flex items-center justify-center shadow-xs text-on-surface-variant hover:text-primary">
            <span class="material-symbols-outlined text-base">share</span>
          </button>
        </div>

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

          <a href="<?= e(product_whatsapp_url($p)) ?>" target="_blank" rel="noopener"
             class="mt-1.5 inline-flex items-center gap-1.5 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant hover:text-[#25D366] transition-colors"
             aria-label="WhatsApp enquiry about <?= e($p['name']) ?>">
            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 shrink-0" fill="currentColor" aria-hidden="true"><path d="M16.75 13.96c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.79-.79.96-.15.16-.29.18-.54.06-.25-.13-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.01-.38.11-.5.11-.11.25-.29.37-.43.12-.15.16-.25.24-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.43h-.47c-.16 0-.43.06-.65.31-.22.25-.86.84-.86 2.05s.88 2.38 1 2.54c.12.17 1.73 2.64 4.2 3.7.58.26 1.04.41 1.4.52.59.19 1.13.16 1.55.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.17-.47-.29zM12.05 21.79h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.9-9.88 2.64 0 5.12 1.03 6.99 2.9a9.82 9.82 0 0 1 2.89 6.99c0 5.45-4.44 9.89-9.89 9.89zM20.52 3.45A11.82 11.82 0 0 0 12.05 0C5.5 0 .18 5.32.17 11.87c0 2.09.55 4.14 1.59 5.94L.08 24l6.34-1.66a11.88 11.88 0 0 0 5.62 1.43h.01c6.55 0 11.87-5.32 11.88-11.87 0-3.18-1.24-6.16-3.4-8.45z"/></svg>
            WhatsApp Enquiry
          </a>
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
