<?php
/**
 * Single POST endpoint for all storefront interactions.
 * Every form posts here and is redirected back with a flash message.
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/cart_promo.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . url('index.php'));
    exit;
}
csrf_check();

$action = (string) ($_POST['action'] ?? '');
$return = (string) ($_POST['return'] ?? '');

/** True when the caller is our fetch() enhancement (wishlist toggles). */
$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

function json_out(array $payload): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Only ever redirect to a local path (never an off-site referer). */
function safe_back(string $explicit): string
{
    $explicit = str_replace('\\', '/', $explicit);
    if ($explicit !== '' && !str_contains($explicit, '://') && !str_starts_with($explicit, '//')) {
        return $explicit;
    }
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref !== '') {
        $parts = parse_url($ref);
        $host  = strtolower((string) ($parts['host'] ?? ''));
        $req   = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        // HTTP_HOST usually carries a port ("127.0.0.1:8080") that parse_url()
        // strips from the host component, so compare hostnames only.
        $reqHost = preg_replace('/:\d+$/', '', $req) ?: $req;
        if ($host === '' || $host === $reqHost) {
            $path  = (string) ($parts['path'] ?? '/');
            $query = (string) ($parts['query'] ?? '');
            return $query !== '' ? $path . '?' . $query : $path;
        }
    }
    return url('index.php');
}

$back = safe_back($return);

function redirect_back(string $to): never
{
    header('Location: ' . $to);
    exit;
}

/**
 * Live bag state returned with every AJAX response so the page can patch
 * itself in place (badge, line totals, order summary) without a reload.
 */
function cart_payload(): array
{
    $cart   = cart_totals();
    $promo  = !empty($_SESSION['promo_code']) ? promo_lookup((string) $_SESSION['promo_code']) : null;
    $discount = $promo !== null ? promo_discount($promo, $cart['subtotal']) : 0.0;

    $lines = [];
    foreach ($cart['rows'] as $r) {
        $lines[(string) $r['id']] = [
            'qty'        => (int) $r['qty'],
            'line_total' => price((float) $r['unit_price'] * (int) $r['qty']),
        ];
    }

    return [
        'count' => cart_count(),
        'summary' => [
            'subtotal'    => price($cart['subtotal']),
            'voucher'     => $promo !== null ? (string) $promo['code'] : '',
            'discount'    => price($discount),
            'has_discount'=> $discount > 0,
            'shipping'    => $cart['shipping'] == 0 ? 'FREE' : price($cart['shipping']),
            'total'       => price(max(0.0, $cart['subtotal'] - $discount + $cart['shipping'])),
            'gap'         => price(max(0.0, FREE_SHIPPING_THRESHOLD - $cart['subtotal'])),
            'free_ship'   => $cart['subtotal'] >= FREE_SHIPPING_THRESHOLD,
        ],
        'lines' => $lines,
        'promo_html' => promo_block_html($promo, $discount),
    ];
}

switch ($action) {
    case 'add_to_cart':
        $productId = (int) ($_POST['product_id'] ?? 0);
        $qty       = max(1, min(20, (int) ($_POST['qty'] ?? 1)));
        $size      = trim((string) ($_POST['size'] ?? ''));
        $color     = trim((string) ($_POST['color'] ?? ''));
        $product   = db_one('SELECT name FROM products WHERE id = ? AND is_active = 1', [$productId]);
        if ($product === null) {
            if ($isAjax) {
                json_out(['ok' => false, 'message' => 'That product could not be found.']);
            }
            flash_set('error', 'That product could not be found.');
            redirect_back(url('shop.php'));
        }
        cart_add($productId, $qty, $size ?: null, $color ?: null);
        $message = $product['name'] . ' added to your bag.';
        if ($isAjax) {
            json_out(['ok' => true, 'message' => $message] + cart_payload());
        }
        flash_set('success', $message);
        redirect_back($back);

    case 'update_cart':
        foreach (($_POST['qty'] ?? []) as $cartId => $qty) {
            cart_update((int) $cartId, (int) $qty);
        }
        if ($isAjax) {
            json_out(['ok' => true, 'message' => 'Bag updated.'] + cart_payload());
        }
        flash_set('success', 'Bag updated.');
        redirect_back(url('cart.php'));

    case 'remove_cart':
        cart_remove((int) ($_POST['cart_id'] ?? 0));
        if ($isAjax) {
            json_out(['ok' => true, 'message' => 'Item removed from your bag.'] + cart_payload());
        }
        flash_set('success', 'Item removed from your bag.');
        redirect_back(url('cart.php'));

    case 'wishlist':
        $productId = (int) ($_POST['product_id'] ?? 0);
        if ($productId <= 0 || db_one('SELECT id FROM products WHERE id = ?', [$productId]) === null) {
            if ($isAjax) {
                json_out(['ok' => false, 'message' => 'That product could not be found.']);
            }
            flash_set('error', 'That product could not be found.');
            redirect_back(url('shop.php'));
        }
        if (!is_logged_in()) {
            if ($isAjax) {
                json_out([
                    'ok'          => false,
                    'needs_login' => true,
                    'login'       => url('login.php') . '?next=' . urlencode($back),
                    'message'     => 'Sign in to save items to your wishlist.',
                ]);
            }
            flash_set('info', 'Sign in to save items to your wishlist.');
            redirect_back(url('login.php') . '?next=' . urlencode($back));
        }
        $added   = wishlist_toggle($productId);
        $name    = (string) db_val('SELECT name FROM products WHERE id = ?', [$productId], 'Item');
        $message = $added ? $name . ' saved to your wishlist.' : $name . ' removed from your wishlist.';
        if ($isAjax) {
            json_out([
                'ok'      => true,
                'added'   => $added,
                'count'   => count(wishlist_ids()),
                'message' => $message,
            ]);
        }
        flash_set('success', $message);
        redirect_back($back);

    case 'apply_promo':
        $code = strtoupper(trim((string) ($_POST['promo'] ?? '')));
        $promo = promo_lookup($code);
        if ($promo === null) {
            if ($isAjax) {
                json_out(['ok' => false, 'message' => 'That voucher code is not valid or has expired.']);
            }
            flash_set('error', 'That voucher code is not valid or has expired.');
            redirect_back(url('cart.php'));
        }
        $_SESSION['promo_code'] = $promo['code'];
        $message = 'Voucher ' . $promo['code'] . ' applied.';
        if ($isAjax) {
            json_out(['ok' => true, 'message' => $message] + cart_payload());
        }
        flash_set('success', $message);
        redirect_back(url('cart.php'));

    case 'clear_promo':
        unset($_SESSION['promo_code']);
        if ($isAjax) {
            json_out(['ok' => true, 'message' => 'Voucher removed.'] + cart_payload());
        }
        flash_set('info', 'Voucher removed.');
        redirect_back(url('cart.php'));

    case 'place_order':
        require __DIR__ . '/checkout_handler.php';
        break;

    case 'contact_send':
        // honeypot: bots fill the hidden field, humans never see it
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            flash_set('success', 'Thanks - your message is on its way.');
            redirect_back($back);
        }
        $last = (int) ($_SESSION['contact_last'] ?? 0);
        if ($last > 0 && (time() - $last) < 30) {
            flash_set('error', 'You just sent a message. Please wait a moment before sending another.');
            redirect_back($back);
        }
        $name    = trim((string) ($_POST['name'] ?? ''));
        $email   = filter_var((string) ($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone   = trim((string) ($_POST['phone'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? 'General enquiry'));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($name === '' || $email === false || mb_strlen($message) < 10) {
            flash_set('error', 'Please add your name, a valid email and a message of at least 10 characters.');
            redirect_back($back);
        }
        db_exec(
            'INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?,?,?,?,?)',
            [mb_substr($name, 0, 120), $email, mb_substr($phone, 0, 40), mb_substr($subject, 0, 160), $message]
        );
        $_SESSION['contact_last'] = time();
        flash_set('success', 'Thank you, ' . strtok($name, ' ') . '. Your message is with our Accra team - we reply within two hours during business hours.');
        redirect_back($back);

    case 'newsletter':
        $email = filter_var((string) ($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            if ($isAjax) {
                json_out(['ok' => false, 'message' => 'Please enter a valid email address.']);
            }
            flash_set('error', 'Please enter a valid email address.');
            redirect_back(url('index.php'));
        }
        $message = 'Welcome to the Zion Groups list. Check your inbox for your 10% privilege.';
        if ($isAjax) {
            json_out(['ok' => true, 'message' => $message]);
        }
        flash_set('success', $message);
        redirect_back(url('index.php'));

    case 'account_details':
        require_login();
        csrf_check();
        $name  = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $u = current_user();
        if ($name === '') {
            flash_set('error', 'Name cannot be empty.');
            redirect_back(url('account.php'));
        }
        db_exec('UPDATE users SET name = ?, phone = ? WHERE id = ?', [$name, $phone, (int) $u['id']]);
        user_forget();
        flash_set('success', 'Profile updated.');
        redirect_back(url('account.php'));

    case 'save_address':
        require_login();
        $u = current_user();
        $fields = ['recipient', 'phone', 'region', 'city', 'street'];
        $data = [];
        foreach ($fields as $f) {
            $data[$f] = trim((string) ($_POST[$f] ?? ''));
        }
        if (in_array('', $data, true)) {
            flash_set('error', 'Please complete every address field.');
            redirect_back(url('account.php?tab=addresses'));
        }
        $id = (int) ($_POST['address_id'] ?? 0);
        if ($id > 0) {
            db_exec('UPDATE addresses SET recipient=?, phone=?, region=?, city=?, street=? WHERE id=? AND user_id=?',
                [$data['recipient'], $data['phone'], $data['region'], $data['city'], $data['street'], $id, (int) $u['id']]);
        } else {
            if (!empty($_POST['is_default'])) {
                db_exec('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [(int) $u['id']]);
            }
            db_exec('INSERT INTO addresses (user_id, recipient, phone, region, city, street, is_default) VALUES (?,?,?,?,?,?,?)',
                array_merge([(int) $u['id']], array_values($data), [!empty($_POST['is_default']) ? 1 : 0]));
        }
        flash_set('success', 'Address saved.');
        redirect_back(url('account.php?tab=addresses'));

    case 'delete_address':
        require_login();
        db_exec('DELETE FROM addresses WHERE id = ? AND user_id = ?',
            [(int) ($_POST['address_id'] ?? 0), (int) current_user()['id']]);
        flash_set('success', 'Address removed.');
        redirect_back(url('account.php?tab=addresses'));

    case 'submit_review':
        $productId = (int) ($_POST['product_id'] ?? 0);
        $product = db_one('SELECT id, name, slug FROM products WHERE id = ? AND is_active = 1', [$productId]);
        $toReviews = $product !== null ? url('product.php?slug=' . urlencode($product['slug'])) . '#reviews' : $back;
        /** Validation exit that answers fetch() with JSON when called via AJAX. */
        $fail = static function (string $msg, string $type = 'error') use ($toReviews, $isAjax): never {
            if ($isAjax) {
                json_out(['ok' => false, 'message' => $msg]);
            }
            flash_set($type, $msg);
            redirect_back($toReviews);
        };
        if ($product === null) {
            if ($isAjax) {
                json_out(['ok' => false, 'message' => 'That product could not be found.']);
            }
            flash_set('error', 'That product could not be found.');
            redirect_back(url('shop.php'));
        }
        // honeypot - humans never see the hidden field
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            if ($isAjax) {
                json_out(['ok' => true, 'message' => 'Thank you. Your review is with our moderators.']);
            }
            redirect_back($toReviews);
        }
        $last = (int) ($_SESSION['review_last'] ?? 0);
        if ($last > 0 && (time() - $last) < 20) {
            $fail('You just posted a review. Please wait a moment before adding another.');
        }

        $u      = current_user();
        $name   = $u !== null ? (string) $u['name'] : trim((string) ($_POST['reviewer_name'] ?? ''));
        $rating = (int) ($_POST['rating'] ?? 0);
        $title  = trim((string) ($_POST['title'] ?? ''));
        $body   = trim((string) ($_POST['body'] ?? ''));

        if ($u === null && mb_strlen($name) < 2) {
            $fail('Please tell us your name.');
        }
        if ($rating < 1 || $rating > 5) {
            $fail('Please choose a star rating from 1 to 5.');
        }
        if (mb_strlen($body) < 10) {
            $fail('Please write at least 10 characters about the product.');
        }
        if (has_recent_review($productId, $u !== null ? (int) $u['id'] : null, $name)) {
            $fail('You have already reviewed this product recently.', 'info');
        }

        db_exec(
            'INSERT INTO reviews (product_id, user_id, reviewer_name, rating, title, body, is_approved)
             VALUES (?,?,?,?,?,?,0)',
            [
                $productId,
                $u !== null ? (int) $u['id'] : null,
                mb_substr($name, 0, 120),
                $rating,
                $title !== '' ? mb_substr($title, 0, 160) : null,
                $body,
            ]
        );
        $_SESSION['review_last'] = time();
        $message = 'Thank you, ' . strtok($name, ' ') . '. Your review is with our moderators and will appear shortly.';
        if ($isAjax) {
            json_out(['ok' => true, 'message' => $message]);
        }
        flash_set('success', $message);
        redirect_back($toReviews);

    default:
        flash_set('error', 'Unrecognised action.');
        redirect_back($back);
}
