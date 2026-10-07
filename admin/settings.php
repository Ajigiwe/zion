<?php
/** Site settings: brand, theme, card arrangement, contact details, location. */

declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_admin();

const THEME_KEYS = [
    'theme_preset', 'theme_primary', 'theme_secondary', 'theme_background',
    'theme_surface', 'theme_on_surface', 'theme_inverse_surface',
    'font_headline', 'font_body',
];

const TEXT_KEYS = [
    'site_name', 'site_tagline', 'site_description', 'footer_about',
    'font_headline', 'font_body',
    'contact_email', 'contact_phone', 'contact_whatsapp', 'contact_hours', 'contact_response',
    'address_line', 'address_city', 'address_country', 'map_lat', 'map_lng',
    'social_instagram', 'social_tiktok', 'social_facebook', 'social_youtube',
    'grid_columns', 'card_ratio', 'home_featured', 'home_new',
    'shipping_metro_tag', 'shipping_metro_title', 'shipping_metro_desc', 'shipping_metro_fee',
    'shipping_regional_tag', 'shipping_regional_title', 'shipping_regional_desc', 'shipping_regional_fee',
    'shipping_pickup_tag', 'shipping_pickup_title', 'shipping_pickup_desc', 'shipping_pickup_fee',
    'free_shipping_threshold',
    'discreet_packaging_title', 'discreet_packaging_desc', 'discreet_packaging_default',
    'policy_returns_days', 'policy_returns_summary', 'policy_returns_full',
    'policy_lingerie_hygiene', 'policy_instruments_warranty',
    'paystack_public_key', 'paystack_secret_key',
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $act = (string) ($_POST['act'] ?? 'save');
    $back = url('admin/settings.php');

    if ($act === 'reset_theme') {
        delete_settings(THEME_KEYS);
        flash_set('success', 'Theme reset to the Zion Crimson defaults.');
        header('Location: ' . $back);
        exit;
    }

    $errors = [];
    $save   = [];

    // colour preset buttons carry every colour they show
    $preset = (string) ($_POST['preset'] ?? '');
    if ($preset !== '' && isset(theme_presets()[$preset])) {
        foreach (theme_presets()[$preset] as $k => $v) {
            if ($k !== 'label') {
                $save[$k] = $v;
            }
        }
        $save['theme_preset'] = $preset;
    }

    foreach (TEXT_KEYS as $key) {
        $value = trim((string) ($_POST[$key] ?? ''));
        if (in_array($key, ['theme_primary', 'theme_secondary', 'theme_background', 'theme_surface', 'theme_on_surface', 'theme_inverse_surface'], true)) {
            continue; // colours handled below
        }
        $save[$key] = $value;
    }

    $save['discreet_packaging_default'] = isset($_POST['discreet_packaging_default']) && $_POST['discreet_packaging_default'] === '1' ? '1' : '0';

    foreach (['theme_primary', 'theme_secondary', 'theme_background', 'theme_surface', 'theme_on_surface', 'theme_inverse_surface'] as $key) {
        if ($preset !== '' && isset(theme_presets()[$preset])) {
            continue; // preset already filled these in
        }
        if (!array_key_exists($key, $_POST)) {
            continue; // field not posted - keep the stored colour
        }
        $value = strtolower(trim((string) $_POST[$key]));
        if (!preg_match('/^#[0-9a-f]{6}$/', $value)) {
            $errors[] = 'Each colour must be a hex value like #551022.';
            continue;
        }
        $save[$key] = $value;
        $save['theme_preset'] = 'custom';
    }

    $save['grid_columns'] = in_array($save['grid_columns'] ?? '', ['2', '3', '4'], true) ? (string) $save['grid_columns'] : '4';
    $save['card_ratio']   = in_array($save['card_ratio'] ?? '', ['auto', 'portrait', 'square', 'landscape'], true) ? (string) $save['card_ratio'] : 'auto';
    $save['home_featured'] = in_array($save['home_featured'] ?? '', ['4', '8', '12'], true) ? (string) $save['home_featured'] : '4';
    $save['home_new'] = in_array($save['home_new'] ?? '', ['0', '4', '8', '12'], true) ? (string) $save['home_new'] : '8';

    if (($save['site_name'] ?? '') === '') {
        $errors[] = 'The site name cannot be empty.';
    }
    if (($save['contact_email'] ?? '') !== '' && !filter_var($save['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'That contact email address is not valid.';
    }
    $lat = is_numeric($save['map_lat'] ?? null) ? (float) $save['map_lat'] : null;
    $lng = is_numeric($save['map_lng'] ?? null) ? (float) $save['map_lng'] : null;
    if ($lat === null || $lat < -90 || $lat > 90) {
        $errors[] = 'Latitude must be a decimal number between -90 and 90.';
    }
    if ($lng === null || $lng < -180 || $lng > 180) {
        $errors[] = 'Longitude must be a decimal number between -180 and 180.';
    }
    foreach (['instagram', 'tiktok', 'facebook', 'youtube'] as $k) {
        $key = 'social_' . $k;
        if (($save[$key] ?? '') !== '' && !filter_var($save[$key], FILTER_VALIDATE_URL)) {
            $errors[] = 'The ' . $k . ' link must be a full URL (https://...).';
        }
    }

    // hero slider: repeatable slide blocks + automatic rotation
    if (isset($_POST['hero_title'])) {
        $titles = (array) $_POST['hero_title'];
        $subs   = (array) ($_POST['hero_subtitle'] ?? []);
        $bodies = (array) ($_POST['hero_body'] ?? []);
        $cta1   = (array) ($_POST['hero_cta_label'] ?? []);
        $cta1h  = (array) ($_POST['hero_cta_href'] ?? []);
        $cta2   = (array) ($_POST['hero_cta2_label'] ?? []);
        $cta2h  = (array) ($_POST['hero_cta2_href'] ?? []);
        $images = (array) ($_POST['hero_image'] ?? []);

        $slides = [];
        foreach ($titles as $i => $unused) {
            $slide = [
                'title'      => trim((string) $titles[$i]),
                'subtitle'   => trim((string) ($subs[$i] ?? '')),
                'body'       => trim((string) ($bodies[$i] ?? '')),
                'cta_label'  => trim((string) ($cta1[$i] ?? '')),
                'cta_href'   => trim((string) ($cta1h[$i] ?? '')),
                'cta2_label' => trim((string) ($cta2[$i] ?? '')),
                'cta2_href'  => trim((string) ($cta2h[$i] ?? '')),
                'image_url'  => trim((string) ($images[$i] ?? '')),
            ];
            if (implode('', $slide) === '') {
                continue; // blank row - nothing to show
            }
            if ($slide['title'] === '') {
                $errors[] = 'Every hero slide needs a headline.';
                continue;
            }
            foreach ([[$slide['cta_label'], $slide['cta_href']], [$slide['cta2_label'], $slide['cta2_href']]] as [$lab, $href]) {
                if (($lab === '') !== ($href === '')) {
                    $errors[] = 'Hero buttons need both a label and a link.';
                }
                if ($href !== '' && preg_match('#^javascript:#i', $href)) {
                    $errors[] = 'Hero links cannot start with javascript:.';
                }
                if ($href !== '' && preg_match('#^https?://#i', $href) && filter_var($href, FILTER_VALIDATE_URL) === false) {
                    $errors[] = 'That hero button link is not a valid URL.';
                }
            }
            if ($slide['image_url'] !== '' && preg_match('#^https?://#i', $slide['image_url']) && filter_var($slide['image_url'], FILTER_VALIDATE_URL) === false) {
                $errors[] = 'That hero image URL is not valid.';
            }
            $slides[] = $slide;
        }

        if ($slides === []) {
            $errors[] = 'The hero slider needs at least one slide.';
        }
        if (count($slides) > 8) {
            $errors[] = 'Keep the hero slider to 8 slides or fewer.';
            $slides = array_slice($slides, 0, 8);
        }

        $autoplay = (int) ($_POST['hero_autoplay'] ?? 6);
        if ($autoplay < 0 || $autoplay > 60) {
            $errors[] = 'Hero autoplay must be between 0 and 60 seconds.';
            $autoplay = 6;
        }

        if ($errors === []) {
            $save['hero_slides']   = json_encode(array_values($slides), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $save['hero_autoplay'] = (string) $autoplay;
        }
    }

    // sign-in slider: repeatable image blocks + automatic rotation
    if (isset($_POST['auth_image'])) {
        $aImgs = (array) $_POST['auth_image'];
        $aCaps = (array) ($_POST['auth_caption'] ?? []);

        $aSlides = [];
        foreach ($aImgs as $i => $unused) {
            $img = trim((string) ($aImgs[$i] ?? ''));
            if ($img === '') {
                continue; // blank rows fall back to the catalogue
            }
            $cap = trim((string) ($aCaps[$i] ?? ''));
            if (preg_match('#^https?://#i', $img) && filter_var($img, FILTER_VALIDATE_URL) === false) {
                $errors[] = 'That sign-in slide image URL is not valid.';
                continue;
            }
            $aSlides[] = ['image_url' => $img, 'caption' => $cap];
        }

        if (count($aSlides) > 8) {
            $errors[] = 'Keep the sign-in slider to 8 slides or fewer.';
            $aSlides = array_slice($aSlides, 0, 8);
        }

        $aAutoplay = (int) ($_POST['auth_slider_autoplay'] ?? 5);
        if ($aAutoplay < 0 || $aAutoplay > 60) {
            $errors[] = 'Sign-in slider autoplay must be between 0 and 60 seconds.';
            $aAutoplay = 5;
        }

        if ($errors === []) {
            $save['auth_slider_slides']   = json_encode(array_values($aSlides), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $save['auth_slider_autoplay'] = (string) $aAutoplay;
        }
    }

    if ($errors === []) {
        set_settings($save);
        flash_set('success', 'Settings saved. The storefront has already picked them up.');
        header('Location: ' . $back);
        exit;
    }
}

$v = static fn (string $key): string => e(setting($key));
$heroSlides = hero_slides();
$presets = theme_presets();
$fonts   = font_options();

$heroBlock = static function (array $s, int $i): string {
    $in = static function (string $name, string $value, string $placeholder = ''): string {
        return '<input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"'
            . ' name="' . e($name) . '[]" value="' . e($value) . '"'
            . ($placeholder !== '' ? ' placeholder="' . e($placeholder) . '"' : '')
            . ($name === 'hero_title' ? ' required' : '') . '/>';
    };
    $num = $i + 1;
    $out = '<div class="rounded-lg border border-outline-variant/70 bg-surface-container p-3 flex flex-col gap-2" data-slide-block>'
        . '<div class="flex items-center justify-between gap-3">'
        . '<span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider" data-slide-label>Slide ' . $num . '</span>'
        . '<button class="px-3 py-1.5 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-error hover:bg-error-container/40"'
        . ' type="button" data-remove-slide>Remove</button>'
        . '</div>'
        . '<div class="grid grid-cols-1 md:grid-cols-2 gap-2">'
        . '<label class="flex flex-col gap-1"><span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Headline</span>'
        . $in('hero_title', (string) ($s['title'] ?? ''), 'Express Yourself.') . '</label>'
        . '<label class="flex flex-col gap-1"><span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Italic line</span>'
        . $in('hero_subtitle', (string) ($s['subtitle'] ?? ''), 'Style for your body.') . '</label>'
        . '<label class="flex flex-col gap-1 md:col-span-2"><span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Body copy</span>'
        . '<textarea class="min-h-16 p-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"'
        . ' name="hero_body[]" placeholder="One or two sentences.">' . e((string) ($s['body'] ?? '')) . '</textarea></label>'
        . '<label class="flex flex-col gap-1"><span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Main button</span>'
        . $in('hero_cta_label', (string) ($s['cta_label'] ?? ''), 'Shop Lingerie') . '</label>'
        . '<label class="flex flex-col gap-1"><span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Main button link</span>'
        . $in('hero_cta_href', (string) ($s['cta_href'] ?? ''), 'shop.php?dept=lingerie') . '</label>'
        . '<label class="flex flex-col gap-1"><span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Second button</span>'
        . $in('hero_cta2_label', (string) ($s['cta2_label'] ?? ''), 'Explore Music') . '</label>'
        . '<label class="flex flex-col gap-1"><span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Second button link</span>'
        . $in('hero_cta2_href', (string) ($s['cta2_href'] ?? ''), 'shop.php?dept=instruments') . '</label>'
        . upload_field('hero_image[]', (string) ($s['image_url'] ?? ''), [
            'wrap'  => 'md:col-span-2',
            'label' => 'Background image',
            'hint'  => 'Upload a JPG, PNG, WebP or GIF (up to 8 MB) - stored in storage/uploads/. Leave blank to use the matching department image.',
        ])
        . '</div></div>';

    return $out;
};

$authBlock = static function (array $s, int $i): string {
    $in = static function (string $name, string $value, string $placeholder = ''): string {
        return '<input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"'
            . ' name="' . e($name) . '[]" value="' . e($value) . '"'
            . ($placeholder !== '' ? ' placeholder="' . e($placeholder) . '"' : '') . '/>';
    };
    $num = $i + 1;
    $out = '<div class="rounded-lg border border-outline-variant/70 bg-surface-container p-3 flex flex-col gap-2" data-slide-block>'
        . '<div class="flex items-center justify-between gap-3">'
        . '<span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider" data-slide-label>Slide ' . $num . '</span>'
        . '<button class="px-3 py-1.5 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-error hover:bg-error-container/40"'
        . ' type="button" data-remove-slide>Remove</button>'
        . '</div>'
        . '<div class="grid grid-cols-1 md:grid-cols-2 gap-2">'
        . upload_field('auth_image[]', (string) ($s['image_url'] ?? ''), [
            'wrap'        => 'md:col-span-2',
            'label'       => 'Slide image',
            'input_class' => 'h-11',
            'hint'        => 'Upload a JPG, PNG, WebP or GIF (up to 8 MB) - stored in storage/uploads/.',
        ])
        . '<label class="flex flex-col gap-1 md:col-span-2"><span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Caption (optional)</span>'
        . '<textarea class="min-h-16 p-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"'
        . ' name="auth_caption[]" placeholder="One or two sentences under the tagline.">' . e((string) ($s['caption'] ?? '')) . '</textarea>'
        . '<span class="font-body-sm text-body-sm text-on-surface-variant">Shown on desktop under the tagline for that slide. Leave blank for the standard line.</span></label>'
        . '</div></div>';

    return $out;
};

$authSlides = auth_slider_stored_slides();
if ($authSlides === []) {
    $authSlides = [['image_url' => '', 'caption' => '']];
}

admin_head('Settings', 'settings');
?>
<style>
  .set-section { scroll-margin-top: 1.5rem; }
  .swatch { width: 100%; height: 2.5rem; border-radius: .5rem; border: 1px solid rgba(0,0,0,.08); }
  .color-row { display: flex; align-items: center; gap: .5rem; }
  .color-row input[type=color] { width: 2.75rem; height: 2.5rem; padding: 0; border: 1px solid #d9c1c3; border-radius: .5rem; background: none; cursor: pointer; }
  .color-row input[type=text] { flex: 1; min-width: 0; }
  .preset-card[aria-pressed="true"] { outline: 2px solid #551022; outline-offset: 2px; }
</style>
<script>
  function syncColor(input) {
    var text = input.closest('.color-row').querySelector('input[type=text]');
    if (text) { text.value = input.value; }
    paintPreview();
  }
  function syncHex(text) {
    var row = text.closest('.color-row');
    var pick = row.querySelector('input[type=color]');
    if (pick && /^#[0-9a-fA-F]{6}$/.test(text.value)) { pick.value = text.value; }
    paintPreview();
  }
  function paintPreview() {
    function val(id, fallback) {
      var el = document.getElementById(id);
      if (!el) { return fallback; }
      if (el.type === 'color') { return el.value; }
      return /^#[0-9a-fA-F]{6}$/.test(el.value) ? el.value : fallback;
    }
    var p = val('theme_primary', '#551022');
    var s = val('theme_secondary', '#765a26');
    var bg = val('theme_background', '#fef8f7');
    var su = val('theme_surface', '#fef8f7');
    var on = val('theme_on_surface', '#1d1b1b');
    var box = document.getElementById('themePreview');
    if (!box) { return; }
    box.style.background = bg;
    box.style.color = on;
    var head = box.querySelector('[data-p=head]');
    if (head) { head.style.color = p; }
    var chip = box.querySelector('[data-p=chip]');
    if (chip) { chip.style.background = p; }
    var accent = box.querySelector('[data-p=accent]');
    if (accent) { accent.style.background = s; }
    var card = box.querySelector('[data-p=card]');
    if (card) { card.style.background = su; card.style.borderColor = s; }
  }
  function renumberList(list) {
    if (!list) { return; }
    var blocks = list.querySelectorAll('[data-slide-block]');
    Array.prototype.forEach.call(blocks, function (b, i) {
      var lab = b.querySelector('[data-slide-label]');
      if (lab) { lab.textContent = 'Slide ' + (i + 1); }
      var rm = b.querySelector('[data-remove-slide]');
      if (rm) { rm.style.display = blocks.length > 1 ? '' : 'none'; }
    });
  }

  function renumberAll() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-slide-list]'), renumberList);
  }

  function settingsReady() { renumberAll(); paintPreview(); }

  /* The page (this script included) is replayed after every AJAX save, so the
     delegated listeners are registered once - renumbering and the preview run
     again on every pass. */
  if (!window.__zionSettingsWired) {
    window.__zionSettingsWired = 1;

    document.addEventListener('input', function (e) {
      if (e.target && e.target.type === 'color') { syncColor(e.target); }
      else if (e.target && e.target.matches('.color-row input[type=text]')) { syncHex(e.target); }
    });

    document.addEventListener('click', function (e) {
      if (!e.target || !e.target.closest) { return; }
      var add = e.target.closest('[data-add-list]');
      if (add) {
        var list = document.getElementById(add.getAttribute('data-add-list'));
        var tpl = document.getElementById(add.getAttribute('data-add-tpl'));
        if (tpl && list) {
          list.appendChild(tpl.content.cloneNode(true));
          renumberList(list);
          var last = list.lastElementChild;
          var first = last ? last.querySelector('input') : null;
          if (first) { first.focus(); }
        }
        return;
      }
      var rm = e.target.closest('[data-remove-slide]');
      if (rm) {
        var host = rm.closest('[data-slide-list]');
        if (host && host.querySelectorAll('[data-slide-block]').length > 1) {
          rm.closest('[data-slide-block]').remove();
          renumberList(host);
        }
      }
    });

    document.addEventListener('DOMContentLoaded', settingsReady);
  }

  if (document.readyState !== 'loading') { settingsReady(); }
</script>

<div class="max-w-5xl">
  <nav class="font-label-nav text-label-nav text-on-surface-variant mb-3 flex items-center gap-2">
    <a class="hover:text-primary" href="<?= e(url('admin/index.php')) ?>">Dashboard</a>
    <span class="text-outline-variant">/</span>
    <span class="text-primary font-semibold">Settings</span>
  </nav>

  <div class="flex flex-wrap items-end justify-between gap-3 mb-space-md">
    <div>
      <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Store configuration</span>
      <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Settings</h1>
      <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Brand, sliders, theme, card arrangement, contact details and location - saved straight to the live storefront.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a class="px-4 py-2.5 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-primary hover:bg-primary-container/30 flex items-center gap-1.5 font-bold"
         href="<?= e(url('admin/backup.php')) ?>"><span class="material-symbols-outlined text-base">database</span> Backups & Wipe</a>
      <a class="px-4 py-2.5 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface-variant hover:bg-surface-container-lowest"
         target="_blank" rel="noopener" href="<?= e(url('page.php?slug=contact')) ?>">Preview contact page</a>
      <button class="px-5 py-2.5 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary"
              type="submit" form="settingsForm">Save settings</button>
    </div>
  </div>

  <?php if (($errors ?? []) !== []): ?>
    <div class="mb-4 px-4 py-3 rounded-lg bg-error-container text-on-error-container font-body-sm text-body-sm">
      <ul class="list-disc ml-5"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <div class="flex flex-wrap gap-2 mb-space-md" id="sectionTabs">
    <?php foreach ([
        'general'   => 'General',
        'shipping'  => 'Dispatch & Shipping',
        'policies'  => 'Refunds & Returns',
        'hero'      => 'Hero slider',
        'authslider' => 'Sign-in slider',
        'theme'     => 'Theme',
        'layout'    => 'Card arrangement',
        'contact'   => 'Contact & location',
        'social'    => 'Social',
    ] as $id => $label): ?>
      <a class="px-4 py-2 rounded-lg border border-outline-variant font-label-nav text-label-nav uppercase text-on-surface-variant hover:bg-surface-container-lowest hover:text-on-surface"
         href="#<?= e($id) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <form id="settingsForm" data-ajax method="post" class="flex flex-col gap-space-md">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="save"/>

    <!-- GENERAL -->
    <section id="general" class="set-section bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-space-sm">General</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Site name <span class="text-error">*</span></span>
          <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="site_name" required value="<?= $v('site_name') ?>"/>
        </label>
        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Tagline</span>
          <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="site_tagline" value="<?= $v('site_tagline') ?>" placeholder="Style. Sound. You."/>
        </label>
        <label class="flex flex-col gap-1 md:col-span-2">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Search engine description</span>
          <textarea class="min-h-20 p-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                    name="site_description" maxlength="300"><?= $v('site_description') ?></textarea>
        </label>
        <label class="flex flex-col gap-1 md:col-span-2">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Footer blurb</span>
          <textarea class="min-h-20 p-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                    name="footer_about"><?= $v('footer_about') ?></textarea>
        </label>
      </div>
    </section>

    <!-- HERO SLIDER -->
    <section id="hero" class="set-section bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-space-sm">
        <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Hero slider</h2>
        <button class="px-4 py-2 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface-variant hover:bg-surface-container hover:text-on-surface"
                type="button" data-add-list="heroSlides" data-add-tpl="heroSlideTpl">+ Add slide</button>
      </div>
      <p class="font-body-sm text-body-sm text-on-surface-variant mb-space-sm">
        The homepage banner. Slides rotate automatically using the delay below; the first slide also carries the page&rsquo;s main heading.
      </p>

      <label class="flex flex-col gap-1 max-w-xs mb-space-md">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Automatic rotation (seconds)</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
               type="number" name="hero_autoplay" min="0" max="60" step="1" value="<?= (int) hero_autoplay_seconds() ?>"/>
        <span class="font-body-sm text-body-sm text-on-surface-variant">Enter 0 to switch the automatic rotation off - visitors then move the slides themselves.</span>
      </label>

      <div class="flex flex-col gap-3" id="heroSlides" data-slide-list>
        <?php foreach ($heroSlides as $i => $slide): ?>
          <?= $heroBlock($slide, $i) ?>
        <?php endforeach; ?>
      </div>

      <template id="heroSlideTpl"><?= $heroBlock([], 0) ?></template>
    </section>

    <!-- SIGN-IN SLIDER -->
    <section id="authslider" class="set-section bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-space-sm">
        <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Sign-in slider</h2>
        <button class="px-4 py-2 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface-variant hover:bg-surface-container hover:text-on-surface"
                type="button" data-add-list="authSlides" data-add-tpl="authSlideTpl">+ Add slide</button>
      </div>
      <p class="font-body-sm text-body-sm text-on-surface-variant mb-space-sm">
        The photo panel behind the Sign in and Create account forms. It cross-fades on every screen size; dots and arrows show on wider screens.
      </p>

      <label class="flex flex-col gap-1 max-w-xs mb-space-md">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Automatic rotation (seconds)</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
               type="number" name="auth_slider_autoplay" min="0" max="60" step="1" value="<?= (int) auth_slider_autoplay_seconds() ?>"/>
        <span class="font-body-sm text-body-sm text-on-surface-variant">Enter 0 to switch the automatic rotation off - visitors then move the slides themselves.</span>
      </label>

      <div class="flex flex-col gap-3" id="authSlides" data-slide-list>
        <?php foreach ($authSlides as $i => $slide): ?>
          <?= $authBlock($slide, $i) ?>
        <?php endforeach; ?>
      </div>

      <template id="authSlideTpl"><?= $authBlock([], 0) ?></template>

      <p class="font-body-sm text-body-sm text-on-surface-variant mt-space-sm">
        Leave every image blank to keep the panel on catalogue images (department heroes first, then featured products).
      </p>
    </section>

    <!-- THEME -->
    <section id="theme" class="set-section bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-space-sm">
        <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Theme</h2>
        <button class="px-4 py-2 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface-variant hover:bg-surface-container"
                type="submit" form="settingsForm" name="act" value="reset_theme"
                onclick="return confirm('Reset every theme colour and font to the Zion defaults?');">Reset theme</button>
      </div>
      <p class="font-body-sm text-body-sm text-on-surface-variant mb-space-sm">Pick a house palette or tune each colour yourself - the whole store, including this admin, follows it instantly.</p>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-space-sm mb-space-md">
        <?php foreach ($presets as $key => $p): ?>
          <button class="text-left bg-surface-container rounded-xl p-3 border border-outline-variant/60 hover:shadow-sm transition-shadow preset-card"
                  type="submit" form="settingsForm" name="preset" value="<?= e($key) ?>"
                  aria-pressed="<?= setting('theme_preset') === $key ? 'true' : 'false' ?>">
            <span class="grid grid-cols-4 gap-1 mb-2">
              <?php foreach (['theme_primary', 'theme_secondary', 'theme_background', 'theme_on_surface'] as $ck): ?>
                <span class="swatch" style="background:<?= e($p[$ck]) ?>"></span>
              <?php endforeach; ?>
            </span>
            <span class="font-label-nav text-label-nav uppercase tracking-wider text-on-surface block"><?= e($p['label']) ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
        <?php
        $colorFields = [
            'theme_primary'         => 'Primary',
            'theme_secondary'       => 'Accent (gold)',
            'theme_background'      => 'Page background',
            'theme_surface'         => 'Cards & surfaces',
            'theme_on_surface'      => 'Body text',
            'theme_inverse_surface' => 'Footer / dark panels',
        ];
        foreach ($colorFields as $key => $label): ?>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider"><?= e($label) ?></span>
            <span class="color-row">
              <input type="color" id="<?= e($key) ?>" value="<?= $v($key) ?>" data-key="<?= e($key) ?>"/>
              <input class="h-10 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none uppercase"
                     type="text" name="<?= e($key) ?>" value="<?= $v($key) ?>" pattern="#[0-9a-fA-F]{6}"/>
            </span>
          </label>
        <?php endforeach; ?>

        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Headline typeface</span>
          <select class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="font_headline">
            <?php foreach ($fonts['headline'] as $key => $f): ?>
              <option value="<?= e($key) ?>" <?= setting('font_headline') === $key ? 'selected' : '' ?>><?= e($f['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Body typeface</span>
          <select class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="font_body">
            <?php foreach ($fonts['body'] as $key => $f): ?>
              <option value="<?= e($key) ?>" <?= setting('font_body') === $key ? 'selected' : '' ?>><?= e($f['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <div id="themePreview" class="mt-space-md rounded-xl border border-outline-variant/60 p-space-md">
        <div class="flex items-center gap-2 mb-2">
          <span data-p="chip" class="inline-block w-8 h-8 rounded-lg"></span>
          <span data-p="head" class="font-headline-md text-headline-md font-bold">Zion preview headline</span>
        </div>
        <div data-p="card" class="rounded-lg border p-3 font-body-sm text-body-sm">
          Card surface with accent border. <span class="font-label-price font-bold">GH&#8373; 420</span>
          <span data-p="accent" class="inline-block w-6 h-2 rounded-full align-middle ml-2"></span>
        </div>
      </div>
    </section>

    <!-- CARD ARRANGEMENT -->
    <section id="layout" class="set-section bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-space-sm">Card arrangement</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-space-sm">
        <div class="flex flex-col gap-2">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Products per row</span>
          <div class="flex gap-2">
            <?php foreach (['2', '3', '4'] as $n): ?>
              <label class="flex-1 cursor-pointer">
                <input class="peer sr-only" type="radio" name="grid_columns" value="<?= $n ?>" <?= setting('grid_columns') === $n ? 'checked' : '' ?>>
                <span class="block h-11 border border-outline-variant rounded font-label-nav text-label-nav uppercase text-on-surface-variant text-center leading-[2.6rem] peer-checked:bg-primary-container peer-checked:text-on-primary peer-checked:border-primary-container transition-colors"><?= $n ?> up</span>
              </label>
            <?php endforeach; ?>
          </div>
          <span class="font-body-sm text-body-sm text-on-surface-variant">Used on the shop, search, wishlist and homepage grids.</span>
        </div>

        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Card image shape</span>
          <select class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="card_ratio">
            <?php foreach (['auto' => 'Uniform 4:5 (every card the same)', 'portrait' => 'Portrait 3:4 (all cards)', 'square' => 'Square 1:1', 'landscape' => 'Landscape 4:3'] as $k => $label): ?>
              <option value="<?= e($k) ?>" <?= setting('card_ratio') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Homepage featured items</span>
          <select class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="home_featured">
            <?php foreach (['4', '8', '12'] as $n): ?>
              <option value="<?= $n ?>" <?= setting('home_featured') === $n ? 'selected' : '' ?>><?= $n ?> products</option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Homepage new arrivals</span>
          <select class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="home_new">
            <option value="0" <?= setting('home_new') === '0' ? 'selected' : '' ?>>Hidden</option>
            <?php foreach (['4', '8', '12'] as $n): ?>
              <option value="<?= $n ?>" <?= setting('home_new') === $n ? 'selected' : '' ?>><?= $n ?> products</option>
            <?php endforeach; ?>
          </select>
          <span class="font-body-sm text-body-sm text-on-surface-variant">Newest active products by date added. Hidden removes the section.</span>
        </label>
      </div>
    </section>

    <!-- CONTACT & LOCATION -->
    <section id="contact" class="set-section bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-space-sm">Contact &amp; location</h2>
      <p class="font-body-sm text-body-sm text-on-surface-variant mb-space-sm">These details drive the contact page, the footer, the checkout pick-up point and the map.</p>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Contact email</span>
          <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" type="email" name="contact_email" value="<?= $v('contact_email') ?>"/>
        </label>
        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Phone number</span>
          <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="contact_phone" value="<?= $v('contact_phone') ?>" placeholder="+233 50 123 4567"/>
        </label>
        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">WhatsApp number</span>
          <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="contact_whatsapp" value="<?= $v('contact_whatsapp') ?>" placeholder="233501234567"/>
          <span class="font-body-sm text-body-sm text-on-surface-variant">Digits only, country code first.</span>
        </label>
        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Opening hours</span>
          <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="contact_hours" value="<?= $v('contact_hours') ?>"/>
          <span class="font-body-sm text-body-sm text-on-surface-variant">Separate days and time with a colon, blocks with |</span>
        </label>
        <label class="flex flex-col gap-1 md:col-span-2">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Reply promise</span>
          <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="contact_response" value="<?= $v('contact_response') ?>"/>
        </label>

        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Street / area</span>
          <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="address_line" value="<?= $v('address_line') ?>"/>
        </label>
        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">City / region</span>
          <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="address_city" value="<?= $v('address_city') ?>"/>
        </label>
        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Country</span>
          <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="address_country" value="<?= $v('address_country') ?>"/>
        </label>
        <div class="grid grid-cols-2 gap-space-sm">
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Latitude</span>
            <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="map_lat" value="<?= $v('map_lat') ?>" placeholder="5.6037"/>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Longitude</span>
            <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="map_lng" value="<?= $v('map_lng') ?>" placeholder="-0.1780"/>
          </label>
        </div>
      </div>

      <div class="mt-space-sm flex flex-wrap gap-3 font-body-sm text-body-sm text-on-surface-variant">
        <a class="text-primary underline" target="_blank" rel="noopener"
           href="https://www.google.com/maps/search/?api=1&query=<?= e(setting('map_lat')) ?>,<?= e(setting('map_lng')) ?>">Check these coordinates on a map</a>
        <span>&middot;</span>
        <a class="text-primary underline" target="_blank" rel="noopener" href="<?= e(url('page.php?slug=contact')) ?>">See the contact page</a>
      </div>
    </section>

    <!-- SOCIAL -->
    <section id="social" class="set-section bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-space-sm">Social links</h2>
      <p class="font-body-sm text-body-sm text-on-surface-variant mb-space-sm">Leave a field blank to hide it from the footer.</p>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
        <?php foreach (['instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'youtube' => 'YouTube'] as $key => $label): ?>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider"><?= e($label) ?></span>
            <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="social_<?= e($key) ?>" value="<?= $v('social_' . $key) ?>" placeholder="https://www.<?= e($key) ?>/"/>
          </label>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- DISPATCH & SHIPPING -->
    <section id="shipping" class="set-section bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <div class="flex items-center gap-3 mb-space-sm">
        <span class="material-symbols-outlined text-secondary text-2xl">local_shipping</span>
        <div>
          <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Dispatch &amp; Delivery Methods</h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant">Customize the delivery methods, badge tags, transit timelines, fees, and packaging notice shown at checkout.</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-space-md">
        <!-- 1. Express / Metro Method -->
        <div class="rounded-xl border border-outline-variant p-4 bg-surface-container flex flex-col gap-3">
          <div class="flex items-center justify-between">
            <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary font-bold">Method 1 (Metro / Express)</span>
            <span class="px-2 py-0.5 rounded bg-primary/10 text-primary text-xs font-semibold">Greater Accra</span>
          </div>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Badge Tag</span>
            <input class="h-10 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                   name="shipping_metro_tag" value="<?= $v('shipping_metro_tag') ?>" placeholder="FASTEST"/>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Method Name</span>
            <input class="h-10 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                   name="shipping_metro_title" value="<?= $v('shipping_metro_title') ?>" placeholder="Accra Express"/>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Timeline / Subtitle</span>
            <input class="h-10 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                   name="shipping_metro_desc" value="<?= $v('shipping_metro_desc') ?>" placeholder="Same-Day / 24 hrs"/>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Fee (GH&#8373;)</span>
            <input class="h-10 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none font-mono"
                   type="number" step="0.01" min="0" name="shipping_metro_fee" value="<?= $v('shipping_metro_fee') ?>" placeholder="0.00"/>
            <span class="font-body-sm text-[11px] text-on-surface-variant">Enter 0 for FREE delivery</span>
          </label>
        </div>

        <!-- 2. Regional / Inter-City Method -->
        <div class="rounded-xl border border-outline-variant p-4 bg-surface-container flex flex-col gap-3">
          <div class="flex items-center justify-between">
            <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary font-bold">Method 2 (Regional Road)</span>
            <span class="px-2 py-0.5 rounded bg-secondary-fixed text-on-secondary-fixed text-xs font-semibold">Other Regions</span>
          </div>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Badge Tag</span>
            <input class="h-10 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                   name="shipping_regional_tag" value="<?= $v('shipping_regional_tag') ?>" placeholder="INTER-CITY"/>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Method Name</span>
            <input class="h-10 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                   name="shipping_regional_title" value="<?= $v('shipping_regional_title') ?>" placeholder="Regional Road"/>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Timeline / Subtitle</span>
            <input class="h-10 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                   name="shipping_regional_desc" value="<?= $v('shipping_regional_desc') ?>" placeholder="Kumasi / Takoradi"/>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Fee (GH&#8373;)</span>
            <input class="h-10 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none font-mono"
                   type="number" step="0.01" min="0" name="shipping_regional_fee" value="<?= $v('shipping_regional_fee') ?>" placeholder="45.00"/>
            <span class="font-body-sm text-[11px] text-on-surface-variant">Applied to destinations outside Greater Accra</span>
          </label>
        </div>

        <!-- 3. Self Pick / Store Showroom Method -->
        <div class="rounded-xl border border-outline-variant p-4 bg-surface-container flex flex-col gap-3">
          <div class="flex items-center justify-between">
            <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary font-bold">Method 3 (Self Pick)</span>
            <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-700 text-xs font-semibold">Store Pickup</span>
          </div>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Badge Tag</span>
            <input class="h-10 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                   name="shipping_pickup_tag" value="<?= $v('shipping_pickup_tag') ?>" placeholder="SELF PICK"/>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Pickup Location Name</span>
            <input class="h-10 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                   name="shipping_pickup_title" value="<?= $v('shipping_pickup_title') ?>" placeholder="Market Circle & Main Station, Tarkwa"/>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Timeline / Subtitle</span>
            <input class="h-10 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                   name="shipping_pickup_desc" value="<?= $v('shipping_pickup_desc') ?>" placeholder="Ready in 2 Hours"/>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Fee (GH&#8373;)</span>
            <input class="h-10 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none font-mono"
                   type="number" step="0.01" min="0" name="shipping_pickup_fee" value="<?= $v('shipping_pickup_fee') ?>" placeholder="0.00"/>
            <span class="font-body-sm text-[11px] text-on-surface-variant">Enter 0 for free customer pickup</span>
          </label>
        </div>
      </div>

      <div class="border-t border-outline-variant pt-4 grid grid-cols-1 md:grid-cols-2 gap-space-sm">
        <!-- Free shipping threshold -->
        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Free Shipping Threshold (GH&#8373;)</span>
          <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none font-mono"
                 type="number" step="0.01" min="0" name="free_shipping_threshold" value="<?= $v('free_shipping_threshold') ?>" placeholder="1000.00"/>
          <span class="font-body-sm text-body-sm text-on-surface-variant">Orders with subtotal at or above this amount receive free shipping automatically.</span>
        </label>

        <!-- Discreet packaging default checkbox -->
        <div class="flex flex-col gap-2 justify-center bg-surface-container rounded-xl p-3 border border-outline-variant/60">
          <label class="flex items-center gap-2.5 cursor-pointer">
            <input class="accent-primary w-4 h-4" type="checkbox" name="discreet_packaging_default" value="1" <?= setting('discreet_packaging_default', '1') === '1' ? 'checked' : '' ?>/>
            <span class="font-label-nav text-label-nav text-on-surface font-semibold uppercase tracking-wider">Check Discreet Packaging by default at checkout</span>
          </label>
          <span class="font-body-sm text-body-sm text-on-surface-variant">When checked, the packaging guarantee box is pre-selected for customers.</span>
        </div>

        <!-- Discreet packaging text options -->
        <label class="flex flex-col gap-1 md:col-span-2">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Discreet Packaging Heading</span>
          <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                 name="discreet_packaging_title" value="<?= $v('discreet_packaging_title') ?>" placeholder="Discreet Packaging Guaranteed (checked by default)."/>
        </label>

        <label class="flex flex-col gap-1 md:col-span-2">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Discreet Packaging Description Text</span>
          <textarea class="min-h-16 p-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                    name="discreet_packaging_desc"><?= $v('discreet_packaging_desc') ?></textarea>
        </label>
      </div>

      <!-- Paystack Payment Gateway -->
      <div class="border-t border-outline-variant mt-6 pt-4">
        <div class="flex items-center gap-2 mb-3">
          <span class="text-xl">💳</span>
          <div>
            <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">Paystack Payment Gateway</h3>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Live or Sandbox API keys for card, mobile money (MTN MoMo, Telecel, AT Money), and bank payments.</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-space-sm bg-surface-container rounded-xl p-4 border border-outline-variant/60">
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Paystack Public Key</span>
            <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none font-mono"
                   name="paystack_public_key" value="<?= $v('paystack_public_key') ?>" placeholder="<?= e(PAYSTACK_PUBLIC_KEY ? PAYSTACK_PUBLIC_KEY : 'pk_live_... or pk_test_...') ?>"/>
            <span class="font-body-sm text-[11px] text-on-surface-variant"><?= PAYSTACK_PUBLIC_KEY ? 'Configured in .env (' . substr(PAYSTACK_PUBLIC_KEY, 0, 8) . '...)' : 'Leave blank if set in .env' ?></span>
          </label>

          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Paystack Secret Key</span>
            <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none font-mono"
                   type="password" name="paystack_secret_key" value="<?= $v('paystack_secret_key') ?>" placeholder="<?= e(PAYSTACK_SECRET_KEY ? '••••••••••••••••' : 'sk_live_... or sk_test_...') ?>"/>
            <span class="font-body-sm text-[11px] text-on-surface-variant"><?= PAYSTACK_SECRET_KEY ? 'Configured in .env' : 'Leave blank if set in .env' ?></span>
          </label>
        </div>
      </div>

      <!-- System Reliability & Backups Quick Card -->
      <div class="border-t border-outline-variant mt-6 pt-4">
        <div class="p-4 rounded-xl bg-surface-container border border-outline-variant/60 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="flex items-start gap-3">
            <span class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-xl">database</span>
            </span>
            <div>
              <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">Database Backups & Safety Snapshots</h3>
              <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
                Generate full system archives, download raw SQL dumps, restore previous snapshots, or run controlled data wipe operations.
              </p>
            </div>
          </div>
          <a href="<?= e(url('admin/backup.php')) ?>" class="px-4 py-2.5 bg-primary text-on-primary hover:bg-primary/90 rounded-lg font-label-nav text-label-nav uppercase tracking-wider font-bold transition-colors shrink-0 flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-base">cloud_sync</span>
            Open Backups Center
          </a>
        </div>
      </div>
    </section>

    <!-- REFUNDS & RETURN POLICIES -->
    <section id="policies" class="set-section bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <div class="flex items-center gap-3 mb-space-sm">
        <span class="material-symbols-outlined text-secondary text-2xl">policy</span>
        <div>
          <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Refunds &amp; Return Policies</h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant">
            Manage your store's return window, refund terms, lingerie hygiene requirements, and instrument warranty policies. These update live on the Shipping &amp; Delivery, FAQ, and Terms of Service pages.
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-space-sm mb-space-md">
        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Return Window (Days)</span>
          <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none font-mono"
                 type="number" min="0" max="365" name="policy_returns_days" value="<?= $v('policy_returns_days') ?>" placeholder="7"/>
          <span class="font-body-sm text-[11px] text-on-surface-variant">Number of days from delivery date customers can request returns or exchanges.</span>
        </label>

        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Quick Policy Notice (Summary)</span>
          <textarea class="min-h-16 p-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                    name="policy_returns_summary" placeholder="Brief 1-2 sentence policy summary..."><?= $v('policy_returns_summary') ?></textarea>
          <span class="font-body-sm text-[11px] text-on-surface-variant">Displayed on FAQ and checkout summary cards.</span>
        </label>

        <label class="flex flex-col gap-1 md:col-span-2">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Full Return &amp; Refund Policy Text</span>
          <textarea class="min-h-24 p-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                    name="policy_returns_full" placeholder="Full return and exchange terms..."><?= $v('policy_returns_full') ?></textarea>
          <span class="font-body-sm text-[11px] text-on-surface-variant">Shown prominently on the dedicated Shipping &amp; Returns policy page.</span>
        </label>

        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Lingerie &amp; Intimate Apparel Hygiene Seal Terms</span>
          <textarea class="min-h-20 p-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                    name="policy_lingerie_hygiene" placeholder="Hygiene rules for intimate apparel..."><?= $v('policy_lingerie_hygiene') ?></textarea>
          <span class="font-body-sm text-[11px] text-on-surface-variant">Specifies conditions for unworn lingerie and hygienic liners.</span>
        </label>

        <label class="flex flex-col gap-1">
          <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Instruments &amp; Sound Equipment Warranty Policy</span>
          <textarea class="min-h-20 p-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                    name="policy_instruments_warranty" placeholder="Warranty and equipment return policy..."><?= $v('policy_instruments_warranty') ?></textarea>
          <span class="font-body-sm text-[11px] text-on-surface-variant">Outlines manufacturer warranty and equipment testing terms.</span>
        </label>
      </div>

      <div class="mt-space-sm flex flex-wrap gap-3 font-body-sm text-body-sm text-on-surface-variant border-t border-outline-variant pt-3">
        <a class="text-primary underline" target="_blank" rel="noopener" href="<?= e(url('page.php?slug=shipping-and-returns')) ?>">Preview Shipping &amp; Returns page</a>
        <span>&middot;</span>
        <a class="text-primary underline" target="_blank" rel="noopener" href="<?= e(url('page.php?slug=terms')) ?>">Preview Terms of Service</a>
      </div>
    </section>

    <div class="sticky bottom-0 bg-surface-container/95 backdrop-blur border-t border-outline-variant px-4 py-3 rounded-xl flex items-center justify-between gap-3 shadow-lg">
      <span class="font-body-sm text-body-sm text-on-surface-variant">Settings apply to every page as soon as you save.</span>
      <button class="px-6 py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary transition-colors"
              type="submit">Save settings</button>
    </div>
  </form>
</div>
<?php admin_foot(); ?>
