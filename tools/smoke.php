<?php
/**
 * Command-level smoke suite for the security and stock fixes.
 *
 *   php tools/smoke.php            run, then drop the throwaway database
 *   php tools/smoke.php --keep     keep <DB_NAME>_smoke for inspection
 *
 * 45 checks: sign-in throttle, FOR UPDATE stock reservation, cart clamping,
 * the abandoned-order sweeper, the Paystack verification guard and the
 * Telegram intake pipeline (staging, matching, xlsx, discard - fully offline).
 * Everything runs against a throwaway database provisioned by
 * tools/smoke_lib.php, so the application's own data is never touched.
 *
 * For the 44 checks that go through HTTP (login, checkout, IDOR, production
 * demo-order refusal, webhook gating), run tools/smoke_http.php.
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

/* ------------------------------------------------------ G: telegram intake */

group('telegram intake: staging, matching, xlsx, discard (fully offline)');

require_once smoke_root() . '/includes/intake_service.php';
require_once smoke_root() . '/includes/intake_matcher.php';
require_once smoke_root() . '/includes/intake_xlsx.php';

// Idempotency for --keep runs: drop anything an earlier attempt staged.
intake_tables();
db_exec('DELETE FROM intake_items');
db_exec('DELETE FROM intake_batches');

$tgChat = 111222333;
$tgCfg  = ['token' => '', 'secret' => 'smoke', 'chats' => [$tgChat]];

okk('intake tables ensured', (int) db_val('SELECT COUNT(*) FROM intake_batches') === 0);

// A stranger must get silence and change nothing.
$reply = intake_dispatch($tgCfg, ['message' => ['message_id' => 1, 'chat' => ['id' => 999], 'text' => '/start']]);
okk('non-whitelisted chat is a silent no-op', $reply === '' && (int) db_val('SELECT COUNT(*) FROM intake_batches') === 0);

// The spreadsheet.
$csv = "sku,name,department,category,brand,price,stock\n"
     . "SMK-INT-0001,Aura Bodysuit,lingerie,Bodysuits,Velora,89.00,5\n"
     . "SMK-INT-0002,Yamaha Drumsticks,instruments,Drumsticks,Yamaha,45.00,7\n"
     . "SMK-INT-0003,Silk Chemise,lingerie,Chemises,Velora,70.00,3\n";
$reply = intake_dispatch($tgCfg, [
    'message' => [
        'message_id' => 2,
        'chat'       => ['id' => $tgChat],
        'document'   => ['file_id' => 'local-sheet', 'file_name' => 'batch.csv', 'file_size' => strlen($csv)],
    ],
], ['local-sheet' => $csv]);
$batch    = intake_active_batch($tgChat);
$batchId  = (int) ($batch['id'] ?? 0);
okk('sheet staged and answered with the row count', str_contains($reply, '3 product rows') && $batch !== null, $reply);
okk('batch remembers the sheet', $batch !== null && (int) $batch['row_count'] === 3 && (string) $batch['sheet_name'] === 'batch.csv', json_encode($batch));

// Photos: captioned singles, one album (caption on its first member), a SKU.
// No image ships in the repo, so build a valid 1x1 PNG by hand (no GD needed):
// intake_save_image() still validates it with finfo + getimagesizefromstring.
$makePng = static function (): string {
    $chunk = static fn (string $type, string $data): string =>
        pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    return "\x89PNG\r\n\x1a\n"
        . $chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0))
        . $chunk('IDAT', gzcompress("\x00\xFF\x00\x00"))
        . $chunk('IEND', '');
};
$photoBytes = $makePng();

$sendPhoto = static function (array $cfg, int $chat, int $msgId, string $caption, string $album, string $bytes): string {
    $msg = [
        'message_id' => $msgId,
        'chat'       => ['id' => $chat],
        'photo'      => [['file_id' => 'local-' . $msgId, 'width' => 100, 'height' => 100]],
    ];
    if ($caption !== '') {
        $msg['caption'] = $caption;
    }
    if ($album !== '') {
        $msg['media_group_id'] = $album;
    }
    return intake_dispatch($cfg, ['message' => $msg], ['local-' . $msgId => $bytes]);
};

$replies = [
    $sendPhoto($tgCfg, $tgChat, 10, 'Aura Bodysuit', '', $photoBytes),
    $sendPhoto($tgCfg, $tgChat, 11, 'Yamaha Drumsticks', 'ALB-1', $photoBytes),
    $sendPhoto($tgCfg, $tgChat, 12, '', 'ALB-1', $photoBytes),
    $sendPhoto($tgCfg, $tgChat, 13, 'SMK-INT-0003', '', $photoBytes),
];
$photoErrors = array_values(array_filter($replies));
okk('photo uploads are accepted silently', $photoErrors === [], implode(' | ', $photoErrors));
okk('four photos staged into the batch', intake_photo_count($batchId) === 4, 'n=' . intake_photo_count($batchId));

// Telegram retries the same update: must not double-store.
$sendPhoto($tgCfg, $tgChat, 12, '', 'ALB-1', $photoBytes);
okk('webhook retry (same message_id) is deduplicated', intake_photo_count($batchId) === 4, 'n=' . intake_photo_count($batchId));

// Matching: caption, album grouping, SKU - with order fallback OFF.
$items   = intake_items($batchId);
$parsed  = bulk_parse_csv_string($csv);
$preview = bulk_preview_validation($parsed['rows']);
$match   = intake_match($preview['results'], $items, ['order_fallback' => false]);

okk('row 1 matched by caption', ($match['assignments'][0]['method'] ?? '') === 'caption', json_encode($match['assignments'][0] ?? null));
okk('album keeps both photos on row 2', count($match['assignments'][1]['paths'] ?? []) === 2 && ($match['assignments'][1]['method'] ?? '') === 'caption', json_encode($match['assignments'][1] ?? null));
okk('row 3 matched by SKU', ($match['assignments'][2]['method'] ?? '') === 'sku', json_encode($match['assignments'][2] ?? null));
okk('no leftovers with order fallback off', $match['stats']['unmatched'] === 0 && $match['stats']['leftovers'] === 0, json_encode($match['stats']));

$pathsOk = count($items) === 4;
foreach ($items as $it) {
    $rel = (string) $it['file_path'];
    $pathsOk = $pathsOk && str_starts_with($rel, 'storage/uploads/') && is_file(smoke_root() . '/' . $rel);
}
okk('staged photos land in storage/uploads and exist on disk', $pathsOk, implode(',', array_column($items, 'file_path')));

// Order fallback on/off, and an ambiguous caption left for the human.
$flat = [
    [['data' => ['sku' => '', 'name' => 'Product A']], ['data' => ['sku' => '', 'name' => 'Product B']]],
    [['id' => 1, 'file_path' => 'storage/uploads/a.jpg', 'caption' => ''],
     ['id' => 2, 'file_path' => 'storage/uploads/b.jpg', 'caption' => '']],
];
$withOrder    = intake_match($flat[0], $flat[1], ['order_fallback' => true]);
$withoutOrder = intake_match($flat[0], $flat[1], ['order_fallback' => false]);
okk('order fallback pairs rows to photos in send order',
    ($withOrder['assignments'][0]['paths'][0] ?? '') === 'storage/uploads/a.jpg'
    && ($withOrder['assignments'][1]['paths'][0] ?? '') === 'storage/uploads/b.jpg'
    && ($withOrder['assignments'][0]['method'] ?? '') === 'order'
);
okk('order fallback can be switched off', $withoutOrder['stats']['unmatched'] === 2, json_encode($withoutOrder['stats']));

$tie = intake_match(
    [['data' => ['sku' => '', 'name' => 'Black Lace Bra']], ['data' => ['sku' => '', 'name' => 'Black Silk Slip']]],
    [['id' => 9, 'file_path' => 'storage/uploads/x.jpg', 'caption' => 'black']],
    ['order_fallback' => false]
);
okk('ambiguous caption is left for the human, not guessed', $tie['stats']['unmatched'] === 2 && $tie['stats']['leftovers'] === 1, json_encode($tie['stats']));

// Phone flow: a whole saved batch arrives as one captionless album.
$split = intake_group_shots([
    ['file_path' => 'storage/uploads/a.jpg', 'caption' => '', 'media_group' => 'ALB-X', 'id' => 1],
    ['file_path' => 'storage/uploads/b.jpg', 'caption' => '', 'media_group' => 'ALB-X', 'id' => 2],
    ['file_path' => 'storage/uploads/c.jpg', 'caption' => 'Yamaha Drumsticks', 'media_group' => 'ALB-Y', 'id' => 3],
    ['file_path' => 'storage/uploads/d.jpg', 'caption' => '', 'media_group' => 'ALB-Y', 'id' => 4],
    ['file_path' => 'storage/uploads/e.jpg', 'caption' => '', 'media_group' => '', 'id' => 5],
]);
okk('captionless album splits into single shots, in order',
    count($split) === 4
    && ($split[0]['paths'] ?? []) === ['storage/uploads/a.jpg']
    && ($split[1]['paths'] ?? []) === ['storage/uploads/b.jpg']
    && $split[0]['caption'] === '' && $split[1]['caption'] === '',
    json_encode($split));
okk('captioned album still means one product with a gallery',
    ($split[2]['paths'] ?? []) === ['storage/uploads/c.jpg', 'storage/uploads/d.jpg']
    && $split[2]['caption'] === 'Yamaha Drumsticks'
    && ($split[3]['paths'] ?? []) === ['storage/uploads/e.jpg'],
    json_encode($split[2] ?? null));

$phoneFlow = intake_match(
    [['data' => ['sku' => '', 'name' => 'Red Bass Guitar']], ['data' => ['sku' => '', 'name' => 'Horns Amp']], ['data' => ['sku' => '', 'name' => 'Mic Stand']]],
    [
        ['id' => 20, 'file_path' => 'storage/uploads/p1.jpg', 'caption' => '', 'media_group' => 'BULK'],
        ['id' => 21, 'file_path' => 'storage/uploads/p2.jpg', 'caption' => '', 'media_group' => 'BULK'],
        ['id' => 22, 'file_path' => 'storage/uploads/p3.jpg', 'caption' => '', 'media_group' => 'BULK'],
    ],
    ['order_fallback' => true]
);
okk('captionless batch order-matches one photo per row',
    ($phoneFlow['assignments'][0]['paths'] ?? []) === ['storage/uploads/p1.jpg']
    && ($phoneFlow['assignments'][1]['paths'] ?? []) === ['storage/uploads/p2.jpg']
    && ($phoneFlow['assignments'][2]['paths'] ?? []) === ['storage/uploads/p3.jpg']
    && $phoneFlow['stats']['leftovers'] === 0,
    json_encode($phoneFlow['assignments']));

// The .xlsx reader (shared strings + inline strings + numbers).
$xlsx = tempnam(sys_get_temp_dir(), 'smkxlsx') . '.xlsx';
$zip  = new ZipArchive();
$zip->open($xlsx, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="3" uniqueCount="3"><si><t>sku</t></si><si><t>name</t></si><si><t>price</t></si></sst>');
$zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
    . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row>'
    . '<row r="2"><c r="A2" t="inlineStr"><is><t>SMK-INT-0009</t></is></c><c r="B2" t="inlineStr"><is><t>Lace Garter Belt</t></is></c><c r="C2"><v>12.5</v></c></row>'
    . '</sheetData></worksheet>');
$zip->close();

$xlsxError = '';
try {
    $fromXlsx = intake_xlsx_to_csv($xlsx);
} catch (Throwable $e) {
    $fromXlsx = '';
    $xlsxError = $e->getMessage();
}
@unlink($xlsx);
okk('xlsx converted without throwing', $xlsxError === '' && $fromXlsx !== '', $xlsxError . ' ' . $fromXlsx);
if ($xlsxError === '') {
    $parsedX = bulk_parse_csv_string($fromXlsx);
    okk('xlsx rows land in the bulk parser', count($parsedX['rows'] ?? []) === 1
        && (string) ($parsedX['rows'][0]['sku'] ?? '') === 'SMK-INT-0009'
        && str_contains((string) ($parsedX['rows'][0]['price'] ?? ''), '12.5'),
        json_encode($parsedX['rows'][0] ?? ($parsedX['error'] ?? null)));
}

// Discard: the staged files must leave disk too.
$staged  = array_column(intake_items($batchId), 'file_path');
$removed = intake_discard($batchId);
$left    = array_filter($staged, static fn($p) => is_file(smoke_root() . '/' . $p));
okk('discard deletes every staged photo', $removed === count($staged) && $left === [], 'removed=' . $removed . ' left=' . count($left));
okk('discard marks the batch as discarded', (string) db_val('SELECT status FROM intake_batches WHERE id = ?', [$batchId]) === 'discarded');

/* ---------------------------------------------------------------- summary */

@unlink($stamp); // leave no stamp behind for the running app

smoke_summary('command-level smoke');
