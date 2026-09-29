<?php
/** Single order: fulfilment status, payment, event timeline. */

declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_admin();

$no = trim((string) ($_GET['no'] ?? ''));
$order = $no !== '' ? db_one('SELECT * FROM orders WHERE order_no = ?', [$no]) : null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ($order === null) {
        flash_set('error', 'That order does not exist.');
        header('Location: ' . url('admin/orders.php'));
        exit;
    }
    csrf_check();
    $act = (string) ($_POST['act'] ?? '');
    $id  = (int) $order['id'];

    if ($act === 'status') {
        $status = (string) ($_POST['status'] ?? '');
        $note   = trim((string) ($_POST['note'] ?? ''));
        if (!in_array($status, ['pending', 'confirmed', 'packing', 'shipped', 'delivered', 'cancelled'], true)) {
            flash_set('error', 'Unknown status.');
        } elseif ($status !== (string) $order['status']) {
            db_exec('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $id]);
            if ($note === '') {
                $note = match ($status) {
                    'pending'   => 'Awaiting payment confirmation',
                    'confirmed' => 'Payment confirmed',
                    'packing'   => 'Packing and discretion check',
                    'shipped'   => 'Handed to courier - ' . $order['shipping_label'],
                    'delivered' => 'Delivered to customer',
                    'cancelled' => 'Order cancelled',
                };
            }
            db_exec('INSERT INTO order_events (order_id, status, note) VALUES (?,?,?)', [$id, $status, $note]);
            if ($status === 'delivered' && $order['payment_channel'] === 'cod') {
                db_exec('UPDATE orders SET payment_status = "paid" WHERE id = ?', [$id]);
            }
            if ($status === 'cancelled' && $order['payment_status'] === 'paid') {
                foreach (db_all('SELECT product_id, qty FROM order_items WHERE order_id = ?', [$id]) as $it) {
                    db_exec('UPDATE products SET stock = stock + ? WHERE id = ?', [(int) $it['qty'], (int) $it['product_id']]);
                }
                db_exec('UPDATE orders SET payment_status = "refunded" WHERE id = ?', [$id]);
            }
            flash_set('success', 'Order ' . $order['order_no'] . ' moved to ' . $status . '.');
        } else {
            flash_set('info', 'That is already the current status.');
        }
    } elseif ($act === 'payment') {
        $pay = (string) ($_POST['payment_status'] ?? '');
        if (!in_array($pay, ['pending', 'paid', 'failed', 'refunded'], true)) {
            flash_set('error', 'Unknown payment state.');
        } else {
            db_exec('UPDATE orders SET payment_status = ? WHERE id = ?', [$pay, $id]);
            db_exec('INSERT INTO order_events (order_id, status, note) VALUES (?,?,?)',
                [$id, 'payment', 'Payment marked ' . $pay]);
            flash_set('success', 'Payment recorded as ' . $pay . '.');
        }
    }

    header('Location: ' . url('admin/order_detail.php?no=' . urlencode($order['order_no'])));
    exit;
}

if ($order === null) {
    admin_head('Order not found', 'orders');
    echo '<div class="bg-surface-container-lowest rounded-xl shadow-xs p-10 text-center">'
        . '<span class="material-symbols-outlined text-4xl text-outline">search_off</span>'
        . '<p class="font-headline-sm text-headline-sm text-on-surface font-bold mt-2">Order not found</p>'
        . '<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">No order matches that reference.</p>'
        . '<a class="inline-block mt-4 px-5 py-2.5 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav uppercase" href="'
        . e(url('admin/orders.php')) . '">Back to orders</a></div>';
    admin_foot();
    exit;
}

$items  = db_all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [(int) $order['id']]);
$events = db_all('SELECT * FROM order_events WHERE order_id = ? ORDER BY created_at, id', [(int) $order['id']]);
$customer = $order['email'] !== null ? db_one('SELECT * FROM users WHERE email = ?', [$order['email']]) : null;
$statuses = ['pending', 'confirmed', 'packing', 'shipped', 'delivered', 'cancelled'];

admin_head('Order ' . $order['order_no'], 'orders');
?>
<nav class="font-label-nav text-label-nav text-on-surface-variant mb-3 flex items-center gap-2">
  <a class="hover:text-primary" href="<?= e(url('admin/index.php')) ?>">Dashboard</a>
  <span class="text-outline-variant">/</span>
  <a class="hover:text-primary" href="<?= e(url('admin/orders.php')) ?>">Orders</a>
  <span class="text-outline-variant">/</span>
  <span class="text-primary font-semibold"><?= e($order['order_no']) ?></span>
</nav>

<div class="flex flex-wrap items-end justify-between gap-3 mb-space-md">
  <div>
    <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">
      <?= e($order['shipping_label']) ?>
    </span>
    <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Order <?= e($order['order_no']) ?></h1>
    <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
      Placed <?= e(date('d M Y, H:i', strtotime((string) $order['created_at']))) ?>
      &middot; updated <?= e(date('d M Y, H:i', strtotime((string) $order['updated_at']))) ?>
    </p>
  </div>
  <div class="flex items-center gap-2">
    <a class="px-4 py-2 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase tracking-wider text-on-surface hover:bg-surface-container transition-colors"
       href="<?= e(url('invoice.php?no=' . urlencode((string) $order['order_no']))) ?>" target="_blank" rel="noopener">
      Print invoice
    </a>
    <?= status_pill((string) $order['status']) ?>
    <?= status_pill((string) $order['payment_status']) ?>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-space-md items-start">
  <section class="lg:col-span-2 flex flex-col gap-space-md">
    <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-3">Items</h2>
      <ul class="flex flex-col divide-y divide-outline-variant/60">
        <?php foreach ($items as $it): ?>
          <li class="py-3 flex items-center gap-3">
            <img class="w-11 h-14 object-cover rounded bg-surface-container" src="<?= e(img_url((string) $it['product_image'])) ?>" alt=""/>
            <div class="flex-1 min-w-0">
              <p class="font-body-sm text-body-sm text-on-surface font-semibold truncate"><?= e($it['product_name']) ?></p>
              <p class="font-body-sm text-[11px] text-on-surface-variant"><?= e((string) ($it['variant_text'] ?: 'One size')) ?> &middot; <?= e(price($it['unit_price'])) ?> each</p>
            </div>
            <span class="font-body-sm text-body-sm text-on-surface-variant">&times;<?= (int) $it['qty'] ?></span>
            <span class="font-label-price text-label-price text-on-surface w-24 text-right"><?= e(price($it['line_total'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="mt-4 pt-4 border-t border-outline-variant/60 flex flex-col gap-1.5 font-body-md text-body-md ml-auto max-w-xs">
        <div class="flex justify-between text-on-surface-variant"><span>Subtotal</span><span><?= e(price($order['subtotal'])) ?></span></div>
        <?php if ((float) $order['discount'] > 0): ?>
          <div class="flex justify-between text-secondary"><span>Promo <?= e((string) $order['promo_code']) ?></span><span>-<?= e(price($order['discount'])) ?></span></div>
        <?php endif; ?>
        <div class="flex justify-between text-on-surface-variant"><span>Delivery</span><span><?= (float) $order['shipping_fee'] > 0 ? e(price($order['shipping_fee'])) : 'Free' ?></span></div>
        <div class="flex justify-between text-on-surface font-bold pt-2 mt-1 border-t border-outline-variant/60"><span>Total</span><span class="text-primary"><?= e(price($order['total'])) ?></span></div>
      </div>
    </div>

    <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-3">Timeline</h2>
      <ul class="flex flex-col gap-2.5">
        <?php foreach (array_reverse($events) as $ev): ?>
          <li class="flex items-start gap-3">
            <span class="material-symbols-outlined text-secondary text-base mt-0.5">radio_button_checked</span>
            <div class="min-w-0">
              <p class="font-body-sm text-body-sm text-on-surface font-semibold capitalize"><?= e($ev['status']) ?> &mdash; <?= e($ev['note'] ?? '') ?></p>
              <p class="font-body-sm text-[11px] text-outline"><?= e(date('d M Y, H:i', strtotime((string) $ev['created_at']))) ?></p>
            </div>
          </li>
        <?php endforeach; ?>
        <?php if ($events === []): ?><li class="font-body-sm text-body-sm text-on-surface-variant">No events recorded.</li><?php endif; ?>
      </ul>
    </div>
  </section>

  <aside class="flex flex-col gap-space-md">
    <form data-ajax method="post" class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="status"/>
      <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-3">Fulfilment status</h2>
      <label class="flex flex-col gap-1 mb-3">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Move to</span>
        <select class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="status">
          <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="flex flex-col gap-1 mb-3">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Event note</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
               name="note" placeholder="e.g. Picked up by VAS courier"/>
      </label>
      <button class="w-full py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary" type="submit">
        Update Status
      </button>
    </form>

    <form data-ajax method="post" class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="payment"/>
      <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-3">Payment</h2>
      <p class="font-body-sm text-body-sm text-on-surface-variant mb-2">
        Channel <span class="font-semibold text-on-surface"><?= e(payment_channel_label((string) $order['payment_channel'])) ?></span><br/>
        Reference <?= e($order['payment_reference'] ?: 'not recorded') ?>
      </p>
      <label class="flex flex-col gap-1 mb-3">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Payment state</span>
        <select class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="payment_status">
          <?php foreach (['pending', 'paid', 'failed', 'refunded'] as $s): ?>
            <option value="<?= $s ?>" <?= $order['payment_status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="w-full py-3 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface hover:bg-surface-container" type="submit">
        Record Payment
      </button>
    </form>

    <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-2">Delivery</h2>
      <p class="font-body-sm text-body-sm text-on-surface font-semibold"><?= e($order['customer_name']) ?></p>
      <p class="font-body-sm text-body-sm text-on-surface-variant">
        <?= e($order['address']) ?><br/>
        <?= e($order['city']) ?>, <?= e($order['region']) ?><br/>
        <?= e($order['phone']) ?>
        <?php if ($order['email'] !== null): ?><br/><?= e($order['email']) ?><?php endif; ?>
      </p>
      <?php if ((int) $order['discreet_pack'] === 1): ?>
        <p class="mt-2 flex items-center gap-1.5 font-body-sm text-body-sm text-secondary">
          <span class="material-symbols-outlined text-base">enhanced_encryption</span> Discreet packaging requested
        </p>
      <?php endif; ?>
      <?php if ($customer !== null): ?>
        <a class="mt-3 inline-block font-label-nav text-label-nav text-primary uppercase hover:underline"
           href="<?= e(url('admin/customers.php?q=' . urlencode((string) $customer['email']))) ?>">View customer account</a>
      <?php endif; ?>
      <?php if ($order['notes'] !== null && $order['notes'] !== ''): ?>
        <p class="mt-3 pt-3 border-t border-outline-variant/60 font-body-sm text-body-sm text-on-surface-variant">
          <span class="font-label-nav text-label-nav uppercase text-on-surface block mb-1">Customer note</span>
          <?= e($order['notes']) ?>
        </p>
      <?php endif; ?>
    </div>
  </aside>
</div>
<?php admin_foot(); ?>
