<?php
/**
 * Paystack Checkout Callback / Redirect Handler.
 *
 * Paystack redirects the customer here after payment authorization.
 * Verifies the transaction reference with Paystack API before finalizing the order.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

$reference = trim((string) ($_GET['reference'] ?? $_GET['trxref'] ?? ''));

if ($reference === '') {
    flash_set('error', 'No transaction reference received from payment gateway.');
    header('Location: ' . url('checkout.php'));
    exit;
}

// 1. Verify transaction with Paystack API
$verify = paystack_api_request('transaction/verify/' . urlencode($reference));

if (!$verify['ok'] || ($verify['data']['status'] ?? '') !== 'success') {
    $msg = $verify['data']['gateway_response'] ?? $verify['message'] ?? 'Payment could not be verified.';
    
    // Check if order exists in DB to record failed event
    $order = db_one('SELECT id, order_no FROM orders WHERE order_no = ? OR payment_reference = ?', [$reference, $reference]);
    if ($order !== null) {
        db_exec(
            'UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?',
            ['failed', $order['id']]
        );
        db_exec(
            'INSERT INTO order_events (order_id, status, note) VALUES (?,?,?)',
            [$order['id'], 'pending', 'Paystack payment verification failed: ' . $msg]
        );
    }

    flash_set('error', 'Payment incomplete or cancelled: ' . $msg);
    header('Location: ' . url('checkout.php'));
    exit;
}

// 2. Locate order in database
$data = $verify['data'];
$orderNo = $data['metadata']['order_no'] ?? $reference;
$order = db_one('SELECT * FROM orders WHERE order_no = ? OR payment_reference = ?', [$orderNo, $reference]);

if ($order === null) {
    flash_set('error', 'Order not found for verified payment reference: ' . $reference);
    header('Location: ' . url('cart.php'));
    exit;
}

// 3. Mark order as paid and confirmed
$channelUsed = (string) ($data['channel'] ?? 'paystack');
$payRef = (string) ($data['reference'] ?? $reference);

db_exec(
    'UPDATE orders SET payment_status = ?, status = ?, payment_channel = ?, payment_reference = ?, updated_at = NOW() WHERE id = ?',
    ['paid', 'confirmed', 'paystack', $payRef, $order['id']]
);

db_exec(
    'INSERT INTO order_events (order_id, status, note) VALUES (?,?,?)',
    [$order['id'], 'confirmed', 'Payment verified via Paystack (' . ucfirst($channelUsed) . ' - Ref: ' . $payRef . ')']
);

// 4. Clear cart & finalize customer session
cart_clear();
unset($_SESSION['promo_code']);
unset($_SESSION['pending_paystack_order']);

if (!isset($_SESSION['my_orders']) || !is_array($_SESSION['my_orders'])) {
    $_SESSION['my_orders'] = [];
}
if (!in_array($order['order_no'], $_SESSION['my_orders'], true)) {
    $_SESSION['my_orders'][] = $order['order_no'];
}

// 5. Low-stock watch check
$low = db_all(
    'SELECT name, sku, stock FROM products
      WHERE is_active = 1 AND stock <= ?
      ORDER BY stock ASC, name LIMIT 20',
    [LOW_STOCK_THRESHOLD]
);
if ($low !== []) {
    $stampFile = __DIR__ . '/storage/logs/last-low-stock-alert';
    $last = is_file($stampFile) ? (int) @file_get_contents($stampFile) : 0;
    if (time() - $last > 3600 && send_low_stock_alert($low, 'Triggered by order ' . $order['order_no'])) {
        @mkdir(dirname($stampFile), 0775, true);
        @file_put_contents($stampFile, (string) time());
    }
}

flash_set('success', 'Payment successful! Order ' . $order['order_no'] . ' is confirmed.');
header('Location: ' . url('order_complete.php?no=' . urlencode($order['order_no'])));
exit;
