<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();

$u = current_user();

$tab = (string) ($_GET['status'] ?? 'all');
$tabs = [
    'all'       => 'All Orders',
    'transit'   => 'In Transit',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
];

$where  = ['orders.user_id = ?'];
$params = [(int) $u['id']];
if ($tab === 'transit') {
    $where[] = "orders.status IN ('pending','confirmed','packing','shipped')";
} elseif ($tab === 'delivered') {
    $where[] = "orders.status = 'delivered'";
} elseif ($tab === 'cancelled') {
    $where[] = "orders.status = 'cancelled'";
}
$whereSql = implode(' AND ', $where);

$counts = db_one(
    'SELECT COUNT(*) AS total,
            SUM(status IN ("pending","confirmed","packing","shipped")) AS transit,
            SUM(status = "delivered") AS delivered,
            SUM(status = "cancelled") AS cancelled
     FROM orders WHERE user_id = ?',
    [(int) $u['id']],
    ['total' => 0, 'transit' => 0, 'delivered' => 0, 'cancelled' => 0]
);
$lifetime = (float) db_val('SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id = ? AND payment_status = "paid"', [(int) $u['id']], 0);

$orders = db_all("SELECT * FROM orders WHERE $whereSql ORDER BY orders.created_at DESC", $params);

set_title('Order History & Tracking | Zion Groups');
set_meta(null, null, true);  // account/order pages are noindex
render_head();
?>
<main class="w-full pt-24 bg-surface min-h-[calc(100vh-280px)]">
  <div class="max-w-[1360px] mx-auto px-margin py-space-lg">

    <nav class="font-label-nav text-label-nav text-on-surface-variant mb-4 flex items-center gap-2" aria-label="Breadcrumb">
      <a class="hover:text-primary" href="<?= e(url('index.php')) ?>">Home</a>
      <span class="text-outline-variant">/</span>
      <a class="hover:text-primary" href="<?= e(url('account.php')) ?>">My Account</a>
      <span class="text-outline-variant">/</span>
      <span class="text-primary font-semibold">Order History</span>
    </nav>

    <div class="mb-space-md">
      <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">My Orders &amp; Delivery History</h1>
      <p class="font-body-md text-body-md text-on-surface-variant mt-1 max-w-2xl">
        Track real-time dispatches across Greater Accra, Kumasi and nationwide. View official VAT invoices, authorized warranty certifications and discreet concierge services.
      </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-space-sm mb-space-md">
      <div class="bg-surface-container-lowest rounded-xl shadow-xs p-4 flex items-center gap-3">
        <span class="material-symbols-outlined text-primary text-2xl">local_shipping</span>
        <div>
          <p class="font-headline-md text-headline-md text-on-surface font-bold"><?= (int) $counts['transit'] ?></p>
          <p class="font-body-sm text-body-sm text-on-surface-variant">Active in transit</p>
        </div>
      </div>
      <div class="bg-surface-container-lowest rounded-xl shadow-xs p-4 flex items-center gap-3">
        <span class="material-symbols-outlined text-secondary text-2xl">payments</span>
        <div>
          <p class="font-headline-md text-headline-md text-on-surface font-bold"><?= e(money($lifetime)) ?></p>
          <p class="font-body-sm text-body-sm text-on-surface-variant">Lifetime spend</p>
        </div>
      </div>
      <div class="bg-surface-container-lowest rounded-xl shadow-xs p-4 flex items-center gap-3">
        <span class="material-symbols-outlined text-secondary-fixed-variant text-2xl">hotel_class</span>
        <div>
          <p class="font-headline-md text-headline-md text-on-surface font-bold"><?= $lifetime >= 10000 ? 'Gold' : ($lifetime > 0 ? 'Silver' : 'Member') ?></p>
          <p class="font-body-sm text-body-sm text-on-surface-variant">Zion tier</p>
        </div>
      </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-space-md">
      <?php foreach ($tabs as $key => $label): ?>
        <?php $n = $key === 'all' ? (int) $counts['total'] : (int) $counts[$key]; ?>
        <a class="px-4 py-2 rounded-full font-label-nav text-label-nav uppercase tracking-wider transition-colors
           <?= $tab === $key ? 'bg-primary-container text-on-primary font-bold' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' ?>"
           href="<?= e(url('orders.php?status=' . $key)) ?>">
          <?= e($label) ?> <span class="opacity-70">(<?= $n ?>)</span>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($orders === []): ?>
      <div class="bg-surface-container-lowest rounded-xl shadow-xs p-12 text-center">
        <span class="material-symbols-outlined text-4xl text-outline">receipt_long</span>
        <h2 class="font-headline-md text-headline-md text-on-surface font-bold mt-3">No orders in this view</h2>
        <a class="inline-block mt-4 px-6 py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider" href="<?= e(url('shop.php')) ?>">Start shopping</a>
      </div>
    <?php else: ?>
      <div class="flex flex-col gap-space-md">
        <?php foreach ($orders as $o): ?>
          <?php
          $items = db_all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id LIMIT 4', [(int) $o['id']]);
          $event = db_one('SELECT * FROM order_events WHERE order_id = ? ORDER BY created_at DESC, id DESC LIMIT 1', [(int) $o['id']]);
          $statusCls = match ($o['status']) {
              'delivered' => 'bg-secondary-fixed text-on-secondary-fixed',
              'cancelled' => 'bg-error-container text-on-error-container',
              'shipped'   => 'bg-primary-container text-on-primary',
              default     => 'bg-primary-fixed text-primary',
          };
          ?>
          <article class="bg-surface-container-lowest rounded-xl shadow-xs overflow-hidden">
            <div class="p-space-md flex flex-wrap items-center justify-between gap-4 border-b border-outline-variant/60">
              <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <span class="font-headline-sm text-headline-sm text-on-surface font-bold">Order #<?= e($o['order_no']) ?></span>
                <span class="font-body-sm text-body-sm text-on-surface-variant">Placed <?= e(date('d M Y', strtotime((string) $o['created_at']))) ?></span>
                <span class="font-body-sm text-body-sm text-on-surface-variant">
                  <?= e(payment_channel_label((string) $o['payment_channel'])) ?> &middot;
                  <?= $o['payment_status'] === 'paid' ? 'Paid' : 'Payment pending' ?>
                </span>
              </div>
              <div class="flex items-center gap-3">
                <span class="font-headline-sm text-headline-sm text-on-surface font-bold"><?= e(price($o['total'])) ?></span>
                <span class="font-label-tag text-label-tag uppercase tracking-wider px-2.5 py-1 rounded <?= e($statusCls) ?>"><?= e($o['status']) ?></span>
              </div>
            </div>

            <?php if ($o['status'] !== 'cancelled'): ?>
              <div class="px-space-md py-3 bg-surface-container flex items-center gap-3 flex-wrap">
                <span class="material-symbols-outlined text-secondary text-lg">radar</span>
                <span class="font-body-sm text-body-sm text-on-surface font-semibold"><?= e($event['note'] ?? 'Awaiting confirmation') ?></span>
                <span class="font-body-sm text-body-sm text-on-surface-variant">&middot; <?= e($o['shipping_label']) ?></span>
                <span class="font-body-sm text-body-sm text-outline">&middot; <?= e(date('d M, H:i', strtotime((string) ($event['created_at'] ?? $o['created_at'])))) ?></span>
              </div>
            <?php endif; ?>

            <div class="p-space-md flex flex-col sm:flex-row gap-4 justify-between">
              <div class="flex flex-wrap items-center gap-3">
                <?php foreach ($items as $it): ?>
                  <a class="flex items-center gap-2 bg-surface-container rounded-lg p-2 hover:bg-surface-container-high transition-colors" href="<?= e(url('product.php?slug=' . urlencode((string) db_val('SELECT slug FROM products WHERE id = ?', [(int) $it['product_id']], '')))) ?>">
                    <img class="w-10 h-12 object-cover rounded bg-surface-container-lowest" src="<?= e(img_url((string) $it['product_image'])) ?>" alt="<?= e($it['product_name']) ?>"/>
                    <span class="font-body-sm text-body-sm text-on-surface max-w-[160px] truncate"><?= e($it['product_name']) ?></span>
                    <span class="font-body-sm text-body-sm text-on-surface-variant">&times;<?= (int) $it['qty'] ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
              <div class="flex items-center gap-2 self-start">
                <a class="px-4 py-2.5 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary" href="<?= e(url('order.php?no=' . urlencode($o['order_no']))) ?>">Track Order</a>
                <a class="px-4 py-2.5 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase tracking-wider text-on-surface hover:bg-surface-container transition-colors" href="<?= e(url('invoice.php?no=' . urlencode($o['order_no']))) ?>" target="_blank" rel="noopener">Invoice</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php render_foot(); ?>
