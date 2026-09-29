<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();

$u = current_user();
$no = trim((string) ($_GET['no'] ?? ''));
$myOrders = array_map('strval', (array) ($_SESSION['my_orders'] ?? []));

$order = $no !== '' ? db_one('SELECT * FROM orders WHERE order_no = ?', [$no]) : null;

$allowed = false;
if ($order !== null) {
    $allowed = is_admin()
        || in_array((string) $order['order_no'], $myOrders, true)
        || ($u !== null && $order['user_id'] !== null && (int) $order['user_id'] === (int) $u['id']);
}
if (!$allowed) {
    http_response_code(404);
    set_title('Order Not Found | Zion Groups');
    set_meta(null, null, true);  // account/order pages are noindex
    render_head();
    echo '<main class="w-full pt-24 bg-surface min-h-[calc(100vh-280px)]"><div class="max-w-xl mx-auto px-margin py-space-xl text-center">'
        . '<span class="material-symbols-outlined text-5xl text-outline">receipt_long</span>'
        . '<h1 class="font-headline-md text-headline-md text-on-surface font-bold mt-3">Order not found</h1>'
        . '<p class="font-body-md text-body-md text-on-surface-variant mt-2">We could not locate that order under your account.</p>'
        . '<a class="inline-block mt-5 px-6 py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider" href="'
        . e(url('orders.php')) . '">Back to order history</a></div></main>';
    render_foot();
    exit;
}

$items  = db_all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [(int) $order['id']]);
$events = db_all('SELECT * FROM order_events WHERE order_id = ? ORDER BY created_at, id', [(int) $order['id']]);

$steps = [
    'pending'   => ['receipt_long', 'Order received', 'We have received your order and are awaiting payment confirmation.'],
    'confirmed' => ['task_alt', 'Payment confirmed', 'Your payment has cleared. The order has been released to our Accra hub.'],
    'packing'   => ['inventory_2', 'Packing & quality check', 'Each piece is inspected, wrapped and sealed in discreet outer packaging.'],
    'shipped'   => ['local_shipping', 'In transit with courier', 'Dispatched to your destination via our authorized courier partner.'],
    'delivered' => ['where_to_vote', 'Delivered', 'Handed over at your delivery address. Thank you for shopping with Zion.'],
];

$active    = (string) $order['status'];
$cancelled = $active === 'cancelled';
$flow      = array_keys($steps);
$idx       = array_search($active, $flow, true);
$idx       = $idx === false ? 0 : $idx;

/** First timestamp recorded for each event status (pending, confirmed, ...). */
$evAt = [];
foreach ($events as $ev) {
    $s = (string) $ev['status'];
    if ($s !== '' && !isset($evAt[$s])) {
        $evAt[$s] = (string) $ev['created_at'];
    }
}

$payStatus = (string) $order['payment_status'];
$payPill   = match ($payStatus) {
    'paid'      => ['bg-secondary-fixed text-on-secondary-fixed', 'Paid', 'check_circle'],
    'refunded'  => ['bg-surface-container-high text-on-surface-variant', 'Refunded', 'replay'],
    'failed'    => ['bg-error-container text-on-error-container', 'Failed', 'error'],
    default     => ['bg-primary-fixed text-primary', 'Awaiting payment', 'schedule'],
};

$statusCls = match ($active) {
    'delivered' => 'bg-secondary-fixed text-on-secondary-fixed',
    'cancelled' => 'bg-error-container text-on-error-container',
    'shipped'   => 'bg-primary text-on-primary',
    default     => 'bg-primary-container text-on-primary',
};

set_title('Order ' . $order['order_no'] . ' | Zion Groups');
set_meta(null, null, true);  // order pages are noindex
render_head();
?>
<main class="w-full pt-24 bg-surface min-h-[calc(100vh-280px)]">
  <div class="max-w-[1000px] mx-auto px-margin py-space-lg">
    <nav class="font-label-nav text-label-nav text-on-surface-variant mb-4 flex items-center gap-2 flex-wrap" aria-label="Breadcrumb">
      <a class="hover:text-primary" href="<?= e(url('index.php')) ?>">Home</a>
      <span class="text-outline-variant">/</span>
      <a class="hover:text-primary" href="<?= e(url('orders.php')) ?>">My Orders</a>
      <span class="text-outline-variant">/</span>
      <span class="text-primary font-semibold"><?= e($order['order_no']) ?></span>
    </nav>

    <!-- title -->
    <div class="flex flex-wrap items-start justify-between gap-3 mb-space-md">
      <div class="min-w-0">
        <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Order #<?= e($order['order_no']) ?></span>
        <h1 class="font-headline-md sm:text-headline-lg text-headline-md text-on-surface font-bold">Order details &amp; live tracking</h1>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
          Placed <?= e(date('d M Y, H:i', strtotime((string) $order['created_at']))) ?>
          &middot; <?= e(payment_channel_label((string) $order['payment_channel'])) ?>
        </p>
      </div>
      <span class="font-label-nav text-label-nav font-bold uppercase tracking-wider px-3 py-2 rounded-lg <?= e($statusCls) ?>"><?= e(ucfirst($active)) ?></span>
    </div>

    <!-- summary strip -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-space-sm mb-space-md">
      <div class="bg-surface-container-lowest rounded-xl shadow-xs p-4">
        <p class="font-label-tag text-label-tag text-on-surface-variant uppercase tracking-wider mb-1.5">Payment</p>
        <p class="flex items-center gap-1.5 font-body-md text-body-md font-semibold <?= $payStatus === 'paid' ? 'text-secondary' : 'text-primary' ?>">
          <span class="material-symbols-outlined text-lg"><?= e($payPill[2]) ?></span><?= e($payPill[1]) ?>
        </p>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5"><?= e(payment_channel_label((string) $order['payment_channel'])) ?></p>
      </div>
      <div class="bg-surface-container-lowest rounded-xl shadow-xs p-4">
        <p class="font-label-tag text-label-tag text-on-surface-variant uppercase tracking-wider mb-1.5">Delivery</p>
        <p class="font-body-md text-body-md font-semibold text-on-surface"><?= e((string) $order['shipping_label']) ?></p>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
          <?= (float) $order['shipping_fee'] > 0 ? e(price($order['shipping_fee'])) : 'Complimentary' ?>
        </p>
      </div>
      <div class="bg-surface-container-lowest rounded-xl shadow-xs p-4">
        <p class="font-label-tag text-label-tag text-on-surface-variant uppercase tracking-wider mb-1.5">Items</p>
        <p class="font-body-md text-body-md font-semibold text-on-surface"><?= (int) count($items) ?> <?= count($items) === 1 ? 'piece' : 'pieces' ?></p>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">In this order</p>
      </div>
      <div class="bg-surface-container-lowest rounded-xl shadow-xs p-4">
        <p class="font-label-tag text-label-tag text-on-surface-variant uppercase tracking-wider mb-1.5">Order total</p>
        <p class="font-headline-sm text-headline-sm font-bold text-primary"><?= e(price($order['total'])) ?></p>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
          <?= (float) $order['discount'] > 0 ? 'Promo ' . e((string) $order['promo_code']) . ' applied' : 'Incl. delivery' ?>
        </p>
      </div>
    </div>

    <?php if (!$cancelled && $payStatus !== 'paid'): ?>
      <div class="flex items-start gap-2.5 bg-primary-fixed text-primary rounded-xl p-3.5 mb-space-md font-body-sm text-body-sm">
        <span class="material-symbols-outlined text-lg shrink-0 mt-0.5">schedule</span>
        <span>Payment has not cleared yet. Your order stays reserved while we wait for
          <strong><?= e(payment_channel_label((string) $order['payment_channel'])) ?></strong> confirmation.</span>
      </div>
    <?php endif; ?>

    <!-- timeline -->
    <section class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md mb-space-md">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Live order timeline</h2>
        <?php if (!$cancelled && isset($evAt[$active])): ?>
          <span class="font-body-sm text-body-sm text-on-surface-variant">Updated <?= e(date('d M Y, H:i', strtotime($evAt[$active]))) ?></span>
        <?php endif; ?>
      </div>

      <?php if ($cancelled): ?>
        <div class="flex items-start gap-3 bg-error-container text-on-error-container rounded-lg p-3.5">
          <span class="material-symbols-outlined text-xl shrink-0">cancel</span>
          <div>
            <p class="font-body-md text-body-md font-semibold">Order cancelled</p>
            <p class="font-body-sm text-body-sm">This order was cancelled. Any payment made will be refunded to your original method.</p>
          </div>
        </div>
      <?php else: ?>
        <ol class="flex flex-col">
          <?php foreach ($steps as $key => [$icon, $label, $desc]): ?>
            <?php
            $i         = (int) array_search($key, $flow, true);
            $isDone    = $i < $idx;
            $isCurrent = $i === $idx;
            $step      = $i < count($flow) - 1 ? $key : null;
            $stamp     = $isDone || $isCurrent ? ($evAt[$key] ?? null) : null;
            ?>
            <li class="flex gap-3 sm:gap-4">
              <div class="flex flex-col items-center">
                <span class="w-9 h-9 shrink-0 rounded-full flex items-center justify-center
                  <?= $isCurrent ? 'bg-primary text-on-primary ring-4 ring-primary-fixed'
                      : ($isDone ? 'bg-primary-container text-on-primary' : 'bg-surface-container text-outline') ?>">
                  <span class="material-symbols-outlined text-lg"><?= e($icon) ?></span>
                </span>
                <?php if ($step !== null): ?>
                  <span class="w-px flex-1 min-h-8 <?= $isDone && !$isCurrent ? 'bg-primary-container' : 'bg-outline-variant' ?>"></span>
                <?php endif; ?>
              </div>
              <div class="<?= $step !== null ? 'pb-5' : 'pb-1' ?> min-w-0 flex-1">
                <p class="flex flex-wrap items-center gap-x-2 gap-y-1 font-body-md text-body-md font-semibold <?= $isDone || $isCurrent ? 'text-on-surface' : 'text-outline' ?>">
                  <?= e($label) ?>
                  <?php if ($isCurrent): ?>
                    <span class="font-label-tag text-label-tag uppercase tracking-wider bg-primary-fixed text-primary px-2 py-0.5 rounded">Current</span>
                  <?php endif; ?>
                </p>
                <p class="font-body-sm text-body-sm <?= $isDone || $isCurrent ? 'text-on-surface-variant' : 'text-outline' ?>"><?= e($desc) ?></p>
                <?php if ($stamp !== null): ?>
                  <p class="flex items-center gap-1 font-body-sm text-body-sm text-secondary mt-1">
                    <span class="material-symbols-outlined text-base">event</span>
                    <?= e(date('d M Y, H:i', strtotime($stamp))) ?>
                  </p>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>

      <?php if ($events !== []): ?>
        <div class="mt-1 pt-4 border-t border-outline-variant/60">
          <p class="font-label-tag text-label-tag text-on-surface-variant uppercase tracking-wider mb-2.5">Activity log</p>
          <ul class="flex flex-col gap-2">
            <?php foreach (array_reverse($events) as $ev): ?>
              <li class="flex items-start gap-2.5 font-body-sm text-body-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-secondary mt-1.5 shrink-0"></span>
                <span class="text-on-surface font-semibold shrink-0"><?= e(date('d M, H:i', strtotime((string) $ev['created_at']))) ?></span>
                <span class="text-on-surface-variant"><?= e($ev['note']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-space-md">
      <!-- items -->
      <section class="lg:col-span-2 bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
        <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-1">Items in this order</h2>
        <p class="font-body-sm text-body-sm text-on-surface-variant mb-3"><?= (int) count($items) ?> <?= count($items) === 1 ? 'item' : 'items' ?> &middot; <?= e(price($order['subtotal'])) ?> subtotal</p>

        <ul class="flex flex-col divide-y divide-outline-variant/60">
          <?php foreach ($items as $it): ?>
            <?php $slug = (string) db_val('SELECT slug FROM products WHERE id = ?', [(int) $it['product_id']], ''); ?>
            <li class="py-3.5 flex items-start sm:items-center gap-3 sm:gap-4">
              <img class="w-14 h-16 shrink-0 object-cover rounded bg-surface-container" src="<?= e(img_url((string) $it['product_image'])) ?>" alt="<?= e((string) $it['product_name']) ?>"/>
              <div class="flex-1 min-w-0">
                <?php if ($slug !== ''): ?>
                  <a class="font-body-md text-body-md text-on-surface font-semibold hover:text-primary block truncate" href="<?= e(url('product.php?slug=' . urlencode($slug))) ?>"><?= e((string) $it['product_name']) ?></a>
                <?php else: ?>
                  <p class="font-body-md text-body-md text-on-surface font-semibold truncate"><?= e((string) $it['product_name']) ?></p>
                <?php endif; ?>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                  <?= e((string) ($it['variant_text'] ?? '')) !== '' ? e((string) $it['variant_text']) : 'One size' ?>
                </p>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Qty <?= (int) $it['qty'] ?> &times; <?= e(price($it['unit_price'])) ?></p>
              </div>
              <span class="font-label-price text-label-price text-on-surface shrink-0 text-right"><?= e(price($it['line_total'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="mt-4 pt-4 border-t border-outline-variant/60 flex flex-col gap-1.5 font-body-md text-body-md sm:ml-auto sm:max-w-xs">
          <div class="flex justify-between text-on-surface-variant"><span>Subtotal</span><span><?= e(price($order['subtotal'])) ?></span></div>
          <?php if ((float) $order['discount'] > 0): ?>
            <div class="flex justify-between text-secondary"><span>Promo <?= e((string) $order['promo_code']) ?></span><span>-<?= e(price($order['discount'])) ?></span></div>
          <?php endif; ?>
          <div class="flex justify-between text-on-surface-variant"><span>Delivery</span><span><?= (float) $order['shipping_fee'] > 0 ? e(price($order['shipping_fee'])) : 'Free' ?></span></div>
          <div class="flex justify-between text-on-surface font-bold pt-2 mt-1 border-t border-outline-variant/60"><span>Total</span><span class="text-primary"><?= e(price($order['total'])) ?></span></div>
        </div>
      </section>

      <!-- sidebar -->
      <section class="flex flex-col gap-space-md">
        <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
          <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-2">Delivery address</h2>
          <p class="font-body-sm text-body-sm text-on-surface font-semibold"><?= e((string) $order['customer_name']) ?></p>
          <p class="font-body-sm text-body-sm text-on-surface-variant">
            <?= e((string) $order['address']) ?><br/>
            <?= e((string) $order['city']) ?>, <?= e((string) $order['region']) ?><br/>
            <?= e((string) $order['phone']) ?>
          </p>
          <div class="mt-3 pt-3 border-t border-outline-variant/60 flex flex-col gap-1.5 font-body-sm text-body-sm text-on-surface-variant">
            <p class="flex items-center gap-1.5">
              <span class="material-symbols-outlined text-secondary text-base">local_shipping</span>
              <?= e((string) $order['shipping_method']) ?> &middot; <?= e((string) $order['shipping_label']) ?>
            </p>
            <?php if ($order['payment_reference'] !== null && (string) $order['payment_reference'] !== ''): ?>
              <p class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-secondary text-base">receipt</span>
                Ref <?= e((string) $order['payment_reference']) ?>
              </p>
            <?php endif; ?>
          </div>
        </div>

        <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
          <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-2">Need help?</h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant mb-3">Our Accra concierge can change the delivery slot, swap a size or send a VAT invoice.</p>
          <div class="flex flex-col gap-2">
            <a class="px-4 py-2.5 bg-primary-container text-on-primary text-center rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary transition-colors" href="<?= e(url('page.php?slug=contact')) ?>">Contact concierge</a>
            <a class="px-4 py-2.5 border border-outline-variant text-on-surface text-center rounded-lg font-label-nav text-label-nav uppercase hover:bg-surface-container transition-colors" href="<?= e(url('invoice.php?no=' . urlencode((string) $order['order_no']))) ?>" target="_blank" rel="noopener">Print invoice</a>
            <a class="px-4 py-2.5 border border-outline-variant text-on-surface text-center rounded-lg font-label-nav text-label-nav uppercase hover:bg-surface-container transition-colors" href="<?= e(url('orders.php')) ?>">Back to all orders</a>
          </div>
        </div>
      </section>
    </div>
  </div>
</main>
<?php render_foot(); ?>
