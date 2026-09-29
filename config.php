<?php
/**
 * Zion Groups of Companies - bootstrap.
 * Every storefront / admin entry point starts with: require __DIR__.'/config.php';
 *
 * Configuration comes from the environment. Copy .env.example to .env and edit it
 * for production; without a .env the development defaults below are used.
 */

declare(strict_types=1);

/* --------------------------------------------------------------- .env */

/** Read a dotenv file into the process environment (existing env vars win). */
function env_load(string $file): void
{
    if (!is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'") && str_ends_with($value, $value[0])) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, string $default = ''): string
{
    $v = getenv($key);
    return $v === false || $v === '' ? $default : $v;
}

env_load(__DIR__ . '/.env');

/* ----------------------------------------------------------- environment */

define('APP_ENV', env('APP_ENV', 'development'));
define('APP_DEBUG', env('APP_DEBUG', APP_ENV === 'production' ? 'false' : 'true') === 'true');

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
if (!APP_DEBUG) {
    $logDir = __DIR__ . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    ini_set('error_log', $logDir . '/php-error.log');
}

/** True when the request arrived over HTTPS (directly or via a proxy). */
function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** Canonical origin for absolute URLs: APP_URL when configured, else the live host. */
function app_origin(): string
{
    $configured = rtrim(env('APP_URL'), '/');
    if ($configured !== '') {
        return $configured;
    }
    $scheme = is_https() ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

define('APP_URL', app_origin());

if (is_https()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => is_https(),
    ]);
    session_name('ZION_SESS');
    session_start();
}

/* ---------------------------------------------------------------- database */
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int) env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'velora_shop'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

/* --------------------------------------------------------------- services */
// Set these in .env once the Paystack / SMTP accounts exist (unused until then).
define('PAYSTACK_PUBLIC_KEY', env('PAYSTACK_PUBLIC_KEY'));
define('PAYSTACK_SECRET_KEY', env('PAYSTACK_SECRET_KEY'));
define('MAIL_MAILER', env('MAIL_MAILER', 'log'));          // log | smtp | php
define('MAIL_FROM', env('MAIL_FROM', 'orders@ziongroups.com.gh'));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Zion Groups of Companies'));
define('MAIL_HOST', env('MAIL_HOST'));
define('MAIL_PORT', (int) env('MAIL_PORT', '587'));
define('MAIL_USER', env('MAIL_USER'));
define('MAIL_PASS', env('MAIL_PASS'));
define('MAIL_ENCRYPT', env('MAIL_ENCRYPT', 'tls'));
define('ADMIN_EMAIL', env('ADMIN_EMAIL', 'admin@ziongroups.com.gh'));
define('LOW_STOCK_THRESHOLD', (int) env('LOW_STOCK_THRESHOLD', '5'));

/* ------------------------------------------------------------------- app   */
const SITE_NAME    = 'Zion Groups of Companies';
const SITE_TAGLINE = 'Style. Sound. You.';
const CURRENCY     = 'GH₵';
const FREE_SHIPPING_THRESHOLD = 1000.00;
const SHIPPING_METRO    = 0.00;
const SHIPPING_REGIONAL = 45.00;

/**
 * Absolute base path of the app ("" when served from the docroot,
 * "/velora-shop" when served from a sub-directory).
 */
function base_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $dir  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $dir  = $dir === '/' || $dir === '.' ? '' : rtrim($dir, '/');
        // admin/* entry points live one level deeper
        if (substr($dir, -6) === '/admin') {
            $dir = substr($dir, 0, -6);
        }
        $base = $dir;
    }
    return $base . '/' . ltrim($path, '/');
}

function url(string $path = ''): string
{
    return base_url($path);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/filters.php';
require_once __DIR__ . '/includes/mailer.php';
