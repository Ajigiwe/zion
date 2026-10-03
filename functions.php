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
    db_exec(
        "UPDATE cart_items SET qty = ? WHERE id = ? AND $where",
        array_merge([min(99, $qty), $cartId], $params)
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

