<?php
/** Creates the order from the checkout form. Included by actions.php. */

declare(strict_types=1);

// Normally included by actions.php; loaded directly too so a stray hit
// renders the proper error flow instead of a fatal.
require_once __DIR__ . '/config.php';

if (!function_exists('json_out')) {
    function json_out(array $payload): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$isAjax = $isAjax ?? (
    strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
    || (!empty($_POST['ajax']) && $_POST['ajax'] === '1')
);

$lines = cart_rows();
if ($lines === []) {
    if ($isAjax) {
        json_out(['ok' => false, 'message' => 'Your bag is empty.']);
    }
    flash_set('error', 'Your bag is empty.');
    redirect_back(url('cart.php'));
}

$name    = trim((string) ($_POST['name'] ?? ''));
$phone   = trim((string) ($_POST['phone'] ?? ''));
$email   = trim((string) ($_POST['email'] ?? ''));
$region  = trim((string) ($_POST['region'] ?? ''));
$city    = trim((string) ($_POST['city'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$method  = (string) ($_POST['shipping_method'] ?? 'metro');
$channel = (string) ($_POST['payment_channel'] ?? 'paystack');
$notes   = trim((string) ($_POST['notes'] ?? ''));
$discreet = isset($_POST['discreet_pack']) ? 1 : 0;

$errors = [];
if ($name === '')    { $errors[] = 'Full name is required.'; }
if ($phone === '')   { $errors[] = 'Phone number is required.'; }
if ($region === '')  { $errors[] = 'Region is required.'; }
if ($city === '')    { $errors[] = 'City / neighbourhood is required.'; }
if ($address === '') { $errors[] = 'Street address is required.'; }
if (!in_array($channel, ['paystack', 'cod'], true)) {
    $errors[] = 'Please choose a payment method.';
}
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = 'That email address is not valid.';
}

if (!in_array($method, ['metro', 'regional', 'pickup'], true)) {
    $method = 'metro';
}
[$shipCode, $shipLabel, $shipFee] = shipping_quote($region, $method);

if ($errors !== []) {
    if ($isAjax) {
        json_out(['ok' => false, 'message' => implode(' ', $errors)]);
    }
    flash_set('error', implode(' ', $errors));
    redirect_back(url('checkout.php'));
}

$subtotal = 0.0;
foreach ($lines as $l) {
    $subtotal += (float) $l['unit_price'] * (int) $l['qty'];
}

$discount = 0.0;
$promoCode = null;
if (!empty($_SESSION['promo_code'])) {
    $promo = promo_lookup((string) $_SESSION['promo_code']);
    if ($promo !== null) {
        $discount = promo_discount($promo, $subtotal);
        $promoCode = $promo['code'];
    } else {
        unset($_SESSION['promo_code']);
    }
}

$total = max(0.0, $subtotal - $discount + $shipFee);

$useLivePaystack = ($channel === 'paystack') && is_paystack_configured();
$paid = ($channel !== 'cod') && !$useLivePaystack;
$status = $paid ? 'confirmed' : 'pending';

try {
    $orderInfo = db_tx(function (PDO $pdo) use (
        $lines, $name, $email, $phone, $region, $city, $address,
        $shipCode, $shipLabel, $shipFee, $subtotal, $discount, $promoCode, $total,
        $channel, $notes, $discreet, $status, $paid, $useLivePaystack
    ): array {
        $orderNo = next_order_no();
        $u = current_user();

        $st = $pdo->prepare(
            'INSERT INTO orders
             (order_no, user_id, customer_name, email, phone, region, city, address,
              shipping_method, shipping_label, shipping_fee, subtotal, discount, promo_code,
              total, payment_channel, payment_reference, payment_status, status, discreet_pack, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $orderNo,
            $u['id'] ?? null,
            $name,
            $email !== '' ? $email : ($u['email'] ?? null),
            $phone,
            $region,
            $city,
            $address,
            $shipCode,
            $shipLabel,
            $shipFee,
            $subtotal,
            $discount,
            $promoCode,
            $total,
            $channel,
            $paid ? strtoupper($channel) . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)) : ($useLivePaystack ? $orderNo : null),
            $paid ? 'paid' : 'pending',
            $status,
            $discreet,
            $notes !== '' ? $notes : null,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $ins = $pdo->prepare(
            'INSERT INTO order_items
             (order_id, product_id, product_name, product_image, variant_text, unit_price, qty, line_total)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        foreach ($lines as $l) {
            $variant = trim(implode(' - ', array_filter([
                $l['variant_color'] ?? '',
                $l['variant_size'] ?? '',
            ])));
            $ins->execute([
                $orderId,
                (int) $l['product_id'],
                $l['name'],
                $l['image_url'],
                $variant !== '' ? $variant : null,
                $l['unit_price'],
                (int) $l['qty'],
                (float) $l['unit_price'] * (int) $l['qty'],
            ]);
            // decrement stock
            $pdo->prepare('UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?')
                ->execute([(int) $l['qty'], (int) $l['product_id']]);
        }

        $ev = $pdo->prepare('INSERT INTO order_events (order_id, status, note) VALUES (?,?,?)');
        $ev->execute([$orderId, 'pending', 'Order created']);
        if ($paid) {
            $ev->execute([$orderId, 'confirmed', 'Payment authorised - ' . $shipLabel]);
        } elseif ($useLivePaystack) {
            $ev->execute([$orderId, 'pending', 'Awaiting Paystack payment authorization']);
        }

        return [$orderNo, $orderId];
    });
} catch (Throwable $ex) {
    error_log('order failed: ' . $ex->getMessage());
    if ($isAjax) {
        json_out(['ok' => false, 'message' => 'We could not complete your order. Please try again.']);
    }
    flash_set('error', 'We could not complete your order. Please try again.');
    redirect_back(url('checkout.php'));
}

[$orderNo, $orderId] = $orderInfo;

// If live Paystack is configured, handle popup or hosted redirect
if ($useLivePaystack) {
    $u = current_user();
    $payEmail = $email !== '' ? $email : (($u['email'] ?? '') ?: ('customer_' . preg_replace('/\D/', '', $phone) . '@ziongroups.com.gh'));
    $_SESSION['pending_paystack_order'] = $orderNo;

    // Return JSON for Paystack Popup (Inline)
    if ($isAjax) {
        json_out([
            'ok'            => true,
            'paystack'      => true,
            'key'           => paystack_public_key(),
            'email'         => $payEmail,
            'amount'        => (int) round($total * 100), // GHS in pesewas
            'currency'      => 'GHS',
            'reference'     => $orderNo,
            'order_no'      => $orderNo,
            'customer_name' => $name,
            'phone'         => $phone,
            'callback_url'  => APP_URL . url('paystack_callback.php'),
        ]);
    }

    // Standard Non-AJAX fallback (Redirect)
    $init = paystack_api_request('transaction/initialize', 'POST', [
        'email'        => $payEmail,
        'amount'       => (int) round($total * 100), // GHS in pesewas
        'currency'     => 'GHS',
        'reference'    => $orderNo,
        'callback_url' => APP_URL . url('paystack_callback.php'),
        'metadata'     => [
            'order_no'      => $orderNo,
            'customer_name' => $name,
            'phone'         => $phone,
        ],
    ]);

    if ($init['ok'] && !empty($init['data']['authorization_url'])) {
        header('Location: ' . $init['data']['authorization_url']);
        exit;
    }

    // Paystack API initialization failed - notify customer and retain cart
    error_log('Paystack initialization error: ' . ($init['message'] ?? 'Unknown error'));
    flash_set('error', 'Payment gateway error: ' . ($init['message'] ?? 'Could not initialize Paystack checkout. Please try again.'));
    redirect_back(url('checkout.php'));
}

// Simulated / Cash on Delivery flow
cart_clear();
unset($_SESSION['promo_code']);
if (!isset($_SESSION['my_orders']) || !is_array($_SESSION['my_orders'])) {
    $_SESSION['my_orders'] = [];
}
$_SESSION['my_orders'][] = $orderNo;

// Low-stock watch - at most one alert per hour, only when something is at/below threshold.
$low = db_all(
    'SELECT name, sku, stock FROM products
      WHERE is_active = 1 AND stock <= ?
      ORDER BY stock ASC, name LIMIT 20',
    [LOW_STOCK_THRESHOLD]
);
if ($low !== []) {
    $stampFile = __DIR__ . '/storage/logs/last-low-stock-alert';
    $last = is_file($stampFile) ? (int) @file_get_contents($stampFile) : 0;
    if (time() - $last > 3600 && send_low_stock_alert($low, 'Triggered by order ' . $orderNo)) {
        @mkdir(dirname($stampFile), 0775, true);
        @file_put_contents($stampFile, (string) time());
    }
}

if ($isAjax) {
    flash_set('success', 'Order ' . $orderNo . ' confirmed. Thank you, ' . strtok($name, ' ') . '.');
    json_out([
        'ok'       => true,
        'paystack' => false,
        'redirect' => url('order_complete.php?no=' . urlencode($orderNo)),
    ]);
}

flash_set('success', 'Order ' . $orderNo . ' confirmed. Thank you, ' . strtok($name, ' ') . '.');
header('Location: ' . url('order_complete.php?no=' . urlencode($orderNo)));
exit;
