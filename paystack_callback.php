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

// 1. Locate the order first: verification has to be checked against the
//    server-side total, and a verified payment we cannot place is not ours.
$order = db_one('SELECT * FROM orders WHERE order_no = ? OR payment_reference = ?', [$reference, $reference]);

if ($order === null) {
    flash_set('error', 'Order not found for payment reference: ' . $reference);
    header('Location: ' . url('cart.php'));
    exit;
}

// 2. Verify the transaction with Paystack - amount, currency and that this
//    reference has not been spent on another order already.
$check = paystack_verify_payment($reference, (float) $order['total'], (string) $order['order_no']);

if (!$check['ok']) {
    $msg = $check['message'];

    // Record the failure once, so the customer can simply retry payment.
    if ($order['payment_status'] === 'pending') {
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

// 3. Mark order as paid and confirmed
$data         = $check['data'];
$channelUsed  = (string) ($data['channel'] ?? 'paystack');
$payRef       = (string) ($data['reference'] ?? $reference);
$orderId      = (int) $order['id'];

try {
    $finalStatus = db_tx(function (PDO $pdo) use ($order, $orderId, $channelUsed, $payRef): string {
        // Lock the order row first: the abandoned-payment sweep must not be
        // able to return stock between the check below and this payment.
        $cur = db_one('SELECT id, status, payment_status FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if ($cur === null) {
            throw new RuntimeException('Order vanished while finalising payment.');
        }

        $final = 'confirmed';

        // Stock is given back when a Paystack order goes unpaid for too long.
        // If this one paid late, take it again - or hold the order for staff
        // rather than confirm something we can no longer ship.
        if ($cur['status'] === 'cancelled' && order_stock_released($orderId)) {
            $items = db_all('SELECT product_id, product_name, qty FROM order_items WHERE order_id = ?', [$orderId]);
            try {
                order_reserve_stock($pdo, array_map(static fn (array $i): array => [
                    'product_id' => (int) $i['product_id'],
                    'qty'        => (int) $i['qty'],
                    'label'      => (string) $i['product_name'],
                ], $items));
                db_exec("DELETE FROM order_events WHERE order_id = ? AND status = 'stock'", [$orderId]);
            } catch (DomainException $ex) {
                $final = 'pending';
                error_log('paid without stock - ' . $order['order_no'] . ': ' . $ex->getMessage());
            }
        }

        db_exec(
            'UPDATE orders SET payment_status = ?, status = ?, payment_channel = ?, payment_reference = ?, updated_at = NOW() WHERE id = ?',
            ['paid', $final, 'paystack', $payRef, $orderId]
        );
        db_exec(
            'INSERT INTO order_events (order_id, status, note) VALUES (?,?,?)',
            [
                $orderId,
                $final,
                $final === 'confirmed'
                    ? 'Payment verified via Paystack (' . ucfirst($channelUsed) . ' - Ref: ' . $payRef . ')'
                    : 'Payment received but stock is unavailable - manual fulfilment required (Ref: ' . $payRef . ')',
            ]
        );

        return $final;
    });
} catch (Throwable $ex) {
    // The payment itself is verified, so never pretend it did not happen.
    error_log('payment finalise failed - ' . $order['order_no'] . ': ' . $ex->getMessage());
    flash_set(
        'error',
        'Your payment (ref ' . $payRef . ') was received but the order could not be finalised. '
        . 'Please contact us with that reference and we will complete it manually.'
    );
    header('Location: ' . url('checkout.php'));
    exit;
}

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

flash_set(
    'success',
    $finalStatus === 'confirmed'
        ? 'Payment successful! Order ' . $order['order_no'] . ' is confirmed.'
        : 'Payment received for order ' . $order['order_no']
            . ', but the items sold out while you were paying - our team will contact you to arrange the next step.'
);
header('Location: ' . url('order_complete.php?no=' . urlencode($order['order_no'])));
exit;
