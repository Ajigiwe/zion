<?php
/**
 * category.php?slug=bras  ->  shop.php?cat=bras
 * Keeps the pretty URLs used across the mockups while sharing all the
 * filtering / sorting logic in shop.php.
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$_GET['cat'] = (string) ($_GET['slug'] ?? '');
unset($_GET['slug']);

require __DIR__ . '/shop.php';
