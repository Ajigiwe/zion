<?php
/** <head> + opening <body>. Expects page_title(), page_meta(), url(), cart helpers. */
$title = page_title();
$meta  = page_meta();
$description = $meta['description'] !== null && trim($meta['description']) !== ''
    ? trim($meta['description'])
    : (string) setting('site_description');
$description = mb_strimwidth($description, 0, 160, '…');
$canonical = canonical_url();
$ogImage   = $meta['image'] !== null && trim($meta['image']) !== '' ? absolute_image_url($meta['image']) : null;
$jsonld    = page_jsonld();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($description) ?>"/>
  <link rel="icon" href="<?= e(url('assets/logo-mark.svg')) ?>" type="image/svg+xml"/>
  <link rel="canonical" href="<?= e($canonical) ?>"/>
  <?php if ($meta['noindex']): ?>
    <meta name="robots" content="noindex, nofollow"/>
  <?php endif; ?>
  <meta property="og:type" content="<?= $meta['og_type'] ?? 'website' ?>"/>
  <meta property="og:site_name" content="<?= e(SITE_NAME) ?>"/>
  <meta property="og:title" content="<?= e($title) ?>"/>
  <meta property="og:description" content="<?= e($description) ?>"/>
  <meta property="og:url" content="<?= e($canonical) ?>"/>
  <?php if ($ogImage !== null && $ogImage !== ''): ?>
    <meta property="og:image" content="<?= e($ogImage) ?>"/>
    <meta name="twitter:card" content="summary_large_image"/>
  <?php else: ?>
    <meta name="twitter:card" content="summary"/>
  <?php endif; ?>
  <meta name="twitter:title" content="<?= e($title) ?>"/>
  <meta name="twitter:description" content="<?= e($description) ?>"/>
  <link href="https://fonts.googleapis.com" rel="preconnect"/>
  <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
  <link href="<?= e(font_css_url()) ?>" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&amp;display=swap" rel="stylesheet"/>
  <link href="<?= e(url('assets/base.css') . '?v=' . ((int) @filemtime(__DIR__ . '/../assets/base.css'))) ?>" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script id="tailwind-config"><?= tailwind_config_js() ?></script>
  <?php foreach ($jsonld as $block): ?>
    <script type="application/ld+json"><?= json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <?php endforeach; ?>
</head>
<body class="bg-surface font-body-md text-on-surface antialiased">
