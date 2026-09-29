<?php
/**
 * robots.txt content. Served dynamically so the Sitemap line always carries the
 * canonical origin (APP_URL in production, the live host in development).
 * .htaccess routes /robots.txt here when the app runs on Apache.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$lines = [
    'User-agent: *',
    'Allow: /',
    '',
    'Disallow: /admin/',
    'Disallow: /actions.php',
    'Disallow: /cart.php',
    'Disallow: /checkout.php',
    'Disallow: /account.php',
    'Disallow: /orders.php',
    'Disallow: /order.php',
    'Disallow: /order_complete.php',
    'Disallow: /wishlist.php',
    'Disallow: /wishlist_state.php',
    'Disallow: /login.php',
    'Disallow: /register.php',
    'Disallow: /logout.php',
    'Disallow: /search.php',
    'Disallow: /*?q=',
    '',
    'User-agent: SemrushBot',
    'Disallow: /',
    '',
    'Sitemap: ' . absolute_url('sitemap.php'),
];

echo implode("\n", $lines) . "\n";
