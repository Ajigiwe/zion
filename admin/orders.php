<?php
/** Order queue: filter, search, open a shipment. */

declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_admin();

$tabs = [
    'all'       => 'All orders',
    'pending'   => 'Pending',
    'confirmed' => 'Confirmed',
    'packing'   => 'Packing',
    'shipped'   => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
];
$status = (string) ($_GET['status'] ?? 'all');
if (!isset($tabs[$status])) {
    $status = 'all';
}
$q = trim((string) ($_GET['q'] ?? ''));

$rangeOptions = [7 => 7, 30 => 30, 90 => 90, 365 => 365, 0 => 0];
$range = (int) ($_GET['range'] ?? 0);
if (!array_key_exists($range, $rangeOptions)) {
    $range = 0;
}

$where  = ['1 = 1'];
$params = [];
if ($status !== 'all') {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
if ($range > 0) {
    $where[] = 'o.created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $range . ' DAY)';
}
if ($q !== '') {
    $where[] = '(o.order_no LIKE ? OR o.customer_name LIKE ? OR o.email LIKE ? OR o.phone LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$orders = db_all(
    "SELECT o.*, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS items
     FROM orders o
     WHERE $whereSql
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT 300",
    $params
);

$counts = [];
foreach (array_keys($tabs) as $k) {
    $counts[$k] = $k === 'all'
        ? (int) db_val('SELECT COUNT(*) FROM orders', [], 0)
        : (int) db_val('SELECT COUNT(*) FROM orders WHERE status = ?', [$k], 0);
}

/* CSV export of the current filter (?export=csv&status=&q=&range=) */
if (($_GET['export'] ?? '') === 'csv') {
    $rows = db_all(
        "SELECT o.*, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS items
           FROM orders o
          WHERE $whereSql
          ORDER BY o.created_at DESC, o.id DESC",
        $params
    );
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="zion-orders-' . date('Ymd-Hi') . '.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Order', 'Placed', 'Customer', 'Email', 'Phone', 'Region', 'City', 'Items',
        'Shipping method', 'Payment', 'Payment status', 'Fulfilment status',
        'Subtotal', 'Discount', 'Promo', 'Shipping fee', 'Total',
    ]);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['order_no'],
            date('Y-m-d H:i', strtotime((string) $r['created_at'])),
            $r['customer_name'],
            $r['email'],
            $r['phone'],
            $r['region'],
            $r['city'],
            (int) $r['items'],
            $r['shipping_label'],
            payment_channel_label((string) $r['payment_channel']),
            $r['payment_status'],
            $r['status'],
            number_format((float) $r['subtotal'], 2),
            number_format((float) $r['discount'], 2),
            (string) $r['promo_code'],
            number_format((float) $r['shipping_fee'], 2),
            number_format((float) $r['total'], 2),
        ]);
    }
    fclose($out);
    exit;
}

admin_head('Orders', 'orders');
?>
<div class="mb-space-md">
  <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Fulfilment</span>
  <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Orders</h1>
  <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Update statuses, record payments and keep the Accra hub queue clean.</p>
</div>

<div class="flex flex-wrap gap-2 mb-space-md">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="px-4 py-2 rounded-full font-label-nav text-label-nav uppercase tracking-wider transition-colors
       <?= $status === $key ? 'bg-primary-container text-on-primary font-bold' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' ?>"
       href="<?= e(url('admin/orders.php?status=' . $key)) ?>">
      <?= e($label) ?> <span class="opacity-70">(<?= $counts[$key] ?>)</span>
    </a>
  <?php endforeach; ?>
</div>

<form method="get" class="flex flex-wrap gap-2 mb-space-md">
  <input type="hidden" name="status" value="<?= e($status) ?>"/>
  <input class="h-11 w-72 px-3 border border-outline-variant rounded-lg font-body-sm text-body-sm focus:border-primary outline-none"
         type="search" name="q" value="<?= e($q) ?>" placeholder="Order number, name, email or phone"/>
  <button class="h-11 px-5 bg-inverse-surface text-surface rounded-lg font-label-nav text-label-nav uppercase" type="submit">Search</button>
</form>

<section class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
  <div class="overflow-x-auto">
    <table class="w-full text-left">
      <thead>
        <tr class="border-b border-outline-variant">
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Order</th>
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Customer</th>
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Items</th>
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Payment</th>
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Status</th>
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Placed</th>
          <th class="py-2 font-label-nav text-label-nav uppercase text-on-surface-variant text-right">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($orders === []): ?>
          <tr><td class="py-8 text-center font-body-sm text-body-sm text-on-surface-variant" colspan="7">No orders match that filter.</td></tr>
        <?php else: foreach ($orders as $o): ?>
          <tr class="border-b border-outline-variant/50 hover:bg-surface-container">
            <td class="py-2.5 pr-3">
              <a class="font-body-sm text-body-sm font-semibold text-on-surface hover:text-primary" href="<?= e(url('admin/order_detail.php?no=' . urlencode($o['order_no']))) ?>"><?= e($o['order_no']) ?></a>
              <div class="font-body-sm text-[11px] text-outline"><?= e($o['shipping_label']) ?></div>
            </td>
            <td class="py-2.5 pr-3 font-body-sm text-body-sm text-on-surface-variant">
              <?= e($o['customer_name']) ?>
              <div class="text-[11px] text-outline"><?= e($o['email'] ?: $o['phone']) ?></div>
            </td>
            <td class="py-2.5 pr-3 font-body-sm text-body-sm text-on-surface-variant"><?= (int) $o['items'] ?></td>
            <td class="py-2.5 pr-3">
              <div class="font-body-sm text-body-sm text-on-surface-variant"><?= e(payment_channel_label((string) $o['payment_channel'])) ?></div>
              <?= status_pill((string) $o['payment_status']) ?>
            </td>
            <td class="py-2.5 pr-3"><?= status_pill((string) $o['status']) ?></td>
            <td class="py-2.5 pr-3 font-body-sm text-body-sm text-on-surface-variant"><?= e(date('d M Y, H:i', strtotime((string) $o['created_at']))) ?></td>
            <td class="py-2.5 font-label-price text-label-price text-on-surface text-right"><?= e(price($o['total'])) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_foot(); ?>
