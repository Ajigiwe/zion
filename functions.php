<?php
/** Shared application helpers: output, auth, flash, CSRF, cart, orders. */

declare(strict_types=1);

/* ------------------------------------------------------------ output */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Resolve an image reference to a usable src.
 * Local library paths ("storage/uploads/x.jpg") get the app base path;
 * remote URLs and data URIs pass through untouched.
 */
function img_url(?string $ref): string
{
    $ref = trim((string) $ref);
    if ($ref === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $ref) === 1 || str_starts_with($ref, 'data:')) {
        return $ref;
    }
    return url($ref);
}

/** Absolute URL of an image reference (og:image, structured data). */
function absolute_image_url(?string $ref): string
{
    $ref = trim((string) $ref);
    if ($ref === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $ref) === 1) {
        return $ref;
    }
    return APP_URL . url($ref);
}

/** "GH₵ 4,950.00" */
function money(float|string $v, bool $alwaysCents = false): string
{
    $v = (float) $v;
    $cents = $alwaysCents || abs($v - round($v)) > 0.0001;
    return CURRENCY . ' ' . number_format($v, $cents ? 2 : 0, '.', ',');
}

/** "GH₵ 280" (whole cedis, no decimals) */
function price(float|string $v): string
{
    return money($v, false);
}

function plural(int $n, string $one, string $many): string
{
    return $n . ' ' . ($n === 1 ? $one : $many);
}

/** Human label for an orders.payment_channel value. */
function payment_channel_label(string $ch): string
{
    return match ($ch) {
        'paystack' => 'Paystack',
        'cod'      => 'Pay on Delivery',
        'momo'     => 'MTN MoMo',
        'telecel'  => 'Telecel Cash',
        'at'       => 'AT Money',
        'debit'    => 'Card',
        default    => strtoupper($ch),
    };
}

/** Local path (+ query) of the page being rendered, for form "return" fields. */
function here_url(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    // dirname() uses the platform separator, so "\" on Windows - normalise it.
    $dir    = str_replace('\\', '/', dirname($script));
    $path   = ($dir === '/' || $dir === '.' || $dir === '' ? '' : rtrim($dir, '/')) . '/' . basename($script);
    $query  = (string) ($_SERVER['QUERY_STRING'] ?? '');
    return $query !== '' ? $path . '?' . $query : $path;
}

/* ------------------------------------------------------------- flash */

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_all(): array
{
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

/* --------------------------------------------------------------- CSRF */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function full_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url($path);
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        $isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || (!empty($_POST['ajax']) && (string) $_POST['ajax'] === '1');
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'Your security session has expired. Please refresh the page and try again.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        exit('Invalid or expired form token. Please go back and try again.');
    }
}

/* --------------------------------------------------------------- auth */

function current_user(): ?array
{
    if (array_key_exists('_current_user', $GLOBALS)) {
        return $GLOBALS['_current_user'];
    }
    $id = (int) ($_SESSION['user_id'] ?? 0);
    $GLOBALS['_current_user'] = $id > 0
        ? db_one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$id])
        : null;
    return $GLOBALS['_current_user'];
}

/** Drop the memoised user so the next current_user() call re-reads the DB. */
function user_forget(): void
{
    unset($GLOBALS['_current_user']);
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    // pull any cart rows this account left behind in a previous session
    db_exec(
        'UPDATE cart_items SET user_id = ? WHERE session_token = ? AND user_id IS NULL',
        [(int) $user['id'], cart_token()]
    );
    db_exec(
        'UPDATE cart_items SET session_token = ? WHERE user_id = ? AND session_token <> ?',
        [cart_token(), (int) $user['id'], cart_token()]
    );
    user_forget();
}

function logout_user(): void
{
    session_regenerate_id(true);
    unset($_SESSION['user_id']);
    user_forget();
}

/** Redirect to the sign-in screen, remembering where the visitor came from. */
function require_login(string $returnTo = ''): void
{
    if (is_logged_in()) {
        return;
    }
    if ($returnTo === '') {
        $returnTo = ($_SERVER['REQUEST_URI'] ?? '/');
    }
    flash_set('info', 'Please sign in to continue.');
    header('Location: ' . url('login.php') . '?next=' . urlencode($returnTo));
    exit;
}

function require_admin(): void
{
    if (is_admin()) {
        return;
    }
    header('Location: ' . url('admin/login.php'));
    exit;
}

/* ------------------------------------------------------- login throttle */

const LOGIN_MAX_ATTEMPTS = 5;      // failures per identity per window
const LOGIN_LOCK_SECONDS = 900;    // 15 minutes

/**
 * Create the attempt counter table on first use.
 *
 * The app ships no migration runner, so the table is ensured lazily - that way
 * an existing install picks up the protection without an import step. Failures
 * are logged and the throttle then fails *open* rather than blocking sign-in.
 */
function login_throttle_table(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;
    try {
        db_exec(
            'CREATE TABLE IF NOT EXISTS `login_attempts` (
               `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
               `scope`          VARCHAR(16)  NOT NULL,
               `ident`          VARCHAR(160) NOT NULL,
               `attempts`       INT UNSIGNED NOT NULL DEFAULT 1,
               `last_attempt_at` DATETIME     NOT NULL,
               PRIMARY KEY (`id`),
               UNIQUE KEY `uq_login_attempts_scope_ident` (`scope`, `ident`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable $e) {
        error_log('login_throttle: could not ensure login_attempts - ' . $e->getMessage());
    }
}

/**
 * [scope, ident] pairs to count for a sign-in attempt: the submitted identity
 * under the caller's scope, plus the client IP under a shared scope so a locked
 * address cannot simply switch to the other login form.
 *
 * @return list<array{0: string, 1: string}>
 */
function login_throttle_idents(string $scope, string $identity): array
{
    $out  = [];
    $id   = mb_strtolower(trim($identity));
    $ip   = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if ($id !== '') {
        $out[] = [$scope, mb_substr($id, 0, 160)];
    }
    if ($ip !== '') {
        $out[] = ['shared', mb_substr('ip:' . $ip, 0, 160)];
    }
    return $out;
}

/** Why this sign-in must be refused right now, or null when it may proceed. */
function login_throttle_block(string $scope, string $identity): ?string
{
    // Every timestamp comparison stays in SQL: PHP and MySQL often disagree
    // about the display timezone (they did on XAMPP - 9 hours apart), and a
    // PHP-side "has the window expired?" would then disable the lockout.
    foreach (login_throttle_idents($scope, $identity) as [$s, $ident]) {
        try {
            $row = db_one(
                'SELECT attempts, TIMESTAMPDIFF(SECOND, last_attempt_at, NOW()) AS elapsed
                   FROM login_attempts
                  WHERE scope = ? AND ident = ?
                    AND last_attempt_at > NOW() - INTERVAL ' . LOGIN_LOCK_SECONDS . ' SECOND',
                [$s, $ident]
            );
        } catch (Throwable $e) {
            error_log('login_throttle: ' . $e->getMessage());
            return null;
        }
        if ($row === null || (int) $row['attempts'] < LOGIN_MAX_ATTEMPTS) {
            continue;
        }
        $wait   = LOGIN_LOCK_SECONDS - (int) $row['elapsed'];   // seconds still to serve
        $mins   = max(1, (int) ceil($wait / 60));
        return 'Too many sign-in attempts. Please wait about '
            . $mins . ' minute' . ($mins === 1 ? '' : 's') . ' and try again.';
    }
    return null;
}

/** Record one failed attempt against every identity for this sign-in. */
function login_throttle_fail(string $scope, string $identity): void
{
    login_throttle_table();
    foreach (login_throttle_idents($scope, $identity) as [$s, $ident]) {
        try {
            db_exec(
                'INSERT INTO login_attempts (scope, ident, attempts, last_attempt_at)
                 VALUES (?, ?, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                   attempts       = IF(last_attempt_at < NOW() - INTERVAL ' . LOGIN_LOCK_SECONDS . ' SECOND, 1, attempts + 1),
                   last_attempt_at = NOW()',
                [$s, $ident]
            );
        } catch (Throwable $e) {
            error_log('login_throttle: ' . $e->getMessage());
        }
    }
}

/** Forget the failures for this identity once the password was right. */
function login_throttle_clear(string $scope, string $identity): void
{
    login_throttle_table();
    $pairs = login_throttle_idents($scope, $identity);
    if ($pairs === []) {
        return;
    }
    try {
        $placeholders = [];
        $params = [];
        foreach ($pairs as [$s, $ident]) {
            $placeholders[] = '(?, ?)';
            $params[] = $s;
            $params[] = $ident;
        }
        db_exec(
            'DELETE FROM login_attempts WHERE (scope, ident) IN (' . implode(', ', $placeholders) . ')',
            $params
        );
    } catch (Throwable $e) {
        error_log('login_throttle: ' . $e->getMessage());
    }
}

/* ----------------------------------------------------------- cart token */

function cart_token(): string
{
    if (empty($_SESSION['cart_token'])) {
        $_SESSION['cart_token'] = bin2hex(random_bytes(20));
    }
    return $_SESSION['cart_token'];
}

/** WHERE fragment + params identifying the current visitor's cart. */
function cart_scope(): array
{
    $u = current_user();
    if ($u !== null) {
        return ['cart_items.user_id = ?', [(int) $u['id']]];
    }
    return ['cart_items.session_token = ?', [cart_token()]];
}

function cart_rows(): array
{
    [$where, $params] = cart_scope();
    return db_all(
        "SELECT cart_items.*, products.name, products.slug, products.image_url,
                products.department, products.stock, products.sku, products.brand_label
         FROM cart_items
         JOIN products ON products.id = cart_items.product_id
         WHERE $where
         ORDER BY cart_items.id",
        $params
    );
}

function cart_count(): int
{
    [$where, $params] = cart_scope();
    return (int) db_val(
        "SELECT COALESCE(SUM(qty), 0) FROM cart_items WHERE $where",
        $params,
        0
    );
}

function cart_totals(): array
{
    $rows = cart_rows();
    $subtotal = 0.0;
    foreach ($rows as $r) {
        $subtotal += (float) $r['unit_price'] * (int) $r['qty'];
    }
    $freeThresh = (float) setting('free_shipping_threshold', (string) FREE_SHIPPING_THRESHOLD);
    $metroFee   = (float) setting('shipping_metro_fee', (string) SHIPPING_METRO);
    $regFee     = (float) setting('shipping_regional_fee', (string) SHIPPING_REGIONAL);
    $shipping   = $subtotal === 0.0 || $subtotal >= $freeThresh
        ? $metroFee
        : $regFee;
    return ['rows' => $rows, 'subtotal' => $subtotal, 'shipping' => $shipping, 'total' => $subtotal + $shipping];
}

function cart_add(int $productId, int $qty = 1, ?string $size = null, ?string $color = null): void
{
    $product = db_one('SELECT id, price, stock FROM products WHERE id = ? AND is_active = 1', [$productId]);
    if ($product === null) {
        flash_set('error', 'That product is no longer available.');
        return;
    }
    [$where, $params] = cart_scope();
    $existing = db_one(
        "SELECT id, qty FROM cart_items
         WHERE $where AND product_id = ? AND variant_size <=> ? AND variant_color <=> ?",
        array_merge($params, [$productId, $size, $color])
    );
    $newQty = min((int) $product['stock'], ((int) ($existing['qty'] ?? 0)) + max(1, $qty));
    if ($existing !== null) {
        db_exec('UPDATE cart_items SET qty = ? WHERE id = ?', [$newQty, (int) $existing['id']]);
    } else {
        db_exec(
            'INSERT INTO cart_items (session_token, user_id, product_id, variant_size, variant_color, qty, unit_price)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [cart_token(), current_user()['id'] ?? null, $productId, $size, $color, $newQty, $product['price']]
        );
    }
}

function cart_update(int $cartId, int $qty): void
{
    [$where, $params] = cart_scope();
    if ($qty <= 0) {
        db_exec("DELETE FROM cart_items WHERE id = ? AND $where", array_merge([$cartId], $params));
        return;
    }
    // Clamp to what is actually on the shelf (the row may have been adjusted
    // by another shopper since it was added), never past 99 a line.
    $stock = db_val(
        "SELECT p.stock FROM cart_items JOIN products p ON p.id = cart_items.product_id
          WHERE cart_items.id = ? AND $where",
        array_merge([$cartId], $params),
        99
    );
    $qty = min(99, $qty, max(0, (int) $stock));
    if ($qty <= 0) {
        db_exec("DELETE FROM cart_items WHERE id = ? AND $where", array_merge([$cartId], $params));
        return;
    }
    db_exec(
        "UPDATE cart_items SET qty = ? WHERE id = ? AND $where",
        array_merge([$qty, $cartId], $params)
    );
}

function cart_remove(int $cartId): void
{
    [$where, $params] = cart_scope();
    db_exec("DELETE FROM cart_items WHERE id = ? AND $where", array_merge([$cartId], $params));
}

function cart_clear(): void
{
    [$where, $params] = cart_scope();
    db_exec("DELETE FROM cart_items WHERE $where", $params);
}

/* ------------------------------------------------------------ wishlist */

function wishlist_ids(): array
{
    $u = current_user();
    if ($u === null) {
        return [];
    }
    $rows = db_all('SELECT product_id FROM wishlist WHERE user_id = ?', [(int) $u['id']]);
    return array_map('intval', array_column($rows, 'product_id'));
}

function in_wishlist(int $productId): bool
{
    return in_array($productId, wishlist_ids(), true);
}

function wishlist_toggle(int $productId): bool
{
    $u = current_user();
    if ($u === null) {
        return false;
    }
    $exists = db_one('SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?', [(int) $u['id'], $productId]);
    if ($exists !== null) {
        db_exec('DELETE FROM wishlist WHERE id = ?', [(int) $exists['id']]);
        return false;
    }
    db_exec('INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)', [(int) $u['id'], $productId]);
    return true;
}

/* -------------------------------------------------------------- promo */

function promo_lookup(string $code): ?array
{
    $code = trim($code);
    if ($code === '') {
        return null;
    }
    return db_one(
        'SELECT * FROM promo_codes
         WHERE code = ? AND is_active = 1
           AND (expires_at IS NULL OR expires_at > NOW())',
        [$code]
    );
}

function promo_discount(array $promo, float $subtotal): float
{
    if ($promo === []) {
        return 0.0;
    }
    $value = (float) $promo['value'];
    $d = $promo['type'] === 'percent' ? $subtotal * $value / 100 : min($value, $subtotal);
    return round($d, 2);
}

/* ------------------------------------------------------------- orders */

function next_order_no(): string
{
    $seq = (int) db_val('SELECT COUNT(*) + 1 FROM orders', [], 1);
    do {
        $no = sprintf('VEL-%d-%04d', (int) date('Y'), $seq + random_int(0, 40));
        $seq++;
    } while (db_one('SELECT id FROM orders WHERE order_no = ?', [$no]) !== null);
    return $no;
}

/* ------------------------------------------------------ stock reservation */

/** Unpaid Paystack orders give their stock back after this many minutes. */
const ABANDONED_ORDER_MINUTES = 60;

/**
 * Marker event meaning "this order's stock is back on the shelf".
 *
 * The `stock` event is what makes every release idempotent: an order's
 * inventory can be returned exactly once, no matter how many code paths
 * (admin cancel, abandoned-payment sweeper) get there.
 */
function order_stock_released(int $orderId): bool
{
    return db_one(
        "SELECT id FROM order_events WHERE order_id = ? AND status = 'stock' LIMIT 1",
        [$orderId]
    ) !== null;
}

/**
 * Take stock for the given items inside an open transaction.
 *
 * Product rows are locked for update and the decrement only applies when the
 * stock is really there, so two concurrent checkouts cannot both sell the last
 * unit and a short stock level is never silently masked.
 *
 * @param array<int, array{product_id: int, qty: int, label: string}> $items
 * @throws DomainException when an item is missing or short.
 */
function order_reserve_stock(PDO $pdo, array $items): void
{
    if ($items === []) {
        return;
    }
    // Stable lock order keeps two simultaneous checkouts from deadlocking.
    usort($items, static fn (array $a, array $b): int => $a['product_id'] <=> $b['product_id']);

    $read  = $pdo->prepare('SELECT name, stock FROM products WHERE id = ? FOR UPDATE');
    $write = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');

    foreach ($items as $item) {
        $pid = (int) $item['product_id'];
        $qty = max(1, (int) $item['qty']);
        $label = (string) ($item['label'] ?? 'An item in your bag');

        $read->execute([$pid]);
        $row = $read->fetch();
        if ($row === false) {
            throw new DomainException('"' . $label . '" is no longer available - please remove it from your bag.');
        }
        $available = (int) $row['stock'];
        if ($available < $qty) {
            throw new DomainException(
                $available > 0
                    ? 'Only ' . $available . ' left of "' . $row['name'] . '" - please reduce the quantity in your bag.'
                    : '"' . $row['name'] . '" is out of stock - please remove it from your bag.'
            );
        }

        $write->execute([$qty, $pid, $qty]);
        if ($write->rowCount() === 0) {
            // Somebody took the last unit between the read and the write.
            throw new DomainException('"' . $row['name'] . '" sold out while you were checking out.');
        }
    }
}

/**
 * Put an order's items back on the shelf and record the marker event.
 * Call inside a transaction; a no-op when the stock was already returned.
 *
 * @return bool true when stock was actually returned.
 */
function order_release_stock(PDO $pdo, int $orderId, string $reason = 'Stock returned to inventory'): bool
{
    if (order_stock_released($orderId)) {
        return false;
    }
    $items = $pdo->prepare('SELECT product_id, product_name, qty FROM order_items WHERE order_id = ?');
    $items->execute([$orderId]);
    $giveBack = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
    while ($row = $items->fetch()) {
        $giveBack->execute([(int) $row['qty'], (int) $row['product_id']]);
    }

    $ev = $pdo->prepare('INSERT INTO order_events (order_id, status, note) VALUES (?,?,?)');
    $ev->execute([$orderId, 'stock', $reason]);
    return true;
}

/**
 * Give stock back from Paystack orders nobody ever paid for.
 *
 * Stock is reserved when an order is created so the last unit cannot be sold
 * twice, but a customer who never finishes at the gateway would otherwise hold
 * it forever. Runs from page loads rather than a cron, so it is rate limited by
 * a stamp file and processes a bounded batch.
 *
 * @return int number of orders released.
 */
function release_abandoned_paystack_orders(): int
{
    $stampFile = __DIR__ . '/storage/logs/last-stock-release';
    $last = is_file($stampFile) ? (int) @file_get_contents($stampFile) : 0;
    if (time() - $last < 600) {
        return 0;
    }
    @mkdir(dirname($stampFile), 0775, true);
    @file_put_contents($stampFile, (string) time());   // stamp first: never spin on a failing sweep

    try {
        $stale = db_all(
            "SELECT id, order_no FROM orders
              WHERE payment_channel = 'paystack'
                AND payment_status = 'pending'
                AND status = 'pending'
                AND created_at < NOW() - INTERVAL " . ABANDONED_ORDER_MINUTES . " MINUTE
              ORDER BY id
              LIMIT 50"
        );
    } catch (Throwable $e) {
        error_log('abandoned orders: ' . $e->getMessage());
        return 0;
    }

    $released = 0;
    foreach ($stale as $staleOrder) {
        $orderId = (int) $staleOrder['id'];
        $note    = 'Payment not received within ' . ABANDONED_ORDER_MINUTES . ' minutes - stock returned to inventory';
        try {
            $did = db_tx(function (PDO $pdo) use ($orderId, $staleOrder, $note): bool {
                // Re-read under lock: payment may have landed since the select.
                $cur = db_one(
                    'SELECT payment_status, status FROM orders WHERE id = ? FOR UPDATE',
                    [$orderId]
                );
                if ($cur === null || $cur['payment_status'] !== 'pending' || $cur['status'] !== 'pending') {
                    return false;
                }
                if (!order_release_stock($pdo, $orderId, $note)) {
                    return false;
                }
                db_exec(
                    'UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?',
                    ['cancelled', $orderId]
                );
                error_log('abandoned order released: ' . $staleOrder['order_no']);
                return true;
            });
            if ($did) {
                $released++;
            }
        } catch (Throwable $e) {
            error_log('abandoned order ' . $staleOrder['order_no'] . ': ' . $e->getMessage());
        }
    }
    return $released;
}

/** Shipping method preset from the region chosen at checkout. */
function shipping_quote(string $region, string $method): array
{
    $metroRegions = ['Greater Accra Region'];
    if ($method === 'pickup') {
        $loc  = setting('shipping_pickup_title', setting('address_line'));
        $desc = setting('shipping_pickup_desc', 'Ready in 2 Hours');
        $fee  = (float) setting('shipping_pickup_fee', '0.00');
        return ['pickup', 'Self Pick (' . $loc . ' - ' . $desc . ')', $fee];
    }
    if (in_array($region, $metroRegions, true) && $method !== 'regional') {
        $title = setting('shipping_metro_title', 'Accra Express');
        $desc  = setting('shipping_metro_desc', 'Same-Day / 24 hrs');
        $fee   = (float) setting('shipping_metro_fee', (string) SHIPPING_METRO);
        return ['metro', $title . ' (' . $desc . ')', $fee];
    }
    $title = setting('shipping_regional_title', 'Regional Road');
    $desc  = setting('shipping_regional_desc', 'Kumasi / Takoradi');
    $fee   = (float) setting('shipping_regional_fee', (string) SHIPPING_REGIONAL);
    return ['regional', $title . ' (' . $desc . ')', $fee];
}

/* ------------------------------------------------------------- reviews */

/** Newest approved reviews for a product. */
function product_reviews(int $productId, int $limit = 50): array
{
    return db_all(
        'SELECT r.*, u.role
           FROM reviews r
           LEFT JOIN users u ON u.id = r.user_id
          WHERE r.product_id = ? AND r.is_approved = 1
          ORDER BY r.created_at DESC, r.id DESC
          LIMIT ' . max(1, $limit),
        [$productId]
    );
}

/** Live aggregate of the approved rows: average, count, 5..1 star split. */
function rating_summary(int $productId): array
{
    $rows = db_all(
        'SELECT rating, COUNT(*) AS n FROM reviews
          WHERE product_id = ? AND is_approved = 1 GROUP BY rating',
        [$productId]
    );
    $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $sum = 0;
    $count = 0;
    foreach ($rows as $r) {
        $n = (int) $r['n'];
        $dist[(int) $r['rating']] = $n;
        $sum += (int) $r['rating'] * $n;
        $count += $n;
    }
    return [
        'count' => $count,
        'avg'   => $count > 0 ? round($sum / $count, 1) : 0.0,
        'dist'  => $dist,
    ];
}

/** Mirror the approved reviews back onto products.rating / review_count. */
function refresh_product_rating(int $productId): void
{
    $s = rating_summary($productId);
    db_exec(
        'UPDATE products SET rating = ?, review_count = ? WHERE id = ?',
        [$s['avg'], $s['count'], $productId]
    );
}

/** Has this visitor already reviewed this product? (spam brake) */
function has_recent_review(int $productId, ?int $userId, string $name): bool
{
    if ($userId !== null) {
        return db_one(
            'SELECT id FROM reviews WHERE product_id = ? AND user_id = ? AND created_at > NOW() - INTERVAL 1 DAY',
            [$productId, $userId]
        ) !== null;
    }
    if (trim($name) === '') {
        return false;
    }
    return db_one(
        "SELECT id FROM reviews
          WHERE product_id = ? AND user_id IS NULL AND reviewer_name = ? AND created_at > NOW() - INTERVAL 1 DAY",
        [$productId, trim($name)]
    ) !== null;
}

/* --------------------------------------------------------- page titles */

function set_title(string $title): void
{
    $GLOBALS['page_title'] = $title;
}

function page_title(): string
{
    return $GLOBALS['page_title'] ?? 'Luxury Lingerie & Instruments | Tarkwa, Ghana';
}

/* ----------------------------------------------------------- meta / SEO */

/**
 * Per-page meta description, social image and robots directive.
 * Consumed by includes/head.php.
 */
function set_meta(?string $description = null, ?string $image = null, bool $noindex = false, string $ogType = 'website'): void
{
    $GLOBALS['page_meta'] = [
        'description' => $description,
        'image'       => $image,
        'noindex'     => $noindex,
        'og_type'     => $ogType,
    ];
}

function page_meta(): array
{
    return $GLOBALS['page_meta'] ?? ['description' => null, 'image' => null, 'noindex' => false, 'og_type' => 'website'];
}

/** Extra JSON-LD block(s) for the page (Organization, Product, ...). */
function set_jsonld(array $data): void
{
    $GLOBALS['page_jsonld'][] = $data;
}

function page_jsonld(): array
{
    return $GLOBALS['page_jsonld'] ?? [];
}

/** Absolute URL on the canonical origin (APP_URL, else the live host). */
function absolute_url(string $path = ''): string
{
    return APP_URL . url($path);
}

/** Absolute URL of the page being rendered, query string included. */
function canonical_url(): string
{
    return APP_URL . here_url();
}

/** Renders the storefront <head> + <header>; expects $GLOBALS['page_title']. */
function render_head(): void
{
    require __DIR__ . '/includes/head.php';
    require __DIR__ . '/includes/header.php';
}

function render_foot(): void
{
    require __DIR__ . '/includes/footer.php';
}

/* -------------------------------------------------------- breadcrumbs */

function breadcrumbs(array $crumbs): void
{
    $GLOBALS['breadcrumbs'] = $crumbs;
}

/* ----------------------------------------------------------- paystack */

function paystack_public_key(): string
{
    $envKey = defined('PAYSTACK_PUBLIC_KEY') ? (string) PAYSTACK_PUBLIC_KEY : '';
    if ($envKey !== '') {
        return $envKey;
    }
    return (string) setting('paystack_public_key', '');
}

function paystack_secret_key(): string
{
    $envKey = defined('PAYSTACK_SECRET_KEY') ? (string) PAYSTACK_SECRET_KEY : '';
    if ($envKey !== '') {
        return $envKey;
    }
    return (string) setting('paystack_secret_key', '');
}

function is_paystack_configured(): bool
{
    $sec = paystack_secret_key();
    return $sec !== '' && (str_starts_with($sec, 'sk_live_') || str_starts_with($sec, 'sk_test_') || strlen($sec) >= 10);
}

/**
 * Execute a Paystack API request (Initialize, Verify, etc.).
 *
 * @param string $endpoint e.g. 'transaction/initialize' or 'transaction/verify/REF'
 * @param string $method   'GET' or 'POST'
 * @param array  $data     Payload for POST
 * @return array{ok: bool, message?: string, data?: array}
 */
function paystack_api_request(string $endpoint, string $method = 'GET', array $data = []): array
{
    $secretKey = paystack_secret_key();
    if ($secretKey === '') {
        return ['ok' => false, 'message' => 'Paystack secret key is missing.'];
    }

    $url = 'https://api.paystack.co/' . ltrim($endpoint, '/');
    $headers = [
        'Authorization: Bearer ' . $secretKey,
        'Content-Type: application/json',
        'Cache-Control: no-cache',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($res === false || $err) {
            return ['ok' => false, 'message' => 'Network error connecting to Paystack: ' . $err];
        }

        $json = json_decode((string) $res, true);
        if (!is_array($json)) {
            return ['ok' => false, 'message' => 'Invalid response from Paystack API.'];
        }

        if (empty($json['status'])) {
            return ['ok' => false, 'message' => $json['message'] ?? 'Paystack transaction could not be initialized.'];
        }

        return ['ok' => true, 'data' => $json['data'] ?? []];
    }

    // Stream fallback
    $opts = [
        'http' => [
            'method'        => strtoupper($method),
            'header'        => implode("\r\n", $headers),
            'timeout'       => 30,
            'ignore_errors' => true,
        ],
    ];
    if (strtoupper($method) === 'POST') {
        $opts['http']['content'] = json_encode($data);
    }
    $ctx = stream_context_create($opts);
    $res = @file_get_contents($url, false, $ctx);
    if ($res === false) {
        return ['ok' => false, 'message' => 'Could not connect to Paystack API.'];
    }
    $json = json_decode($res, true);
    if (!is_array($json) || empty($json['status'])) {
        return ['ok' => false, 'message' => $json['message'] ?? 'Paystack error'];
    }
    return ['ok' => true, 'data' => $json['data'] ?? []];
}

/**
 * Verify a Paystack transaction against a server-computed order total.
 *
 * A "success" flag on its own is not proof that the customer paid what the
 * order costs - the inline popup takes the amount from the browser. So this
 * also checks amount, currency, and that the reference has not been booked
 * against another order already.
 *
 * @param string      $reference     Reference reported by Paystack.
 * @param float       $expectedTotal Server-side total for the order (GH₵).
 * @param string|null $ownOrderNo    Order this reference belongs to; pass null
 *                                   before the order row exists (checkout).
 * @return array{ok: bool, message?: string, data?: array}
 */
function paystack_verify_payment(string $reference, float $expectedTotal, ?string $ownOrderNo = null): array
{
    $verify = paystack_api_request('transaction/verify/' . urlencode($reference));
    if (!$verify['ok']) {
        return ['ok' => false, 'message' => $verify['message'] ?? 'Payment could not be verified.'];
    }

    $data = is_array($verify['data'] ?? null) ? $verify['data'] : [];
    if (($data['status'] ?? '') !== 'success') {
        return [
            'ok'      => false,
            'message' => (string) ($data['gateway_response'] ?? 'Payment was not successful.'),
        ];
    }

    // One reference, one order - a verified payment must not be replayed.
    $sql    = 'SELECT order_no FROM orders WHERE payment_reference = ?';
    $params = [$reference];
    if ($ownOrderNo !== null && $ownOrderNo !== '') {
        $sql .= ' AND order_no <> ?';
        $params[] = $ownOrderNo;
    }
    if (db_one($sql . ' LIMIT 1', $params) !== null) {
        return ['ok' => false, 'message' => 'That payment reference has already been used.'];
    }

    $expected = (int) round($expectedTotal * 100);
    $paid     = (int) ($data['amount'] ?? -1);
    // 2 pesewas of slack: checkout.js rounds the same cart total in float land.
    if ($paid < $expected - 2 || $paid > $expected + 2) {
        return [
            'ok'      => false,
            'message' => sprintf(
                'Payment amount does not match this order (expected GH₵%.2f, gateway reports GH₵%.2f).',
                $expectedTotal,
                $paid / 100
            ),
        ];
    }

    if (strtoupper((string) ($data['currency'] ?? '')) !== 'GHS') {
        return ['ok' => false, 'message' => 'Payment currency mismatch.'];
    }

    return ['ok' => true, 'data' => $data];
}

/**
 * May the simulated (key-less) Paystack flow mark an order paid?
 *
 * Never in production: a missing key there is a misconfiguration, not a demo,
 * and silently confirming orders would ship goods for free.
 */
function paystack_demo_allowed(): bool
{
    return APP_ENV !== 'production';
}

