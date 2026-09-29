<?php
/**
 * Printable VAT invoice for an order. Access is restricted exactly like
 * order.php: the owning customer, a guest who placed it in this session,
 * or staff. Print styles strip the chrome for a clean A4 sheet.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

$no = trim((string) ($_GET['no'] ?? ''));
$myOrders = array_map('strval', (array) ($_SESSION['my_orders'] ?? []));
$u = current_user();

$order = $no !== '' ? db_one('SELECT * FROM orders WHERE order_no = ?', [$no]) : null;

$allowed = false;
if ($order !== null) {
    $allowed = is_admin()
        || in_array((string) $order['order_no'], $myOrders, true)
        || ($u !== null && $order['user_id'] !== null && (int) $order['user_id'] === (int) $u['id']);
}
if (!$allowed) {
    http_response_code(404);
    set_title('Invoice Not Found | Zion Groups');
    set_meta(null, null, true);
    render_head();
    echo '<main class="w-full pt-24 bg-surface min-h-[calc(100vh-280px)]"><div class="max-w-xl mx-auto px-margin py-space-xl text-center">'
        . '<span class="material-symbols-outlined text-5xl text-outline">receipt_long</span>'
        . '<h1 class="font-headline-md text-headline-md text-on-surface font-bold mt-3">Invoice not found</h1>'
        . '<p class="font-body-sm text-body-sm text-on-surface-variant mt-2">We could not locate an invoice for that order.</p>'
        . '<a class="inline-block mt-5 px-6 py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider" href="'
        . e(url('index.php')) . '">Return to the storefront</a></div></main>';
    render_foot();
    exit;
}

$items  = db_all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [(int) $order['id']]);
$events = db_all('SELECT * FROM order_events WHERE order_id = ? ORDER BY created_at, id', [(int) $order['id']]);

$accent = '#71273a';
$placed = date('d F Y, H:i', strtotime((string) $order['created_at']));
$backUrl = is_admin()
    ? url('admin/order_detail.php?no=' . urlencode((string) $order['order_no']))
    : (in_array((string) $order['order_no'], $myOrders, true) || $u !== null
        ? url('order.php?no=' . urlencode((string) $order['order_no']))
        : url('index.php'));
$backLabel = is_admin() ? 'Back to order' : 'Back to order details';

set_title('Invoice ' . $order['order_no'] . ' | Zion Groups');
set_meta(null, null, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<meta name="robots" content="noindex, nofollow"/>
<title><?= e('Invoice ' . $order['order_no'] . ' | ' . SITE_NAME) ?></title>
<style>
  :root { --accent: <?= $accent ?>; --ink: #1d1b1b; --muted: #6b6560; --line: #d6d0cf; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #f4f1ef; color: var(--ink);
         font: 14px/1.5 Inter, "Helvetica Neue", Arial, sans-serif; }
  .sheet { max-width: 820px; margin: 32px auto; background: #fff; padding: 44px 48px;
           box-shadow: 0 10px 30px rgba(0,0,0,.08); }
  .toolbar { max-width: 820px; margin: 24px auto 0; display: flex; gap: 10px; justify-content: space-between; }
  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px;
         font-size: 12px; letter-spacing: .12em; text-transform: uppercase; text-decoration: none; cursor: pointer; }
  .btn-primary { background: var(--accent); color: #fff; border: 1px solid var(--accent); }
  .btn-ghost { background: #fff; color: var(--ink); border: 1px solid var(--line); }
  .btn:hover { filter: brightness(.96); }
  header.top { display: flex; justify-content: space-between; gap: 24px;
               border-bottom: 3px solid var(--accent); padding-bottom: 20px; }
  .brand { font-size: 22px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; }
  .brand span { display: block; font-size: 11px; font-weight: 500; letter-spacing: .3em; color: var(--muted); margin-top: 6px; }
  .doc { text-align: right; }
  .doc h1 { margin: 0; font-size: 30px; letter-spacing: .2em; text-transform: uppercase; color: var(--accent); }
  .doc p { margin: 4px 0 0; color: var(--muted); font-size: 13px; }
  .doc strong { color: var(--ink); }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 26px; }
  .box h2 { margin: 0 0 8px; font-size: 11px; letter-spacing: .18em; text-transform: uppercase; color: var(--muted); }
  .box p { margin: 0; font-size: 13.5px; }
  .box .name { font-weight: 700; }
  .pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px;
          font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
  .pill-paid { background: #e6f4ea; color: #14653a; }
  .pill-due { background: #fdf1e3; color: #8a5a10; }
  .pill-state { background: #f1e9ef; color: var(--accent); }
  table { width: 100%; border-collapse: collapse; margin-top: 28px; }
  thead th { text-align: left; font-size: 10.5px; letter-spacing: .16em; text-transform: uppercase;
             color: var(--muted); border-bottom: 2px solid var(--ink); padding: 8px 6px; }
  thead th.num, td.num { text-align: right; }
  tbody td { padding: 11px 6px; border-bottom: 1px solid var(--line); font-size: 13.5px; vertical-align: top; }
  tbody td .sku { display: block; color: var(--muted); font-size: 11.5px; margin-top: 3px; }
  .totals { display: flex; justify-content: flex-end; margin-top: 18px; }
  .totals table { width: 320px; margin-top: 0; }
  .totals td { border: none; padding: 5px 6px; font-size: 13.5px; }
  .totals tr.grand td { border-top: 2px solid var(--ink); font-weight: 800; font-size: 16px; padding-top: 10px; }
  .totals tr.grand .amt { color: var(--accent); }
  .meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 30px;
          padding-top: 18px; border-top: 1px solid var(--line); }
  .meta h3 { margin: 0 0 6px; font-size: 10.5px; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); }
  .meta p { margin: 0; font-size: 12.5px; }
  .timeline { margin-top: 26px; }
  .timeline ul { list-style: none; margin: 8px 0 0; padding: 0; display: flex; flex-wrap: wrap; gap: 8px 18px; }
  .timeline li { font-size: 12.5px; color: var(--muted); }
  .timeline li b { color: var(--ink); }
  .notes { margin-top: 22px; background: #faf7f5; border-left: 3px solid var(--accent); padding: 12px 16px; font-size: 12.5px; }
  footer.bottom { margin-top: 34px; padding-top: 16px; border-top: 1px solid var(--line);
                  display: flex; justify-content: space-between; gap: 16px; font-size: 11.5px; color: var(--muted); }
  @page { margin: 14mm; }
  @media print {
    body { background: #fff; }
    .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; }
    .no-print { display: none !important; }
    a { text-decoration: none; color: inherit; }
  }
</style>
</head>
<body>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="<?= e($backUrl) ?>">&larr; <?= e($backLabel) ?></a>
  <div style="display:flex; gap:10px">
    <button class="btn btn-primary" type="button" onclick="window.print()">Print / save PDF</button>
  </div>
</div>

<div class="sheet">
  <header class="top">
    <div class="brand">
      <?= e(setting('site_name')) ?>
      <span><?= e(setting('site_tagline')) ?></span>
      <p style="margin-top:12px; font-weight:400; font-size:12.5px; letter-spacing:normal; text-transform:none; color:var(--muted); line-height:1.6">
        <?= e(setting('address_line')) ?><br/>
        <?= e(setting('address_city')) ?>, <?= e(setting('address_country')) ?><br/>
        <?= e(setting('contact_phone')) ?> &middot; <?= e(setting('contact_email')) ?>
      </p>
    </div>
    <div class="doc">
      <h1>Invoice</h1>
      <p>No. <strong><?= e((string) $order['order_no']) ?></strong></p>
      <p>Issued <?= e($placed) ?></p>
      <p style="margin-top:8px">
        <span class="pill <?= $order['payment_status'] === 'paid' ? 'pill-paid' : 'pill-due' ?>">
          <?= $order['payment_status'] === 'paid' ? 'Paid' : 'Payment due' ?>
        </span>
        <span class="pill pill-state"><?= e(ucfirst((string) $order['status'])) ?></span>
      </p>
    </div>
  </header>

  <div class="grid">
    <div class="box">
      <h2>Billed to</h2>
      <p class="name"><?= e((string) $order['customer_name']) ?></p>
      <p>
        <?= e((string) $order['email']) ?><br/>
        <?= e((string) $order['phone']) ?>
      </p>
    </div>
    <div class="box">
      <h2>Ship to</h2>
      <p class="name"><?= e((string) $order['customer_name']) ?></p>
      <p>
        <?= e((string) $order['address']) ?><br/>
        <?= e((string) $order['city']) ?>, <?= e((string) $order['region']) ?><br/>
        <?= e((string) $order['shipping_label']) ?>
      </p>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:34px">#</th>
        <th>Item</th>
        <th class="num" style="width:90px">Unit</th>
        <th class="num" style="width:52px">Qty</th>
        <th class="num" style="width:110px">Amount</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $i => $it): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td>
            <?= e((string) $it['product_name']) ?>
            <span class="sku">
              <?= e((string) ($it['variant_text'] ?? '')) !== '' ? e((string) $it['variant_text']) : 'Standard' ?>
            </span>
          </td>
          <td class="num"><?= e(price($it['unit_price'])) ?></td>
          <td class="num"><?= (int) $it['qty'] ?></td>
          <td class="num"><?= e(price($it['line_total'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="totals">
    <table>
      <tr><td>Subtotal</td><td class="num"><?= e(price($order['subtotal'])) ?></td></tr>
      <?php if ((float) $order['discount'] > 0): ?>
        <tr><td>Discount (<?= e((string) $order['promo_code']) ?>)</td><td class="num">-<?= e(price($order['discount'])) ?></td></tr>
      <?php endif; ?>
      <tr><td>Delivery</td><td class="num"><?= (float) $order['shipping_fee'] > 0 ? e(price($order['shipping_fee'])) : 'Free' ?></td></tr>
      <tr class="grand"><td>Total</td><td class="num amt"><?= e(price($order['total'])) ?></td></tr>
    </table>
  </div>

  <div class="meta">
    <div>
      <h3>Payment method</h3>
      <p><?= e(payment_channel_label((string) $order['payment_channel'])) ?></p>
      <?php if ((string) $order['payment_reference'] !== '' && $order['payment_reference'] !== null): ?>
        <p>Ref <?= e((string) $order['payment_reference']) ?></p>
      <?php endif; ?>
    </div>
    <div>
      <h3>Order status</h3>
      <p><?= e(ucfirst((string) $order['status'])) ?></p>
      <p><?= e((string) $order['shipping_method']) ?></p>
    </div>
    <div>
      <h3>Payment status</h3>
      <p><?= $order['payment_status'] === 'paid' ? 'Settled in full' : 'Awaiting confirmation' ?></p>
      <p>Currency: Ghana cedi (GH&#8373;)</p>
    </div>
  </div>

  <?php if ($events !== []): ?>
    <div class="timeline">
      <h3 style="font-size:10.5px; letter-spacing:.16em; text-transform:uppercase; color:var(--muted); margin:0">Order activity</h3>
      <ul>
        <?php foreach ($events as $ev): ?>
          <li><b><?= e(date('d M Y, H:i', strtotime((string) $ev['created_at']))) ?></b> &mdash; <?= e((string) $ev['note']) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ((string) $order['notes'] !== ''): ?>
    <div class="notes"><strong>Customer note:</strong> <?= e((string) $order['notes']) ?></div>
  <?php endif; ?>

  <footer class="bottom">
    <span>Thank you for shopping with <?= e(setting('site_name')) ?>. Keep this invoice for warranty and returns.</span>
    <span><?= e(setting('contact_hours')) ?></span>
  </footer>
</div>
</body>
</html>
