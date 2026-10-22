<?php
/**
 * End-to-end HTTP smoke suite for the security and stock fixes.
 *
 *   php tools/smoke_http.php           run, then drop the throwaway database
 *   php tools/smoke_http.php --keep    keep <DB_NAME>_smoke for inspection
 *
 * The suite provisions <DB_NAME>_smoke, seeds a customer account with a random
 * password (the shipped seed deliberately carries none), then starts its own
 * pair of PHP servers and drives them with curl:
 *
 *   8098  APP_ENV=development  - the simulated (key-less) Paystack flow is
 *                                allowed, so a demo order completes
 *   8097  APP_ENV=production   - the same flow must be refused, while cash on
 *                                delivery still works
 *
 * 44 checks: page rendering, sign-in success and brute-force throttling, the
 * checkout/stock path, order-confirmation IDOR, the production demo gate and
 * the Telegram webhook's secret header / chat whitelist.
 *
 * Both servers and the database are torn down on exit, pass or fail.
 */
declare(strict_types=1);

require __DIR__ . '/smoke_lib.php';

$boot = smoke_boot();
$pdo  = $boot['pdo'];

/* ------------------------------------------------------------- fixtures */

// The seed ships accounts with unknown passwords, so give the demo customer
// one for the duration of this run only.
$customerEmail = 'kwame.mensah@ziongroups.com.gh';
$customerPw    = smoke_password();
$pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?')
    ->execute([password_hash($customerPw, PASSWORD_DEFAULT), $customerEmail]);

// Pick a product with enough stock to be ordered twice during the run.
$row = $pdo->query(
    'SELECT id, slug, name, stock FROM products WHERE is_active = 1 AND stock > 3 ORDER BY stock DESC, id LIMIT 1'
)->fetch();
if ($row === false) {
    fwrite(STDERR, "smoke: the seed has no active product with stock > 3\n");
    exit(2);
}
$productId = (int) $row['id'];
$slug      = (string) $row['slug'];
$productNm = (string) $row['name'];

// Fresh run: no stale throttle counters or cookie jars from a previous attempt.
$pdo->exec('DELETE FROM login_attempts');
foreach (glob(sys_get_temp_dir() . '/velora_jar_' . $GLOBALS['smoke_run'] . '_*.txt') ?: [] as $old) {
    @unlink($old);
}

/* ------------------------------------------------------------- servers */

$keys = ['PAYSTACK_PUBLIC_KEY' => '', 'PAYSTACK_SECRET_KEY' => ''];

// Telegram webhook tests: the secret is set, the bot token is deliberately
// empty (replies and downloads must fail silently and never reach the
// network), and exactly one chat is whitelisted.
$whSecret = 'smoke' . bin2hex(random_bytes(10));
$whChat   = 999001;
$whOther  = 999002;
$tgKeys   = [
    'TG_BOT_TOKEN'        => '',
    'TG_WEBHOOK_SECRET'   => $whSecret,
    'TG_ALLOWED_CHAT_IDS' => (string) $whChat,
];

$dev = smoke_server(8098, $keys + $tgKeys + [
    'APP_ENV'  => 'development',
    'APP_DEBUG' => 'true',
    'APP_URL'  => 'http://127.0.0.1:8098',
]);
$prod = smoke_server(8097, $keys + $tgKeys + [
    'APP_ENV'  => 'production',
    'APP_DEBUG' => 'false',
    'APP_URL'  => 'http://127.0.0.1:8097',
]);
$GLOBALS['smoke_servers'] = [$dev, $prod];
smoke_wait($dev);
smoke_wait($prod);

$devBase  = $dev['base'];
$prodBase = $prod['base'];

$int = static fn (PDO $p, string $sql): int => (int) $p->query($sql)->fetchColumn();

/* --------------------------------------------------------- A: pages render */

group('storefront renders (the abandoned-order hook runs on every homepage)');

$r = req($devBase . '/index.php', null, smoke_jar('home'));
okk('homepage 200', $r['code'] === 200, 'code=' . $r['code'] . ' loc=' . $r['location']);
okk('homepage clean (no PHP diagnostics)', dirty($r['body']) === '', dirty($r['body']));

$r2 = req($devBase . '/product.php?slug=' . urlencode($slug), null, smoke_jar('home'));
okk('product page 200', $r2['code'] === 200, 'code=' . $r2['code']);
okk('product page clean', dirty($r2['body']) === '', dirty($r2['body']));

/* ---------------------------------------------------- B: successful login */

group('sign-in success clears the throttle (issue #5)');

$login = req($devBase . '/login.php', null, smoke_jar('ok'));
$tok   = csrf_of($login['body']);
okk('login form serves a CSRF token', $tok !== '');

$r = req($devBase . '/login.php', [
    'csrf' => $tok, 'email' => $customerEmail, 'password' => $customerPw,
], smoke_jar('ok'));
okk(
    'correct password signs in',
    $r['code'] === 302 && str_contains($r['location'], 'account.php'),
    'code=' . $r['code'] . ' loc=' . $r['location']
);
okk('no throttle rows left behind', $int($pdo, 'SELECT COUNT(*) FROM login_attempts') === 0);

/* --------------------------------------------------------- C: brute force */

group('sign-in brute force is throttled (issue #5)');

$login = req($devBase . '/login.php', null, smoke_jar('bf'));
$tok   = csrf_of($login['body']);
$codes = [];
$blockedOn = 0;
for ($i = 1; $i <= 6; $i++) {
    $r = req($devBase . '/login.php', ['csrf' => $tok, 'email' => $customerEmail, 'password' => 'definitely-wrong-' . $i], smoke_jar('bf'));
    $codes[] = $r['code'];
    if (str_contains($r['body'], 'Too many sign-in attempts')) {
        $blockedOn = $i;
        break;
    }
}
okk(
    'refused no later than the 6th attempt',
    $blockedOn > 0 && $blockedOn <= 6,
    'blockedOn=' . $blockedOn . ' codes=' . implode(',', $codes)
);
$attempts = $int($pdo, 'SELECT attempts FROM login_attempts WHERE scope = '
    . $pdo->quote('customer') . ' AND ident = ' . $pdo->quote($customerEmail));
okk('exactly 5 failures counted', $attempts === 5, 'attempts=' . $attempts);
okk('shared client-IP counter also counts', $int($pdo, "SELECT COUNT(*) FROM login_attempts WHERE scope='shared'") === 1);

// Even the RIGHT password is refused while the identity is locked.
$r = req($devBase . '/login.php', ['csrf' => $tok, 'email' => $customerEmail, 'password' => $customerPw], smoke_jar('bf'));
okk('correct password still refused while locked', str_contains($r['body'], 'Too many sign-in attempts'));
$pdo->exec('DELETE FROM login_attempts');

/* ------------------------------------------------- D: demo checkout (dev) */

group('demo checkout works in development (issues #2/#3/#7)');

$page = req($devBase . '/product.php?slug=' . urlencode($slug), null, smoke_jar('buy'));
$tok  = csrf_of($page['body']);

$r = req($devBase . '/actions.php', ['action' => 'add_to_cart', 'product_id' => $productId, 'qty' => 2, 'csrf' => $tok], smoke_jar('buy'));
okk('item added to the bag', $r['code'] === 302, 'code=' . $r['code'] . ' body=' . substr(strip_tags($r['body']), 0, 120));

$co  = req($devBase . '/checkout.php', null, smoke_jar('buy'));
$tok = csrf_of($co['body']);
okk('checkout page loads with the bag', $co['code'] === 200 && $tok !== '', 'code=' . $co['code']);

$stockBefore  = $int($pdo, "SELECT stock FROM products WHERE id = $productId");
$ordersBefore = $int($pdo, 'SELECT COUNT(*) FROM orders');

$r = req($devBase . '/checkout.php', [
    'csrf' => $tok, 'name' => 'Kwame Mensah', 'email' => $customerEmail,
    'phone' => '0240000000', 'region' => 'Greater Accra Region', 'city' => 'Tarkwa',
    'address' => '1 Test Street', 'shipping_method' => 'metro', 'payment_channel' => 'paystack',
], smoke_jar('buy'));

okk('checkout accepted', $r['code'] === 302, 'code=' . $r['code'] . ' loc=' . $r['location']);
okk(
    'redirected to the order page',
    (bool) preg_match('#order_complete\.php\?no=(VEL-[0-9]+-[0-9]+)#', $r['location'], $m),
    'loc=' . $r['location']
);
$orderNo = $m[1] ?? '';
okk('one order created', $int($pdo, 'SELECT COUNT(*) FROM orders') === $ordersBefore + 1);

$order = $orderNo !== '' ? ($pdo->query('SELECT * FROM orders WHERE order_no = ' . $pdo->quote($orderNo))->fetch() ?: null) : null;
okk('order recorded', $order !== null, $orderNo);
okk(
    'demo order is paid + confirmed in dev',
    $order !== null && $order['payment_status'] === 'paid' && $order['status'] === 'confirmed',
    $order !== null ? $order['payment_status'] . '/' . $order['status'] : '-'
);
okk(
    'payment reference recorded',
    $order !== null && str_starts_with((string) $order['payment_reference'], 'PAYSTACK-'),
    $order !== null ? (string) $order['payment_reference'] : '-'
);
$stockAfter = $int($pdo, "SELECT stock FROM products WHERE id = $productId");
okk(
    'stock decremented by exactly the quantity ordered (2)',
    $stockAfter === $stockBefore - 2,
    "before=$stockBefore after=$stockAfter"
);
okk(
    'order events written',
    $int($pdo, 'SELECT COUNT(*) FROM order_events e JOIN orders o ON o.id = e.order_id WHERE o.order_no = ' . $pdo->quote($orderNo)) >= 2
);

$oc = req($devBase . '/order_complete.php?no=' . urlencode($orderNo), null, smoke_jar('buy'));
okk('the buyer sees their confirmation page', $oc['code'] === 200 && str_contains($oc['body'], 'order is confirmed'), 'code=' . $oc['code']);
okk('confirmation page is free of PHP diagnostics', dirty($oc['body']) === '', dirty($oc['body']));

/* --------------------------------------------------------------- E: IDOR */

group('order confirmation is not readable by strangers (issue #4)');

$anon = req($devBase . '/order_complete.php?no=' . urlencode($orderNo), null, smoke_jar('anon'));
okk(
    'anonymous visitor is turned away',
    $anon['code'] === 302 && str_contains($anon['location'], 'index.php'),
    'code=' . $anon['code'] . ' loc=' . $anon['location']
);
okk('turn-away leaks no order details', !str_contains($anon['body'], $orderNo) && !str_contains($anon['body'], 'Kwame'));

$inv = req($devBase . '/invoice.php?no=' . urlencode($orderNo), null, smoke_jar('anon'));
okk('invoice stays protected too', $inv['code'] === 404, 'code=' . $inv['code']);

$ord = req($devBase . '/order.php?no=' . urlencode($orderNo), null, smoke_jar('anon'));
okk('order page redirects guests to sign in', $ord['code'] === 302, 'code=' . $ord['code']);

/* -------------------------------------------- F: production refuses demo */

group('production refuses un-paid "paid" orders (issue #3)');

$page = req($prodBase . '/product.php?slug=' . urlencode($slug), null, smoke_jar('p'));
$tok  = csrf_of($page['body']);
$r    = req($prodBase . '/actions.php', ['action' => 'add_to_cart', 'product_id' => $productId, 'qty' => 1, 'csrf' => $tok], smoke_jar('p'));
okk('prod: item added to the bag', $r['code'] === 302, 'code=' . $r['code']);

$co  = req($prodBase . '/checkout.php', null, smoke_jar('p'));
$tok = csrf_of($co['body']);
$ordersBefore = $int($pdo, 'SELECT COUNT(*) FROM orders');

$r = req($prodBase . '/checkout.php', [
    'csrf' => $tok, 'name' => 'Prod Test', 'email' => 'prod@test.local',
    'phone' => '0240000001', 'region' => 'Greater Accra Region', 'city' => 'Accra',
    'address' => '2 Test Street', 'shipping_method' => 'metro', 'payment_channel' => 'paystack',
], smoke_jar('p'));
okk('prod: paystack-without-keys is bounced back', $r['code'] === 302 && !str_contains($r['location'], 'order_complete'), 'loc=' . $r['location']);

$after = req($prodBase . '/checkout.php', null, smoke_jar('p'));
okk('prod: the customer is told why', str_contains($after['body'], 'Online payment is unavailable right now'), dirty($after['body']));
okk('prod: no order was created', $int($pdo, 'SELECT COUNT(*) FROM orders') === $ordersBefore);

// Cash on delivery must still complete in production.
$tok = csrf_of($after['body']);
$r = req($prodBase . '/checkout.php', [
    'csrf' => $tok, 'name' => 'Prod Test', 'email' => 'prod@test.local',
    'phone' => '0240000001', 'region' => 'Greater Accra Region', 'city' => 'Accra',
    'address' => '2 Test Street', 'shipping_method' => 'metro', 'payment_channel' => 'cod',
], smoke_jar('p'));
okk('prod: cash on delivery still completes', $r['code'] === 302 && str_contains($r['location'], 'order_complete'), 'loc=' . $r['location']);
preg_match('#no=(VEL-[0-9]+-[0-9]+)#', $r['location'], $mm);
$codOrder = ($mm[1] ?? '') !== ''
    ? ($pdo->query('SELECT * FROM orders WHERE order_no = ' . $pdo->quote($mm[1]))->fetch() ?: null)
    : null;
okk(
    'prod: COD order is pending, not paid',
    $codOrder !== null && $codOrder['payment_status'] === 'pending' && $codOrder['status'] === 'pending',
    $codOrder ? $codOrder['payment_status'] . '/' . $codOrder['status'] : '-'
);

/* ---------------------------------------------------------- G: stock guard */

group('checkout refuses more than the shelf holds (issue #7)');

$page = req($devBase . '/product.php?slug=' . urlencode($slug), null, smoke_jar('over'));
$tok  = csrf_of($page['body']);
req($devBase . '/actions.php', ['action' => 'add_to_cart', 'product_id' => $productId, 'qty' => 2, 'csrf' => $tok], smoke_jar('over'));
// Take the stock out from under the bag, exactly like a faster shopper would.
$pdo->exec("UPDATE products SET stock = 1 WHERE id = $productId");
$co  = req($devBase . '/checkout.php', null, smoke_jar('over'));
$tok = csrf_of($co['body']);
$r = req($devBase . '/checkout.php', [
    'csrf' => $tok, 'name' => 'Race Test', 'email' => 'race@test.local',
    'phone' => '0240000002', 'region' => 'Greater Accra Region', 'city' => 'Takoradi',
    'address' => '3 Test Street', 'shipping_method' => 'metro', 'payment_channel' => 'cod',
], smoke_jar('over'));
okk('oversell is refused', $r['code'] === 302 && !str_contains($r['location'], 'order_complete'), 'loc=' . $r['location']);
$back = req($devBase . '/cart.php', null, smoke_jar('over'));
okk('customer is told what is left', str_contains($back['body'], 'Only 1 left of'), dirty($back['body']));
okk('no stock was taken', $int($pdo, "SELECT stock FROM products WHERE id = $productId") === 1);
okk('no order was written', $int($pdo, "SELECT COUNT(*) FROM orders WHERE customer_name = 'Race Test'") === 0);

/* -------------------------------------------------- G: telegram webhook --- */

group('telegram webhook: secret gate, whitelist, no side effects');

$whUrl   = $devBase . '/tg_webhook.php';
$update  = static fn (int $chat, array $extra): string => json_encode([
    'message' => $extra + ['message_id' => random_int(100000, 999999), 'chat' => ['id' => $chat]],
]);
$post    = static fn (string $body, string $secret): array => req($whUrl, null, null, [
    'raw'     => $body,
    'headers' => array_values(array_filter([
        'Content-Type: application/json',
        $secret !== '' ? 'X-Telegram-Bot-Api-Secret-Token: ' . $secret : '',
    ])),
]);
// A missing table simply means nothing has ever been staged.
$stagedBatches = static fn (PDO $p): int => (static function () use ($p): int {
    try {
        return (int) $p->query('SELECT COUNT(*) FROM intake_batches')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
})();
$stagedItems = static fn (PDO $p): int => (static function () use ($p): int {
    try {
        return (int) $p->query('SELECT COUNT(*) FROM intake_items')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
})();

$foreignUpdate = $update($whOther, ['text' => '/start']);

$r = $post($foreignUpdate, '');
okk('webhook without the secret header is refused', $r['code'] === 401, 'code=' . $r['code']);

$r = $post($foreignUpdate, 'not-the-secret');
okk('webhook with a wrong secret is refused', $r['code'] === 401, 'code=' . $r['code']);

$r = $post('{this is not json', $whSecret);
okk('garbage body is rejected', $r['code'] === 400, 'code=' . $r['code']);

$r = $post($foreignUpdate, $whSecret);
okk('foreign chat is acknowledged but ignored', $r['code'] === 200, 'code=' . $r['code'] . ' ' . substr(strip_tags($r['body']), 0, 80));

$r = $post($update($whChat, ['text' => '/status']), $whSecret);
okk('whitelisted command is served over 200', $r['code'] === 200, 'code=' . $r['code']);

okk('no batch was staged by any rejected request', $stagedBatches($pdo) === 0, 'batches=' . $stagedBatches($pdo));
okk('no photo row was written either', $stagedItems($pdo) === 0, 'items=' . $stagedItems($pdo));

/* ---------------------------------------------------------------- summary */

smoke_summary('HTTP smoke');
