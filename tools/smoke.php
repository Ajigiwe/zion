<?php
/**
 * Command-level smoke suite for the security and stock fixes.
 *
 *   php tools/smoke.php            run, then drop the throwaway database
 *   php tools/smoke.php --keep     keep <DB_NAME>_smoke for inspection
 *
 * 26 checks: sign-in throttle, FOR UPDATE stock reservation, cart clamping,
 * the abandoned-order sweeper and the Paystack verification guard. Everything
 * runs against a throwaway database provisioned by tools/smoke_lib.php, so the
 * application's own data is never touched.
 *
 * For the 37 checks that go through HTTP (login, checkout, IDOR, production
 * demo-order refusal), run tools/smoke_http.php.
 */
declare(strict_types=1);

require __DIR__ . '/smoke_lib.php';

smoke_boot();

// CLI has no client address; pretend to be a browser so the shared-IP scope of
// the throttle is exercised the way it is on a live sign-in.
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';

$stamp = smoke_root() . '/storage/logs/last-stock-release';

// Idempotency: a --keep database may still hold rows from the previous run.
db_exec('DELETE FROM login_attempts');

/* -------------------------------------------------------------- A: throttle */

group('login throttle (issue #5)');

$identity = 'nobody@smoke.test';
login_throttle_clear('smoke', $identity);

okk('clean slate allows sign-in', login_throttle_block('smoke', $identity) === null);

for ($i = 1; $i <= LOGIN_MAX_ATTEMPTS; $i++) {
    login_throttle_fail('smoke', $identity);
}
$blocked = login_throttle_block('smoke', $identity);
okk(
    'locked after ' . LOGIN_MAX_ATTEMPTS . ' failures',
    $blocked !== null && str_contains($blocked, 'Too many sign-in attempts'),
    'got: ' . var_export($blocked, true)
);
okk(
    'counter row written for both identity and client IP',
    (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE scope = ? AND ident = ?', ['smoke', $identity]) === 1
    && (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE scope = ? AND ident = ?', ['shared', 'ip:203.0.113.9']) === 1
);

login_throttle_clear('smoke', $identity);
okk('clear() lets the customer try again', login_throttle_block('smoke', $identity) === null);
okk(
    'clear() also resets the shared client-IP counter',
    (int) db_val('SELECT COUNT(*) FROM login_attempts') === 0
);

/* ------------------------------------------------------ B: stock reservation */

group('stock reservation (issue #7)');

$product = db_one('SELECT id, name, stock FROM products ORDER BY stock DESC, id LIMIT 1');
okk('found a product with stock', $product !== null, 'no products in seed?');
$pid    = (int) ($product['id'] ?? 0);
$before = (int) ($product['stock'] ?? 0);
$label  = (string) ($product['name'] ?? '');

// Reserve inside a transaction that is then rolled back: proves the decrement
// is applied under the transaction and reverted cleanly.
$midTx = null;
try {
    db_tx(static function (PDO $pdo) use ($pid, $label, &$midTx): void {
        order_reserve_stock($pdo, [['product_id' => $pid, 'qty' => 1, 'label' => $label]]);
        $midTx = (int) db_val('SELECT stock FROM products WHERE id = ?', [$pid]);
        throw new RuntimeException('force rollback');
    });
} catch (RuntimeException $e) {
    // expected
}
$stockAfterRollback = (int) db_val('SELECT stock FROM products WHERE id = ?', [$pid]);
okk('reserve decrements inside the tx', $midTx === $before - 1, 'during=' . var_export($midTx, true));
okk('rollback restores the stock', $stockAfterRollback === $before, "before=$before after=$stockAfterRollback");

// Committing path.
db_tx(static function (PDO $pdo) use ($pid, $label): void {
    order_reserve_stock($pdo, [['product_id' => $pid, 'qty' => 2, 'label' => $label]]);
});
okk(
    'committed reserve removes 2 units',
    (int) db_val('SELECT stock FROM products WHERE id = ?', [$pid]) === $before - 2
);

// Over-selling must fail loudly rather than clamp to zero (the old GREATEST
// behaviour silently pretended the order had been reserved).
$tooMany = $before; // ask for the original quantity while only $before-2 remain
$thrown  = null;
try {
    db_tx(static function (PDO $pdo) use ($pid, $label, $tooMany): void {
        order_reserve_stock($pdo, [['product_id' => $pid, 'qty' => $tooMany, 'label' => $label]]);
    });
} catch (DomainException $e) {
    $thrown = $e->getMessage();
}
okk('short stock throws DomainException', $thrown !== null, 'no exception raised');
okk(
    'short stock message names what is left',
    $thrown !== null && (str_contains($thrown, 'Only') || str_contains($thrown, 'out of stock')),
    (string) $thrown
);
okk('failed reserve left stock untouched', (int) db_val('SELECT stock FROM products WHERE id = ?', [$pid]) === $before - 2);

$thrown = null;
try {
    db_tx(static function (PDO $pdo): void {
        order_reserve_stock($pdo, [['product_id' => 999999, 'qty' => 1, 'label' => 'Ghost item']]);
    });
} catch (DomainException $e) {
    $thrown = $e->getMessage();
}
okk('missing product throws "no longer available"', $thrown !== null && str_contains($thrown, 'no longer available'), (string) $thrown);

/* ----------------------------------------------------------- C: cart clamp */

group('cart quantity clamp (issue #7)');

$_SESSION['cart_token'] = 'smoketoken' . bin2hex(random_bytes(4));
db_exec(
    'INSERT INTO cart_items (session_token, user_id, product_id, variant_size, variant_color, qty, unit_price)
     VALUES (?, NULL, ?, NULL, NULL, ?, ?)',
    [$_SESSION['cart_token'], $pid, 50, (string) db_val('SELECT price FROM products WHERE id = ?', [$pid])]
);
$cartId = (int) db_val('SELECT id FROM cart_items WHERE session_token = ?', [$_SESSION['cart_token']]);

db_exec('UPDATE products SET stock = ? WHERE id = ?', [3, $pid]);
cart_update($cartId, 99);
okk('cart_update clamps to stock (99 -> 3)', (int) db_val('SELECT qty FROM cart_items WHERE id = ?', [$cartId]) === 3);

cart_update($cartId, 1);
okk('cart_update still lowers normally', (int) db_val('SELECT qty FROM cart_items WHERE id = ?', [$cartId]) === 1);

cart_update($cartId, 0);
okk('cart_update qty 0 removes the line', db_one('SELECT id FROM cart_items WHERE id = ?', [$cartId]) === null);

/* ------------------------------------------------ D: abandoned order sweep */

group('abandoned Paystack orders (issue #7)');

db_exec('UPDATE products SET stock = ? WHERE id = ?', [10, $pid]);
$stockBefore = 10;

db_exec(
    "INSERT INTO orders (order_no, user_id, customer_name, email, phone, region, city, address,
                         shipping_method, shipping_label, subtotal, total,
                         payment_channel, payment_status, status, created_at)
     VALUES ('VEL-SMOKE-0001', NULL, 'Smoke Tester', NULL, '0000000000', 'Greater Accra Region', 'Tarkwa', '1 Test Street',
             'metro', 'Metro', 100.00, 100.00, 'paystack', 'pending', 'pending', NOW() - INTERVAL 70 MINUTE)"
);
$orderId = (int) db_val("SELECT id FROM orders WHERE order_no = 'VEL-SMOKE-0001'");
db_exec(
    'INSERT INTO order_items (order_id, product_id, product_name, unit_price, qty, line_total)
     VALUES (?, ?, ?, 50.00, 2, 100.00)',
    [$orderId, $pid, $label]
);

@unlink($stamp);
$released = release_abandoned_paystack_orders();

okk('sweeper released the stale order', $released >= 1, "released=$released");
okk('order marked cancelled', (string) db_val('SELECT status FROM orders WHERE id = ?', [$orderId]) === 'cancelled');
okk('stock returned to the shelf', (int) db_val('SELECT stock FROM products WHERE id = ?', [$pid]) === $stockBefore + 2);
okk('marker event recorded', order_stock_released($orderId));
okk(
    'release note explains why',
    str_contains((string) db_val("SELECT note FROM order_events WHERE order_id = ? AND status = 'stock'", [$orderId]), 'Payment not received within')
);

// Run again immediately: the stamp file plus the marker event must both stop a
// second release of the same inventory.
@unlink($stamp);
$releasedAgain = release_abandoned_paystack_orders();
okk(
    'second sweep does not return stock twice',
    (int) db_val('SELECT stock FROM products WHERE id = ?', [$pid]) === $stockBefore + 2,
    "released=$releasedAgain"
);

/* ------------------------------------------------------- E: paystack helper */

group('paystack verification helper (issues #2/#3)');

if (is_paystack_configured()) {
    group('gateway key detected - no synthetic verification call is made (it would hit the live API)');
} else {
    okk('is_paystack_configured() is false without a key', true);
    $verify = paystack_verify_payment('ZION-TEST-123', 100.00);
    okk(
        'verification fails closed without keys',
        $verify['ok'] === false && str_contains($verify['message'] ?? '', 'secret key'),
        json_encode($verify)
    );
}

okk(
    'demo mode tracks APP_ENV (never allowed in production)',
    paystack_demo_allowed() === (APP_ENV !== 'production'),
    'APP_ENV=' . APP_ENV
);
okk(
    'order rows expose a numeric total for the amount check',
    (float) db_val('SELECT total FROM orders WHERE id = ?', [$orderId]) > 0.0
);

/* ---------------------------------------------------------------- summary */

@unlink($stamp); // leave no stamp behind for the running app

smoke_summary('command-level smoke');
