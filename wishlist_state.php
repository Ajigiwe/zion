<?php
/**
 * Read-only JSON: the signed-in shopper's wishlist product ids.
 *
 * Used by the storefront to re-sync heart buttons after a back/forward cache
 * restore or when a shopper returns to a tab that went stale. Never cached.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo json_encode(['ok' => true, 'ids' => wishlist_ids()], JSON_UNESCAPED_SLASHES);
