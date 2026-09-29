<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$no = (string) ($_GET['no'] ?? '');
$order = $no !== '' ? db_one('SELECT * FROM orders WHERE order_no = ?', [$no]) : null;

$u = current_user();
if ($order === null || ($u !== null && $order['user_id'] !== null && (int) $order['user_id'] !== (int) $u['id'] && !is_admin())) {
    flash_set('info', 'We could not find that order.');
    header('Location: ' . url('index.php'));
    exit;
}

$items = db_all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [(int) $order['id']]);
$events = db_all('SELECT * FROM order_events WHERE order_id = ? ORDER BY created_at, id', [(int) $order['id']]);

set_title('Order ' . $order['order_no'] . ' confirmed | Zion Groups');
set_meta(null, null, true);  // account/order pages are noindex
render_head();

$steps = [
    'pending'   => 'Order Confirmed',
    'confirmed' => 'Payment Authorised',
    'packing'   => 'Quality & Discretion Check',
    'shipped'   => 'Van Dispatched',
    'delivered' => 'Delivered & Verified',
];
$statusOrder = array_keys($steps);
$currentIndex = (int) array_search($order['status'], $statusOrder, true);
if ($currentIndex === false) {
    $currentIndex = 0;
}
?>
<main class="w-full pt-24 bg-surface min-h-[calc(100vh-280px)]">
  <div class="max-w-[900px] mx-auto px-margin py-space-xl">

    <div class="text-center mb-space-lg">
      <span class="material-symbols-outlined text-primary" style="font-size:56px">check_circle</span>
      <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold mt-2">Thank you &mdash; your order is confirmed</h1>
      <p class="font-body-md text-body-md text-on-surface-variant mt-1">
        Order <strong class="text-primary"><?= e($order['order_no']) ?></strong> &middot;
        <?= e(date('d M Y, H:i', strtotime((string) $order['created_at']))) ?>
      </p>
      <p class="font-body-sm text-body-sm text-on-surface-variant mt-2 max-w-xl mx-auto">
        A confirmation has been recorded against your account. We will dispatch via
        <strong><?= e($order['shipping_label']) ?></strong> once the discretion check is complete.
      </p>
    </div>

    <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md mb-space-md">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Delivery progress</h2>
        <span class="font-label-tag text-label-tag uppercase tracking-wider px-2.5 py-1 rounded
          <?= $order['status'] === 'delivered' ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-primary-fixed text-primary' ?>">
          <?= e(strtoupper($order['status'])) ?>
        </span>
      </div>

      <ol class="flex flex-col sm:flex-row gap-4">
        <?php foreach ($steps as $key => $label): $pos = (int) array_search($key, $statusOrder, true); ?>
          <li class="flex-1 flex sm:flex-col items-start gap-3 sm:gap-2">
            <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0
              <?= $pos <= $currentIndex ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant' ?>">
              <?= $pos + 1 ?>
            </span>
            <span class="font-body-sm text-body-sm <?= $pos <= $currentIndex ? 'text-on-surface font-semibold' : 'text-on-surface-variant' ?>"><?= e($label) ?></span>
          </li>
        <?php endforeach; ?>
      </ol>

      <?php if ($events !== []): ?>
        <ul class="mt-4 pt-4 border-t border-outline-variant flex flex-col gap-1.5">
          <?php foreach (array_reverse($events) as $ev): ?>
            <li class="font-body-sm text-body-sm text-on-surface-variant flex items-start gap-2">
              <span class="material-symbols-outlined text-secondary text-sm mt-0.5">radio_button_checked</span>
              <span><strong class="text-on-surface"><?= e($ev['status']) ?></strong> &mdash; <?= e($ev['note'] ?? '') ?>
                <span class="text-outline">(<?= e(date('d M, H:i', strtotime((string) $ev['created_at']))) ?>)</span>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md mb-space-md">
      <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-3">Items</h2>
      <ul class="flex flex-col gap-3">
        <?php foreach ($items as $it): ?>
          <li class="flex items-center gap-3">
            <img class="w-14 h-16 object-cover rounded bg-surface-container" src="<?= e(img_url((string) $it['product_image'])) ?>" alt="<?= e($it['product_name']) ?>"/>
            <div class="flex-1 min-w-0">
              <p class="font-body-sm text-body-sm text-on-surface font-semibold truncate"><?= e($it['product_name']) ?></p>
              <p class="font-body-sm text-[11px] text-on-surface-variant"><?= e((string) $it['variant_text']) ?></p>
              <p class="font-body-sm text-[11px] text-outline">Qty <?= (int) $it['qty'] ?></p>
            </div>
            <span class="font-label-price text-label-price text-on-surface"><?= e(price($it['line_total'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>

      <dl class="mt-4 pt-4 border-t border-outline-variant flex flex-col gap-2 font-body-sm text-body-sm">
        <div class="flex justify-between"><dt class="text-on-surface-variant">Subtotal</dt><dd class="font-semibold"><?= e(price($order['subtotal'])) ?></dd></div>
        <?php if ((float) $order['discount'] > 0): ?>
          <div class="flex justify-between"><dt class="text-on-surface-variant">Voucher</dt><dd class="font-semibold text-primary">&minus; <?= e(price($order['discount'])) ?></dd></div>
        <?php endif; ?>
        <div class="flex justify-between"><dt class="text-on-surface-variant">Shipping &mdash; <?= e($order['shipping_label']) ?></dt>
          <dd class="font-semibold"><?= (float) $order['shipping_fee'] == 0 ? 'FREE' : e(price($order['shipping_fee'])) ?></dd></div>
        <div class="flex justify-between pt-3 border-t border-outline-variant">
          <dt class="font-headline-sm text-headline-sm text-on-surface font-bold">Total (<?= e(payment_channel_label((string) $order['payment_channel'])) ?>)</dt>
          <dd class="font-headline-sm text-headline-sm text-primary font-bold"><?= e(price($order['total'])) ?></dd>
        </div>
      </dl>
    </div>

    <div class="flex flex-wrap gap-3 justify-center">
      <a class="px-6 py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary"
         href="<?= e(url('order.php?no=' . urlencode($order['order_no']))) ?>">Track this order</a>
      <a class="px-6 py-3 border border-outline-variant text-on-surface rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-surface-container"
         href="<?= e(url('invoice.php?no=' . urlencode($order['order_no']))) ?>" target="_blank" rel="noopener">View invoice</a>
      <a class="px-6 py-3 border border-outline-variant text-on-surface rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-surface-container"
         href="<?= e(url('shop.php')) ?>">Continue shopping</a>
    </div>
  </div>
</main>
<?php render_foot(); ?>
