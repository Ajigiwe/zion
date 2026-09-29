<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/product_card.php';
require_once __DIR__ . '/includes/cart_promo.php';

$cart = cart_totals();
$promo = !empty($_SESSION['promo_code']) ? promo_lookup((string) $_SESSION['promo_code']) : null;
$discount = $promo !== null ? promo_discount($promo, $cart['subtotal']) : 0.0;
$total = max(0.0, $cart['subtotal'] - $discount + $cart['shipping']);

set_title('Shopping Bag | Zion Groups');
set_meta(null, null, true);  // account/order pages are noindex
render_head();
?>
<main class="w-full pt-24 bg-surface min-h-[calc(100vh-280px)]">
  <div class="max-w-[1360px] mx-auto px-margin py-space-lg">

    <a class="inline-flex items-center gap-1.5 font-label-nav text-label-nav text-on-surface-variant hover:text-primary transition-colors mb-space-md" href="<?= e(url('shop.php')) ?>">
      <span class="material-symbols-outlined text-base">arrow_back</span> Return to Boutique
    </a>

    <div class="flex items-end justify-between flex-wrap gap-3 mb-space-lg">
      <div>
        <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Step 1 of 3</span>
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Shopping Cart</h1>
      </div>
      <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-container-high font-body-sm text-body-sm text-on-surface-variant">
        <span class="material-symbols-outlined text-secondary text-base">verified_user</span>
        Items reserved in your private session for 45 minutes
      </span>
    </div>

    <?php if ($cart['rows'] === []): ?>
      <div class="bg-surface-container-lowest rounded-xl shadow-xs p-12 text-center">
        <span class="material-symbols-outlined text-5xl text-outline">shopping_bag</span>
        <h2 class="font-headline-md text-headline-md text-on-surface font-bold mt-3">Your bag is empty</h2>
        <p class="font-body-md text-body-md text-on-surface-variant mt-1">Curate your first pick from the atelier or the sound room.</p>
        <div class="flex flex-wrap justify-center gap-3 mt-5">
          <a class="px-6 py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary" href="<?= e(url('shop.php?dept=lingerie')) ?>">Shop Lingerie</a>
          <a class="px-6 py-3 border border-outline-variant text-on-surface rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-surface-container" href="<?= e(url('shop.php?dept=instruments')) ?>">Shop Instruments</a>
        </div>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-space-lg items-start">

        <div class="lg:col-span-2 flex flex-col gap-space-md">
          <div class="flex items-end justify-between flex-wrap gap-2">
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">
              Your Selected Atelier Items
              <span class="font-body-sm text-body-sm text-on-surface-variant font-normal" data-count>(<?= cart_count() ?> items)</span>
            </h2>
          </div>

          <form method="post" action="<?= e(url('actions.php')) ?>" data-cart-update>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_cart"/>

            <div class="flex flex-col gap-space-md">
              <?php foreach ($cart['rows'] as $row): ?>
                <?php
                $pid = (int) $row['product_id'];
                $lineTotal = (float) $row['unit_price'] * (int) $row['qty'];
                $href = url('product.php?slug=' . urlencode($row['slug']));
                $variant = trim(implode(' | ', array_filter([
                    $row['department'] === 'lingerie' ? 'Lingerie' : strtoupper(strtok((string) $row['brand_label'], ' ')),
                    trim(implode(' ', array_filter([$row['variant_color'] ?? null, $row['variant_size'] !== null && $row['variant_size'] !== '' ? 'Size: ' . $row['variant_size'] : null]))),
                ], static fn($v) => $v !== '' && $v !== null)));
                ?>
                <div class="bg-surface-container-lowest rounded-xl shadow-xs p-4 flex gap-4" data-line="<?= (int) $row['id'] ?>">
                  <a href="<?= e($href) ?>" class="shrink-0">
                    <img class="w-20 h-24 sm:w-24 sm:h-28 object-cover rounded-lg bg-surface-container" src="<?= e(img_url($row['image_url'])) ?>" alt="<?= e($row['name']) ?>"/>
                  </a>

                  <div class="flex-1 min-w-0 flex flex-col">
                    <div class="flex items-start justify-between gap-3">
                      <div class="min-w-0">
                        <span class="font-label-tag text-label-tag text-secondary uppercase tracking-wider"><?= e($row['brand_label']) ?></span>
                        <a href="<?= e($href) ?>" class="block font-headline-sm text-headline-sm text-on-surface font-semibold hover:text-primary transition-colors truncate"><?= e($row['name']) ?></a>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5 truncate"><?= e($variant) ?></p>
                        <span class="mt-2 inline-flex items-center gap-1 px-2 py-0.5 rounded bg-surface-container font-label-tag text-label-tag uppercase tracking-wider text-on-surface-variant">
                          <span class="material-symbols-outlined text-secondary text-sm">verified</span>
                          <?= $row['department'] === 'lingerie' ? 'Discreet pack' : 'Warranty included' ?>
                        </span>
                      </div>
                      <div class="text-right shrink-0">
                        <p class="font-label-price text-label-price text-on-surface font-bold" data-line-total="<?= (int) $row['id'] ?>"><?= e(price($lineTotal)) ?></p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5"><?= e(price($row['unit_price'])) ?> each</p>
                      </div>
                    </div>

                    <div class="mt-auto pt-3 border-t border-outline-variant/60 flex flex-wrap items-center justify-between gap-3">
                      <div class="flex items-center border border-outline-variant rounded-lg overflow-hidden">
                        <button class="w-8 h-9 flex items-center justify-center text-on-surface-variant hover:text-primary" type="button" data-step="-1" data-target="<?= (int) $row['id'] ?>" aria-label="Decrease">
                          <span class="material-symbols-outlined text-base">remove</span>
                        </button>
                        <input class="qty-input w-10 h-9 text-center font-label-price text-label-price text-on-surface border-x border-outline-variant bg-surface-container"
                               type="number" min="0" max="<?= (int) $row['stock'] ?>" value="<?= (int) $row['qty'] ?>"
                               name="qty[<?= (int) $row['id'] ?>]" data-qty-input="<?= (int) $row['id'] ?>"/>
                        <button class="w-8 h-9 flex items-center justify-center text-on-surface-variant hover:text-primary" type="button" data-step="1" data-target="<?= (int) $row['id'] ?>" aria-label="Increase">
                          <span class="material-symbols-outlined text-base">add</span>
                        </button>
                      </div>

                      <div class="flex items-center gap-4">
                        <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary flex items-center gap-1" href="<?= e(url('wishlist.php')) ?>">
                          <span class="material-symbols-outlined text-sm">favorite_border</span> Save for later
                        </a>
                        <button class="font-body-sm text-body-sm text-error hover:underline flex items-center gap-1" type="submit"
                                form="remove-<?= (int) $row['id'] ?>">
                          <span class="material-symbols-outlined text-sm">close</span> Remove
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </form>

          <?php foreach ($cart['rows'] as $row): ?>
            <form id="remove-<?= (int) $row['id'] ?>" method="post" action="<?= e(url('actions.php')) ?>" data-cart-remove>
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove_cart"/>
              <input type="hidden" name="cart_id" value="<?= (int) $row['id'] ?>"/>
            </form>
          <?php endforeach; ?>

          <?= promo_block_html($promo, $discount) ?>
        </div>

        <!-- summary -->
        <aside class="flex flex-col gap-space-md lg:sticky lg:top-24">
          <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
            <div class="flex items-center justify-between mb-3">
              <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Order Summary</h2>
              <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary bg-secondary-fixed px-2 py-1 rounded">Prestige Checkout</span>
            </div>

            <dl class="flex flex-col gap-2 font-body-sm text-body-sm">
              <div class="flex items-center justify-between"><dt class="text-on-surface-variant">Subtotal</dt><dd class="font-semibold text-on-surface" data-sum="subtotal"><?= e(price($cart['subtotal'])) ?></dd></div>
              <div class="flex items-center justify-between <?= $discount > 0 ? '' : 'hidden' ?>" data-voucher-row>
                <dt class="text-on-surface-variant">Voucher (<span data-sum="voucher-code"><?= $promo !== null ? e($promo['code']) : '' ?></span>)</dt>
                <dd class="font-semibold text-primary">&minus; <span data-sum="discount"><?= e(price($discount)) ?></span></dd>
              </div>
              <div class="flex items-center justify-between">
                <dt class="text-on-surface-variant">Shipping</dt>
                <dd class="font-semibold <?= $cart['shipping'] == 0 ? 'text-secondary' : 'text-on-surface' ?>" data-sum="shipping">
                  <?= $cart['shipping'] == 0 ? 'FREE' : e(price($cart['shipping'])) ?>
                </dd>
              </div>
              <div class="flex items-center justify-between pt-3 mt-1 border-t border-outline-variant">
                <dt class="font-headline-sm text-headline-sm text-on-surface font-bold">Total</dt>
                <dd class="font-headline-sm text-headline-sm text-primary font-bold" data-sum="total"><?= e(price($total)) ?></dd>
              </div>
            </dl>

            <p class="mt-3 font-body-sm text-body-sm text-on-surface-variant bg-surface-container rounded-lg px-3 py-2 <?= $cart['subtotal'] >= FREE_SHIPPING_THRESHOLD ? 'hidden' : '' ?>"
               data-freehint>
              Add <span data-sum="gap"><?= e(price(FREE_SHIPPING_THRESHOLD - $cart['subtotal'])) ?></span> more for complimentary Accra &amp; Kumasi express delivery.
            </p>

            <a class="mt-4 w-full inline-flex items-center justify-center gap-2 py-3.5 bg-primary-container text-on-primary font-label-nav text-label-nav font-bold uppercase tracking-widest rounded-lg shadow-md hover:bg-primary transition-colors"
               href="<?= e(url('checkout.php')) ?>">
              <span class="material-symbols-outlined text-lg">lock</span> Proceed to Checkout
            </a>

            <p class="mt-3 flex items-start gap-2 font-body-sm text-body-sm text-on-surface-variant bg-surface-container rounded-lg px-3 py-2">
              <span class="material-symbols-outlined text-secondary text-base mt-0.5">local_shipping</span>
              <span><strong class="text-on-surface">Unlocked:</strong> complimentary express courier across Greater Accra &amp; Kumasi.</span>
            </p>

            <div class="mt-3 flex items-start gap-2 bg-surface-container-low rounded-lg p-3">
              <span class="material-symbols-outlined text-secondary text-base">enhanced_encryption</span>
              <p class="font-body-sm text-body-sm text-on-surface-variant">
                <strong class="text-on-surface">Discreet Packaging Promise.</strong>
                All intimate apparel is packed in plain, unmarked luxury kraft cartons. Couriers and neighbours will never know what is inside.
              </p>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-2">
            <?php foreach ([['local_shipping', 'Nationwide courier'], ['verified_user', 'Authentic goods'], ['lock', 'Momo & card secure']] as [$icon, $label]): ?>
              <div class="bg-surface-container-lowest rounded-xl shadow-xs p-3 flex flex-col items-center text-center gap-1">
                <span class="material-symbols-outlined text-primary text-xl"><?= e($icon) ?></span>
                <span class="font-label-tag text-label-tag uppercase tracking-wider text-on-surface-variant leading-tight"><?= e($label) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </aside>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php render_foot(); ?>
