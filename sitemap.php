<?php
/**
 * XML sitemap: home, departments, categories, products and editorial pages.
 * Regenerated on request - no build step needed.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$urls = [];

/** @param array<string,string> $extra */
function sitemap_add(string $loc, string $priority = '0.7', string $changefreq = 'weekly', string $lastmod = ''): void
{
    global $urls;
    $urls[] = [
        'loc'        => $loc,
        'lastmod'    => $lastmod !== '' ? $lastmod : date('Y-m-d'),
        'priority'   => $priority,
        'changefreq' => $changefreq,
    ];
}

sitemap_add(absolute_url('index.php'), '1.0', 'daily');
sitemap_add(absolute_url('shop.php'), '0.9', 'daily');
sitemap_add(absolute_url('shop.php?dept=lingerie'), '0.9', 'daily');
sitemap_add(absolute_url('shop.php?dept=instruments'), '0.9', 'daily');

foreach (db_all('SELECT slug, department FROM categories ORDER BY id') as $c) {
    if ($c['slug'] === 'lingerie' || $c['slug'] === 'instruments') {
        continue;
    }
    sitemap_add(absolute_url('category.php?slug=' . urlencode($c['slug'])), '0.8', 'weekly');
}

foreach (db_all('SELECT slug, updated_at FROM products WHERE is_active = 1 ORDER BY id') as $p) {
    sitemap_add(
        absolute_url('product.php?slug=' . urlencode($p['slug'])),
        '0.8',
        'weekly',
        date('Y-m-d', strtotime((string) $p['updated_at']))
    );
}

$pages = ['about-us', 'our-story', 'contact', 'shipping-and-returns', 'faq', 'size-guide', 'terms', 'privacy-policy'];
foreach ($pages as $slug) {
    sitemap_add(absolute_url('page.php?slug=' . urlencode($slug)), '0.6', 'monthly');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n"
       . '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>' . "\n"
       . '    <lastmod>' . e($u['lastmod']) . '</lastmod>' . "\n"
       . '    <changefreq>' . e($u['changefreq']) . '</changefreq>' . "\n"
       . '    <priority>' . e($u['priority']) . '</priority>' . "\n"
       . '  </url>' . "\n";
}
echo '</urlset>' . "\n";
