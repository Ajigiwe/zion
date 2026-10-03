<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// Handle order submission directly on checkout.php
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    require __DIR__ . '/checkout_handler.php';
    exit;
}

$cart = cart_totals();
if ($cart['rows'] === []) {
    flash_set('info', 'Your bag is empty.');
    header('Location: ' . url('cart.php'));
    exit;
}

$promo = !empty($_SESSION['promo_code']) ? promo_lookup((string) $_SESSION['promo_code']) : null;
$discount = $promo !== null ? promo_discount($promo, $cart['subtotal']) : 0.0;

$u = current_user();
$addresses = [];
if ($u !== null) {
    $addresses = db_all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC', [(int) $u['id']]);
}
$defaultAddress = $addresses[0] ?? null;

$regions = [
    'Greater Accra Region', 'Ashanti Region (Kumasi Metro)', 'Western Region (Takoradi)',
    'Central Region (Cape Coast)', 'Eastern Region (Koforidua)', 'Volta Region (Ho)',
    'Northern Region (Tamale)',
];

$channels = [
    'paystack' => ['Paystack', 'Card, bank transfer & mobile money - secured by Paystack', 'PAYSTACK', 'bg-[#0b6bcb] text-white'],
    'cod'      => ['Pay on Delivery', 'Pay the rider in cash or MoMo when your order arrives', 'COD', 'bg-surface-container-high text-on-surface'],
];

set_title('Checkout | Zion Groups');
set_meta(null, null, true);  // account/order pages are noindex
render_head();

$pre = [
    'name'    => $defaultAddress['recipient'] ?? $u['name'] ?? '',
    'phone'   => $defaultAddress['phone'] ?? $u['phone'] ?? '',
    'email'   => $defaultAddress['email'] ?? $u['email'] ?? '',
    'region'  => $defaultAddress['region'] ?? 'Greater Accra Region',
    'city'    => $defaultAddress['city'] ?? '',
    'address' => $defaultAddress['street'] ?? '',
];

// Server-side first paint of the summary so it is correct before the JS runs.
[, , $initShip] = shipping_quote($pre['region'], 'metro');
$initTotal = max(0.0, $cart['subtotal'] - $discount + $initShip);
?>
<main class="w-full pt-24 bg-surface min-h-[calc(100vh-280px)]">
  <div class="max-w-[1360px] mx-auto px-margin py-space-lg">

    <div class="flex items-center justify-between flex-wrap gap-4 mb-space-lg">
      <div>
        <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Step 2 of 3</span>
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Prestige Checkout</h1>
      </div>
      <ol class="flex items-center gap-space-sm font-label-nav text-label-nav uppercase tracking-wider">
        <li class="flex items-center gap-1.5 text-on-surface-variant"><span class="w-6 h-6 rounded-full bg-surface-container-high text-on-surface flex items-center justify-center text-xs font-bold">1</span> Delivery</li>
        <li class="text-outline-variant">&rarr;</li>
        <li class="flex items-center gap-1.5 text-primary font-bold"><span class="w-6 h-6 rounded-full bg-primary text-on-primary flex items-center justify-center text-xs font-bold">2</span> Payment</li>
        <li class="text-outline-variant">&rarr;</li>
        <li class="flex items-center gap-1.5 text-on-surface-variant"><span class="w-6 h-6 rounded-full bg-surface-container-high text-on-surface flex items-center justify-center text-xs font-bold">3</span> Confirmation</li>
      </ol>
    </div>

    <?php if ($u === null): ?>
      <div class="mb-space-md flex flex-wrap items-center justify-between gap-3 bg-surface-container-high rounded-xl px-4 py-3">
        <p class="font-body-sm text-body-sm text-on-surface-variant">
          <strong class="text-on-surface">Checking out as a guest.</strong> Sign in to track deliveries, save addresses and earn Zion Club points.
        </p>
        <div class="flex gap-2">
          <a class="px-4 py-2 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider" href="<?= e(url('login.php') . '?next=' . urlencode('checkout.php')) ?>">Sign In</a>
          <a class="px-4 py-2 border border-outline-variant rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider text-on-surface-variant" href="<?= e(url('register.php')) ?>">Create Account</a>
        </div>
      </div>
    <?php endif; ?>

    <form id="checkoutForm" method="post" action="<?= e(url('checkout.php')) ?>" class="grid grid-cols-1 lg:grid-cols-3 gap-space-lg items-start">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="place_order"/>
      <input type="hidden" name="return" value="<?= e(url('checkout.php')) ?>"/>

      <div class="lg:col-span-2 flex flex-col gap-space-lg">

        <!-- 01 delivery -->
        <section class="bg-surface-container-lowest rounded-xl shadow-xs p-5 sm:p-6">
          <div class="flex items-center gap-3 mb-5">
            <span class="font-label-tag text-label-tag text-secondary bg-secondary-fixed px-2 py-1 rounded">01</span>
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Delivery Information</h2>
            <span class="font-body-sm text-body-sm text-on-surface-variant hidden sm:inline">&mdash; Accra &amp; Nationwide</span>
          </div>

          <div class="flex flex-col gap-5">
            <!-- contact -->
            <div>
              <p class="font-label-nav text-label-nav text-on-surface uppercase tracking-wider mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary text-base">person</span> Contact
              </p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="flex flex-col gap-1.5">
                  <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Full Name <span class="text-error">*</span></span>
                  <input class="h-11 px-3 bg-surface-container-low border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary focus:ring-2 focus:ring-primary/15 outline-none"
                         name="name" required value="<?= e($pre['name']) ?>" autocomplete="name"/>
                </label>

                <label class="flex flex-col gap-1.5">
                  <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Phone <span class="text-error">*</span></span>
                  <div class="flex">
                    <span class="h-11 px-3 flex items-center bg-surface-container border border-outline-variant border-r-0 rounded-l font-body-sm text-body-sm text-on-surface-variant">+233</span>
                    <input class="h-11 flex-1 px-3 bg-surface-container-low border border-outline-variant rounded-r font-body-sm text-body-sm focus:border-primary outline-none"
                           name="phone" required value="<?= e($pre['phone']) ?>" placeholder="24 492 8812" inputmode="tel" autocomplete="tel"/>
                  </div>
                </label>

                <label class="flex flex-col gap-1.5 sm:col-span-2">
                  <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Email (for your VAT invoice)</span>
                  <input class="h-11 px-3 bg-surface-container-low border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                         type="email" name="email" value="<?= e($pre['email']) ?>" autocomplete="email"/>
                </label>
              </div>
            </div>

            <!-- address -->
            <div class="pt-5 border-t border-outline-variant/60">
              <p class="font-label-nav text-label-nav text-on-surface uppercase tracking-wider mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary text-base">location_on</span> Delivery Address
              </p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="flex flex-col gap-1.5">
                  <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Region <span class="text-error">*</span></span>
                  <select class="h-11 px-3 bg-surface-container-low border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="region" required>
                    <?php foreach ($regions as $r): ?>
                      <option value="<?= e($r) ?>" <?= $pre['region'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>

                <label class="flex flex-col gap-1.5">
                  <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">City / Neighborhood <span class="text-error">*</span></span>
                  <input class="h-11 px-3 bg-surface-container-low border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                         name="city" required value="<?= e($pre['city']) ?>" placeholder="East Legon"/>
                </label>

                <label class="flex flex-col gap-1.5 sm:col-span-2">
                  <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Street Address &amp; Landmark <span class="text-error">*</span></span>
                  <input class="h-11 px-3 bg-surface-container-low border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                         name="address" required value="<?= e($pre['address']) ?>" placeholder="No. 14 Boundary Road, Behind Mensvic Grand Hotel, East Legon"/>
                </label>
              </div>
            </div>

            <!-- dispatch -->
            <div class="pt-5 border-t border-outline-variant/60">
              <p class="font-label-nav text-label-nav text-on-surface uppercase tracking-wider mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary text-base">local_shipping</span> Dispatch Method
              </p>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <?php
                $metroFee = (float) setting('shipping_metro_fee', (string) SHIPPING_METRO);
                $regionalFee = (float) setting('shipping_regional_fee', (string) SHIPPING_REGIONAL);
                $pickupFee = (float) setting('shipping_pickup_fee', '0.00');

                $methods = [
                    [
                        'metro',
                        setting('shipping_metro_tag', 'Fastest'),
                        setting('shipping_metro_title', 'Accra Express'),
                        setting('shipping_metro_desc', 'Same-Day / 24 hrs'),
                        $metroFee <= 0 ? 'FREE' : price($metroFee),
                    ],
                    [
                        'regional',
                        setting('shipping_regional_tag', 'Inter-City'),
                        setting('shipping_regional_title', 'Regional Road'),
                        setting('shipping_regional_desc', 'Kumasi / Takoradi'),
                        $regionalFee <= 0 ? 'FREE' : price($regionalFee),
                    ],
                    [
                        'pickup',
                        setting('shipping_pickup_tag', 'Self Pick'),
                        setting('shipping_pickup_title', setting('address_line')),
                        setting('shipping_pickup_desc', 'Ready in 2 Hours'),
                        $pickupFee <= 0 ? 'FREE' : price($pickupFee),
                    ],
                ];
                foreach ($methods as $i => [$val, $tag, $title, $sub, $fee]): ?>
                  <label class="relative flex flex-col gap-0.5 p-3 pr-9 border rounded-xl cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary/5 transition-colors <?= $i === 0 ? 'border-primary bg-primary/5' : 'border-outline-variant' ?>">
                    <input class="absolute top-3 right-3 accent-primary" type="radio" name="shipping_method" value="<?= e($val) ?>" <?= $i === 0 ? 'checked' : '' ?>/>
                    <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary"><?= e($tag) ?></span>
                    <span class="font-headline-sm text-headline-sm text-on-surface font-bold truncate"><?= e($title) ?></span>
                    <span class="font-body-sm text-body-sm text-on-surface-variant"><?= e($sub) ?></span>
                    <span class="mt-1 font-label-price text-label-price <?= $fee === 'FREE' ? 'text-secondary' : 'text-on-surface' ?>"><?= e($fee) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <label class="flex items-start gap-2.5 bg-surface-container-low rounded-lg p-3 cursor-pointer">
              <input type="checkbox" name="discreet_pack" value="1" <?= setting('discreet_packaging_default', '1') === '1' ? 'checked' : '' ?> class="accent-primary mt-0.5"/>
              <span class="font-body-sm text-body-sm text-on-surface-variant">
                <strong class="text-on-surface"><?= e(setting('discreet_packaging_title', 'Discreet Packaging Guaranteed (checked by default).')) ?></strong>
                <?= e(setting('discreet_packaging_desc', 'Intimate apparel ships in unmarked, plain luxury charcoal boxes with no reference to lingerie on the courier airway bill.')) ?>
              </span>
            </label>
          </div>
        </section>

        <!-- 02 payment -->
        <section class="bg-surface-container-lowest rounded-xl shadow-xs p-5 sm:p-6">
          <div class="flex items-center justify-between flex-wrap gap-2 mb-5">
            <div class="flex items-center gap-3">
              <span class="font-label-tag text-label-tag text-secondary bg-secondary-fixed px-2 py-1 rounded">02</span>
              <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Payment Method</h2>
            </div>
            <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary flex items-center gap-1">
              <span class="material-symbols-outlined text-sm">lock</span> 256-Bit SSL Encrypted
            </span>
          </div>

          <div class="flex flex-col gap-3">
            <?php foreach ($channels as $key => [$label, $hint, $badge, $badgeCls]): ?>
              <div class="pay-card border rounded-xl overflow-hidden transition-colors <?= $key === 'paystack' ? 'border-primary bg-surface-container-low' : 'border-outline-variant' ?>" data-channel="<?= e($key) ?>">
                <label class="flex items-center gap-3 p-3.5 cursor-pointer">
                  <input class="accent-primary" type="radio" name="payment_channel" value="<?= e($key) ?>" <?= $key === 'paystack' ? 'checked' : '' ?> data-pay-radio/>
                  <span class="shrink-0 px-2.5 py-1 rounded font-label-tag text-label-tag font-bold <?= e($badgeCls) ?>"><?= e($badge) ?></span>
                  <span class="min-w-0">
                    <span class="block font-headline-sm text-headline-sm text-on-surface font-bold"><?= e($label) ?></span>
                    <span class="block font-body-sm text-body-sm text-on-surface-variant"><?= e($hint) ?></span>
                  </span>
                </label>

                <div class="pay-panel px-4 pb-4 flex flex-col gap-2 <?= $key === 'paystack' ? '' : 'hidden' ?>">
                  <?php if ($key === 'paystack'): ?>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">
                      A secure Paystack popup will open on your screen to pay with Mobile Money (MTN MoMo, Telecel Cash, AT Money) or Visa/Mastercard. Your order is confirmed the moment payment clears.
                    </p>
                    <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary">Instant popup &bull; No card details touch our servers</span>
                  <?php else: ?>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Pay the rider in cash or by MoMo transfer on delivery. Available in Greater Accra, Kumasi and Takoradi.</p>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- 03 notes -->
        <section class="bg-surface-container-lowest rounded-xl shadow-xs p-5 sm:p-6">
          <div class="flex items-center gap-3 mb-4">
            <span class="font-label-tag text-label-tag text-secondary bg-secondary-fixed px-2 py-1 rounded">03</span>
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Order Notes</h2>
            <span class="font-body-sm text-body-sm text-on-surface-variant">(optional)</span>
          </div>
          <textarea class="w-full px-3 py-2.5 bg-surface-container-low border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none min-h-[84px]"
                    name="notes" placeholder="Delivery instructions, gate codes, preferred call time..."></textarea>
        </section>
      </div>

      <!-- summary -->
      <aside class="flex flex-col gap-space-md lg:sticky lg:top-24">
        <div class="bg-surface-container-lowest rounded-xl shadow-xs p-5 sm:p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Your Order</h2>
            <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary bg-secondary-fixed px-2 py-1 rounded"><?= (int) count($cart['rows']) ?> <?= count($cart['rows']) === 1 ? 'item' : 'items' ?></span>
          </div>

          <ul class="flex flex-col gap-3 max-h-72 overflow-y-auto scroll-thin pr-1 pb-1">
            <?php foreach ($cart['rows'] as $row): ?>
              <li class="flex items-start gap-3">
                <img class="w-12 h-14 object-cover rounded bg-surface-container shrink-0" src="<?= e(img_url($row['image_url'])) ?>" alt="<?= e($row['name']) ?>"/>
                <div class="min-w-0 flex-1">
                  <p class="font-body-sm text-body-sm text-on-surface font-semibold truncate"><?= e($row['name']) ?></p>
                  <p class="font-body-sm text-[11px] text-on-surface-variant truncate">
                    <?= e(trim(implode(' - ', array_filter([$row['variant_color'], $row['variant_size']]))) ?: $row['brand_label']) ?>
                  </p>
                  <p class="font-body-sm text-[11px] text-outline">Qty <?= (int) $row['qty'] ?></p>
                </div>
                <span class="font-label-price text-label-price text-on-surface shrink-0"><?= e(price((float) $row['unit_price'] * (int) $row['qty'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>

          <dl class="mt-4 pt-4 border-t border-outline-variant flex flex-col gap-2 font-body-sm text-body-sm">
            <div class="flex justify-between"><dt class="text-on-surface-variant">Subtotal</dt><dd class="font-semibold"><?= e(price($cart['subtotal'])) ?></dd></div>
            <?php if ($discount > 0): ?>
              <div class="flex justify-between"><dt class="text-on-surface-variant">Voucher <?= e($promo['code']) ?></dt><dd class="font-semibold text-primary">&minus; <?= e(price($discount)) ?></dd></div>
            <?php endif; ?>
            <div class="flex justify-between">
              <dt class="text-on-surface-variant">Shipping</dt>
              <dd class="font-semibold" id="shippingSummary"><?= $initShip == 0 ? 'FREE' : e(price($initShip)) ?></dd>
            </div>
            <div class="flex justify-between pt-3 border-t border-outline-variant">
              <dt class="font-headline-sm text-headline-sm text-on-surface font-bold">Total</dt>
              <dd class="font-headline-sm text-headline-sm text-primary font-bold" id="orderTotal"><?= e(price($initTotal)) ?></dd>
            </div>
          </dl>

          <button id="checkoutSubmitBtn" class="mt-4 w-full inline-flex items-center justify-center gap-2 py-3.5 bg-primary-container text-on-primary font-label-nav text-label-nav font-bold uppercase tracking-widest rounded-lg shadow-md hover:bg-primary transition-colors active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed"
                  type="submit">
            <span class="material-symbols-outlined text-lg">lock</span>
            <span id="ctaLabel">Complete Order &amp; Pay <?= e(price($initTotal)) ?></span>
          </button>

          <p class="mt-3 font-body-sm text-body-sm text-on-surface-variant text-center">
            By completing this order you agree to our <a class="text-primary underline" href="<?= e(url('page.php?slug=terms')) ?>">Terms</a> and
            <a class="text-primary underline" href="<?= e(url('page.php?slug=privacy-policy')) ?>">Privacy Policy</a>.
          </p>
        </div>

        <div class="bg-inverse-surface text-surface rounded-xl p-4 flex items-start gap-3">
          <span class="material-symbols-outlined text-secondary-fixed">enhanced_encryption</span>
          <p class="font-body-sm text-body-sm text-surface-dim">
            Every payment is settled over an encrypted channel. Zion Groups never stores your MoMo PIN or card CVC.
          </p>
        </div>
      </aside>
    </form>
  </div>
</main>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
(function () {
  var subtotal = <?= json_encode((float) $cart['subtotal']) ?>;
  var discount = <?= json_encode((float) $discount) ?>;
  var metro = <?= json_encode((float) setting('shipping_metro_fee', (string) SHIPPING_METRO)) ?>;
  var regional = <?= json_encode((float) setting('shipping_regional_fee', (string) SHIPPING_REGIONAL)) ?>;
  var pickup = <?= json_encode((float) setting('shipping_pickup_fee', '0.00')) ?>;
  var freeAt = <?= json_encode((float) setting('free_shipping_threshold', (string) FREE_SHIPPING_THRESHOLD)) ?>;
  var currency = <?= json_encode(CURRENCY) ?>;
  var isPaystackLive = <?= json_encode(is_paystack_configured()) ?>;

  function fmt(v) {
    return currency + ' ' + v.toLocaleString('en-US', {
      minimumFractionDigits: (v % 1 === 0) ? 0 : 2,
      maximumFractionDigits: 2
    });
  }

  function refresh() {
    var region = document.querySelector('[name="region"]').value;
    var method = (document.querySelector('[name="shipping_method"]:checked') || {}).value || 'metro';
    var fee = method === 'pickup' ? pickup : (method === 'regional' || region.indexOf('Greater Accra') === -1 ? regional : metro);
    if (method === 'metro' && region.indexOf('Greater Accra') === -1) fee = regional;
    if (method !== 'pickup' && subtotal >= freeAt && region.indexOf('Greater Accra') !== -1) fee = metro;

    var total = Math.max(0, subtotal - discount) + fee;
    document.getElementById('shippingSummary').textContent = fee === 0 ? 'FREE' : fmt(fee);
    document.getElementById('orderTotal').textContent = fmt(total);
    var cta = document.getElementById('ctaLabel');
    if (cta) cta.textContent = 'Complete Order & Pay ' + fmt(total);
  }

  document.querySelectorAll('[name="shipping_method"], [name="region"]').forEach(function (el) {
    el.addEventListener('change', refresh);
  });

  document.querySelectorAll('[data-pay-radio]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      document.querySelectorAll('.pay-card').forEach(function (card) {
        var active = card.dataset.channel === radio.value;
        card.classList.toggle('border-primary', active);
        card.classList.toggle('bg-surface-container-low', active);
        card.classList.toggle('border-outline-variant', !active);
        card.querySelector('.pay-panel').classList.toggle('hidden', !active);
      });
    });
  });

  // Paystack Popup (Inline) Form Interceptor
  var form = document.getElementById('checkoutForm') || document.querySelector('form');
  if (form) {
    form.addEventListener('submit', function (e) {
      var channel = (form.querySelector('[name="payment_channel"]:checked') || {}).value || 'paystack';

      if (channel === 'paystack' && isPaystackLive && typeof PaystackPop !== 'undefined') {
        e.preventDefault();

        var submitBtn = document.getElementById('checkoutSubmitBtn') || form.querySelector('button[type="submit"]');
        var originalBtnHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="inline-block animate-spin mr-2">⟳</span> Connecting Paystack Popup...';

        var formData = new FormData(form);
        formData.set('ajax', '1');

        fetch(form.action, {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          }
        })
        .then(function (res) {
          return res.text().then(function (text) {
            try {
              return JSON.parse(text);
            } catch (parseErr) {
              console.warn('Raw response from server:', text);
              var cleanMsg = text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
              throw new Error(cleanMsg || 'Server returned an invalid response.');
            }
          });
        })
        .then(function (data) {
          if (!data.ok) {
            alert(data.message || 'Could not initialize order. Please check all fields.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
            return;
          }

          if (data.paystack) {
            var handler = PaystackPop.setup({
              key: data.key,
              email: data.email,
              amount: data.amount,
              currency: data.currency || 'GHS',
              ref: data.reference || data.order_no,
              metadata: {
                custom_fields: [
                  { display_name: "Customer Name", variable_name: "customer_name", value: data.customer_name },
                  { display_name: "Phone Number", variable_name: "phone_number", value: data.phone },
                  { display_name: "Order Number", variable_name: "order_no", value: data.order_no }
                ]
              },
              callback: function (response) {
                submitBtn.innerHTML = '<span class="inline-block mr-2">✓</span> Payment Authorized. Finalizing...';
                var ref = response.reference || response.trxref || data.reference;
                var callbackUrl = data.callback_url || 'paystack_callback.php';
                var delim = callbackUrl.indexOf('?') === -1 ? '?' : '&';
                window.location.href = callbackUrl + delim + 'reference=' + encodeURIComponent(ref) + '&order_no=' + encodeURIComponent(data.order_no);
              },
              onClose: function () {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
              }
            });
            handler.openIframe();
          } else if (data.redirect) {
            window.location.href = data.redirect;
          }
        })
        .catch(function (err) {
          console.error('Checkout error:', err);
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnHtml;
          var msg = (err && err.message) ? err.message : 'A network error occurred. Please try again or switch payment method.';
          if (msg.length > 250) msg = msg.substring(0, 250) + '...';
          alert(msg);
        });
      }
    });
  }

  refresh();
})();
</script>
<?php render_foot(); ?>
