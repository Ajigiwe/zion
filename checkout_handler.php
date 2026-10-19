<?php
/** Creates the order from the checkout form. Included by actions.php. */

declare(strict_types=1);

// Normally included by actions.php; loaded directly too so a stray hit
// renders the proper error flow instead of a fatal.
require_once __DIR__ . '/config.php';

// A direct hit skips the CSRF check that actions.php / checkout.php perform,
// so repeat it here. It is idempotent for those include sites, which validate
// the same token before requiring this file.
csrf_check();

// actions.php defines this helper; a standalone request still needs it, and
// without it every error path below used to end in a fatal instead.
if (!function_exists('redirect_back')) {
    /** @internal Standalone fallback for the actions.php helper. */
    function redirect_back(string $to): never
    {
        header('Location: ' . $to);
        exit;
    }
}

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
// Paystack with no keys is the simulation/demo path. Handy while developing,
// but in production it would confirm orders nobody paid for - refuse there.
$isDemoPaystack = ($channel === 'paystack') && !is_paystack_configured();
if ($isDemoPaystack && !paystack_demo_allowed()) {
    $errors[] = 'Online payment is unavailable right now - please choose Cash on Delivery.';
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

$submittedRef = trim((string) ($_POST['paystack_ref'] ?? ''));
$useLivePaystack = ($channel === 'paystack') && is_paystack_configured();

// If a reference came back from the inline popup, verify it against what this
// order actually costs - the popup takes its amount from the browser, so a
// "success" on its own proves only that *something* was paid.
$isVerifiedPaystack = false;
if ($useLivePaystack && $submittedRef !== '') {
    $check = paystack_verify_payment($submittedRef, $total);
    if (!$check['ok']) {
        $msg = $check['message'];
        if ($isAjax) {
            json_out(['ok' => false, 'message' => 'Payment verification failed: ' . $msg]);
        }
        flash_set('error', 'Payment verification failed: ' . $msg);
        redirect_back(url('checkout.php'));
    }
    $isVerifiedPaystack = true;
}

$paid = ($channel !== 'cod') && ($isVerifiedPaystack || $isDemoPaystack);
$status = $paid ? 'confirmed' : 'pending';
$finalPayRef = $isVerifiedPaystack ? $submittedRef : ($paid ? strtoupper($channel) . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)) : null);

try {
    $orderInfo = db_tx(function (PDO $pdo) use (
        $lines, $name, $email, $phone, $region, $city, $address,
        $shipCode, $shipLabel, $shipFee, $subtotal, $discount, $promoCode, $total,
        $channel, $finalPayRef, $notes, $discreet, $status, $paid, $useLivePaystack
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
            $finalPayRef,
            $paid ? 'paid' : 'pending',
            $status,
            $discreet,
            $notes !== '' ? $notes : null,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        // Reserve stock while the product rows are locked, before writing any
        // item: this is what stops two buyers taking the last unit, and it
        // fails loudly (rolling the whole order back) instead of clamping a
        // short stock level to zero like the old GREATEST(0, ...) did.
        order_reserve_stock($pdo, array_map(static fn (array $l): array => [
            'product_id' => (int) $l['product_id'],
            'qty'        => (int) $l['qty'],
            'label'      => (string) $l['name'],
        ], $lines));

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
} catch (DomainException $ex) {
    // Stock shortfall (or an item that vanished): db_tx already rolled back,
    // so no half-written order exists - send the customer to fix their bag.
    if ($isAjax) {
        json_out(['ok' => false, 'message' => $ex->getMessage()]);
    }
    flash_set('error', $ex->getMessage());
    redirect_back(url('cart.php'));
} catch (Throwable $ex) {
    error_log('order failed: ' . $ex->getMessage());
    if ($isAjax) {
        json_out(['ok' => false, 'message' => 'We could not complete your order. Please try again.']);
    }
    flash_set('error', 'We could not complete your order. Please try again.');
    redirect_back(url('checkout.php'));
}

[$orderNo, $orderId] = $orderInfo;

// If live Paystack is configured AND payment was not already verified via inline popup, redirect to hosted page
if ($useLivePaystack && !$isVerifiedPaystack) {
    $u = current_user();
    $payEmail = $email !== '' ? $email : (($u['email'] ?? '') ?: ('customer_' . preg_replace('/\D/', '', $phone) . '@ziongroups.com.gh'));
    $pubKey = paystack_public_key();

    if ($pubKey === '') {
        if ($isAjax) {
            json_out(['ok' => false, 'message' => 'Paystack Public Key is missing in .env or settings.']);
        }
        flash_set('error', 'Paystack Public Key is missing in .env or settings.');
        redirect_back(url('checkout.php'));
    }

    $_SESSION['pending_paystack_order'] = $orderNo;
    $callbackUrl = full_url('paystack_callback.php');

    // Standard Non-AJAX fallback (Redirect)
    $init = paystack_api_request('transaction/initialize', 'POST', [
        'email'        => $payEmail,
        'amount'       => (int) round($total * 100), // GHS in pesewas
        'currency'     => 'GHS',
        'reference'    => $orderNo,
        'callback_url' => $callbackUrl,
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
