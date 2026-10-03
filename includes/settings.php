<?php
/**
 * Site settings: stored key/value rows in `settings`, merged over hard defaults.
 *
 * Everything the admin can edit (brand, theme, layout, contact details,
 * location, socials) lives here and is read by the storefront on every request.
 */

declare(strict_types=1);

/* ------------------------------------------------------------ storage */

/** @return array<string,string> every setting with its default applied. */
function settings_defaults(): array
{
    return [
        /* general */
        'site_name'        => 'Zion Groups of Companies',
        'site_tagline'     => 'Style. Sound. You.',
        'site_description' => 'Zion Groups of Companies - luxury intimate apparel, professional musical instruments and church worship equipment from Tarkwa, Ghana, delivered discreetly nationwide.',
        'footer_about'     => 'From Tarkwa, Ghana: sensual luxury intimate apparel, master-crafted musical instruments, professional audio gear and complete church worship setups.',

        /* theme */
        'theme_preset'          => 'crimson',
        'theme_primary'         => '#551022',
        'theme_secondary'       => '#765a26',
        'theme_background'      => '#fef8f7',
        'theme_surface'         => '#fef8f7',
        'theme_on_surface'      => '#1d1b1b',
        'theme_inverse_surface' => '#323030',
        'font_headline'         => 'playfair',
        'font_body'             => 'inter',

        /* card arrangement */
        'grid_columns'  => '4',
        'card_ratio'    => 'auto',
        'home_featured' => '4',

        /* contact details */
        'contact_email'     => 'concierge@ziongroups.com.gh',
        'contact_phone'     => '+233 27 543 9830',
        'contact_whatsapp'  => '233541717773',
        'contact_hours'     => 'Mon - Sat: 9:00 AM - 6:00 PM  |  Sun: 12:00 PM - 5:00 PM',
        'contact_response'  => 'Our Tarkwa team replies within two hours during business hours, seven days a week.',

        /* location */
        'address_line'    => 'Market Circle & Main Station, Tarkwa',
        'address_city'    => 'Tarkwa, Western Region, Ghana',
        'address_country' => 'Ghana',
        'map_lat'         => '5.3017',
        'map_lng'         => '-2.1100',

        /* socials */
        'social_instagram' => 'https://www.instagram.com/',
        'social_tiktok'    => 'https://www.tiktok.com/',
        'social_facebook'  => 'https://www.facebook.com/',
        'social_youtube'   => 'https://www.youtube.com/',

        /* hero slider */
        'hero_slides'   => json_encode([
            [
                'title'     => 'Express Yourself.',
                'subtitle'  => 'Style for your body. Sound for your soul.',
                'body'      => 'Discover hand-finished luxury intimates crafted for pure self-assurance, paired alongside master-grade instruments for the discerning artist.',
                'cta_label' => 'Shop Lingerie',
                'cta_href'  => 'shop.php?dept=lingerie',
                'cta2_label' => 'Explore Music',
                'cta2_href'  => 'shop.php?dept=instruments',
                'image_url' => '',
            ],
            [
                'title'     => 'Sound For Your Soul.',
                'subtitle'  => 'Master-grade instruments for the discerning artist.',
                'body'      => "Hand-picked guitars, keys and studio gear from the world's most trusted names \u{2014} backed by official warranties and ready to play.",
                'cta_label' => 'Explore Music',
                'cta_href'  => 'shop.php?dept=instruments',
                'cta2_label' => 'Shop Lingerie',
                'cta2_href'  => 'shop.php?dept=lingerie',
                'image_url' => '',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'hero_autoplay' => '6',

        /* sign-in slider (login / register art panel) */
        'auth_slider_slides'   => '[]',
        'auth_slider_autoplay' => '5',

        /* dispatch & shipping methods */
        'shipping_metro_tag'       => 'Fastest',
        'shipping_metro_title'     => 'Accra Express',
        'shipping_metro_desc'      => 'Same-Day / 24 hrs',
        'shipping_metro_fee'       => '0.00',

        'shipping_regional_tag'    => 'Inter-City',
        'shipping_regional_title'  => 'Regional Road',
        'shipping_regional_desc'   => 'Kumasi / Takoradi',
        'shipping_regional_fee'    => '45.00',

        'shipping_pickup_tag'      => 'Self Pick',
        'shipping_pickup_title'    => 'Market Circle & Main Station, Tarkwa',
        'shipping_pickup_desc'     => 'Ready in 2 Hours',
        'shipping_pickup_fee'      => '0.00',

        'free_shipping_threshold'  => '1000.00',

        /* discreet packaging */
        'discreet_packaging_title'   => 'Discreet Packaging Guaranteed (checked by default).',
        'discreet_packaging_desc'    => 'Intimate apparel ships in unmarked, plain luxury charcoal boxes with no reference to lingerie on the courier airway bill.',
        'discreet_packaging_default' => '1',
    ];
}

/** Stored values only (empty when the table has not been written to yet). */
function settings_all(): array
{
    if (isset($GLOBALS['_settings_cache']) && is_array($GLOBALS['_settings_cache'])) {
        return $GLOBALS['_settings_cache'];
    }
    $cache = [];
    try {
        foreach (db_all('SELECT skey, svalue FROM settings') as $r) {
            $cache[(string) $r['skey']] = (string) $r['svalue'];
        }
    } catch (Throwable $e) {
        // table not imported yet - defaults still render the site
    }
    $GLOBALS['_settings_cache'] = $cache;
    return $cache;
}

function setting(string $key, ?string $default = null): string
{
    $stored = settings_all();
    if (array_key_exists($key, $stored)) {
        return $stored[$key];
    }
    $defaults = settings_defaults();
    if ($default !== null) {
        return $default;
    }
    return $defaults[$key] ?? '';
}

/** Upsert a batch of settings. */
function set_settings(array $pairs): void
{
    foreach ($pairs as $key => $value) {
        db_exec(
            'INSERT INTO settings (skey, svalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
            [(string) $key, (string) $value]
        );
    }
    unset($GLOBALS['_settings_cache']);
}

/** Forget stored values so the defaults apply again. */
function delete_settings(array $keys): void
{
    foreach ($keys as $key) {
        db_exec('DELETE FROM settings WHERE skey = ?', [(string) $key]);
    }
    unset($GLOBALS['_settings_cache']);
}

/* --------------------------------------------------------- hero slider */

/** Normalised hero slides: stored JSON, falling back to the shipped defaults. */
function hero_slides(): array
{
    $sources = [
        setting('hero_slides'),
        settings_defaults()['hero_slides'] ?? '',
    ];

    foreach ($sources as $raw) {
        if (!is_string($raw) || $raw === '') {
            continue;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            continue;
        }
        $out = [];
        foreach ($decoded as $s) {
            if (!is_array($s)) {
                continue;
            }
            $slide = [];
            foreach (['title', 'subtitle', 'body', 'cta_label', 'cta_href', 'cta2_label', 'cta2_href', 'image_url'] as $k) {
                $slide[$k] = trim((string) ($s[$k] ?? ''));
            }
            if ($slide['title'] === '' && $slide['body'] === '') {
                continue;
            }
            $out[] = $slide;
            if (count($out) === 8) {
                break;
            }
        }
        if ($out !== []) {
            return $out;
        }
    }

    return [[
        'title'     => SITE_TAGLINE,
        'subtitle'  => '',
        'body'      => '',
        'cta_label' => 'Shop now',
        'cta_href'  => 'shop.php',
        'cta2_label' => '',
        'cta2_href'  => '',
        'image_url' => '',
    ]];
}

/** Seconds between hero slides (0 = no automatic rotation). */
function hero_autoplay_seconds(): int
{
    $v = setting('hero_autoplay');
    if ($v === '') {
        return 6;
    }
    return max(0, min(60, (int) $v));
}

/* --------------------------------------------------- sign-in slider */

/** Catalogue-derived slides for the sign-in panel: department heroes, then featured products. */
function auth_slider_auto_slides(): array
{
    $out = [];
    $push = static function (string $url) use (&$out): void {
        $url = trim($url);
        if ($url === '' || count($out) >= 6) {
            return;
        }
        foreach ($out as $slide) {
            if ($slide['image_url'] === $url) {
                return;
            }
        }
        $out[] = ['image_url' => $url, 'caption' => ''];
    };

    foreach (db_all("SELECT image_url FROM categories WHERE slug IN ('lingerie','instruments') AND image_url <> '' ORDER BY FIELD(slug,'lingerie','instruments')") as $cat) {
        $push((string) $cat['image_url']);
    }
    foreach (db_all("SELECT image_url FROM products WHERE is_active = 1 AND image_url <> '' ORDER BY is_featured DESC, rating DESC, id ASC LIMIT 3") as $prod) {
        $push((string) $prod['image_url']);
    }

    return $out;
}

/** The slides saved in Settings (empty array when the admin has not committed any). */
function auth_slider_stored_slides(): array
{
    $decoded = json_decode(setting('auth_slider_slides'), true);
    if (!is_array($decoded)) {
        return [];
    }

    $out = [];
    foreach ($decoded as $s) {
        if (!is_array($s)) {
            continue;
        }
        $slide = [
            'image_url' => trim((string) ($s['image_url'] ?? '')),
            'caption'   => trim((string) ($s['caption'] ?? '')),
        ];
        if ($slide['image_url'] === '') {
            continue;
        }
        $out[] = $slide;
        if (count($out) === 8) {
            break;
        }
    }

    return $out;
}

/** Normalised sign-in panel slides: saved slides, falling back to the catalogue. */
function auth_slider_slides(): array
{
    $stored = auth_slider_stored_slides();
    return $stored !== [] ? $stored : auth_slider_auto_slides();
}

/** Seconds between sign-in panel slides (0 = no automatic rotation). */
function auth_slider_autoplay_seconds(): int
{
    $v = setting('auth_slider_autoplay');
    if ($v === '') {
        return 5;
    }
    return max(0, min(60, (int) $v));
}

/* ------------------------------------------------------------- theme */

/** Colour presets offered in the admin theme panel. */
function theme_presets(): array
{
    return [
        'crimson'  => ['label' => 'Crimson Noir', 'theme_primary' => '#551022', 'theme_secondary' => '#765a26', 'theme_background' => '#fef8f7', 'theme_surface' => '#fef8f7', 'theme_on_surface' => '#1d1b1b', 'theme_inverse_surface' => '#323030'],
        'blush'    => ['label' => 'Ivory Blush', 'theme_primary' => '#8c2f52', 'theme_secondary' => '#8a6a33', 'theme_background' => '#fffdfb', 'theme_surface' => '#ffffff', 'theme_on_surface' => '#241a1c', 'theme_inverse_surface' => '#2b2224'],
        'midnight' => ['label' => 'Midnight Gold', 'theme_primary' => '#1f2a44', 'theme_secondary' => '#a4791d', 'theme_background' => '#f7f8fb', 'theme_surface' => '#ffffff', 'theme_on_surface' => '#12151c', 'theme_inverse_surface' => '#151a26'],
        'emerald'  => ['label' => 'Emerald Studio', 'theme_primary' => '#14532d', 'theme_secondary' => '#6f5213', 'theme_background' => '#f6faf7', 'theme_surface' => '#ffffff', 'theme_on_surface' => '#12211a', 'theme_inverse_surface' => '#0f1a15'],
    ];
}

/** Typeface catalogue: 'headline' and 'body' families. */
function font_options(): array
{
    return [
        'headline' => [
            'playfair'     => ['label' => 'Playfair Display', 'stack' => "'Playfair Display', Georgia, serif", 'q' => 'family=Playfair+Display:wght@500;600;700'],
            'cormorant'    => ['label' => 'Cormorant Garamond', 'stack' => "'Cormorant Garamond', Georgia, serif", 'q' => 'family=Cormorant+Garamond:wght@500;600;700'],
            'dmserif'      => ['label' => 'DM Serif Display', 'stack' => "'DM Serif Display', Georgia, serif", 'q' => 'family=DM+Serif+Display:ital@0;1'],
            'baskerville'  => ['label' => 'Libre Baskerville', 'stack' => "'Libre Baskerville', Georgia, serif", 'q' => 'family=Libre+Baskerville:wght@400;700'],
            'marcellus'    => ['label' => 'Marcellus', 'stack' => "'Marcellus', Georgia, serif", 'q' => 'family=Marcellus'],
        ],
        'body' => [
            'inter'     => ['label' => 'Inter', 'stack' => "'Inter', system-ui, sans-serif", 'q' => 'family=Inter:wght@400;500;600;700'],
            'manrope'   => ['label' => 'Manrope', 'stack' => "'Manrope', system-ui, sans-serif", 'q' => 'family=Manrope:wght@400;500;600;700'],
            'jakarta'   => ['label' => 'Plus Jakarta Sans', 'stack' => "'Plus Jakarta Sans', system-ui, sans-serif", 'q' => 'family=Plus+Jakarta+Sans:wght@400;500;600;700'],
            'worksans'  => ['label' => 'Work Sans', 'stack' => "'Work Sans', system-ui, sans-serif", 'q' => 'family=Work+Sans:wght@400;500;600;700'],
            'montserrat' => ['label' => 'Montserrat', 'stack' => "'Montserrat', system-ui, sans-serif", 'q' => 'family=Montserrat:wght@400;500;600;700'],
        ],
    ];
}

function font_option(string $kind, string $key): array
{
    $all = font_options();
    return $all[$kind][$key] ?? $all[$kind][array_key_first($all[$kind])];
}

function font_css_url(): string
{
    $parts = [
        font_option('headline', setting('font_headline'))['q'],
        font_option('body', setting('font_body'))['q'],
    ];
    return 'https://fonts.googleapis.com/css2?' . implode('&', array_unique($parts)) . '&display=swap';
}

/* --------------------------------------------------- colour arithmetic */

function theme_hex_ok(string $hex): bool
{
    return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $hex);
}

function theme_hsl(string $hex, ?float $dh = null, ?float $sat = null, ?float $light = null): string
{
    if (!theme_hex_ok($hex)) {
        $hex = '#000000';
    }
    $r = hexdec(substr($hex, 1, 2)) / 255;
    $g = hexdec(substr($hex, 3, 2)) / 255;
    $b = hexdec(substr($hex, 5, 2)) / 255;

    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l   = ($max + $min) / 2;
    $d   = $max - $min;
    $s   = $d == 0.0 ? 0.0 : $d / (1.0 - abs(2.0 * $l - 1.0));
    $h   = 0.0;
    if ($d != 0.0) {
        if ($max === $r) {
            $h = fmod((($g - $b) / $d), 6);
        } elseif ($max === $g) {
            $h = (($b - $r) / $d) + 2;
        } else {
            $h = (($r - $g) / $d) + 4;
        }
        $h *= 60;
        if ($h < 0) {
            $h += 360;
        }
    }

    $h = $h + ($dh ?? 0.0);
    $h = fmod($h, 360);
    if ($h < 0) {
        $h += 360;
    }
    $s = $sat !== null ? max(0.0, min(1.0, $sat)) : $s;
    $l = $light !== null ? max(0.0, min(1.0, $light)) : $l;

    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;
    [$r1, $g1, $b1] = match (true) {
        $h < 60  => [$c, $x, 0.0],
        $h < 120 => [$x, $c, 0.0],
        $h < 180 => [0.0, $c, $x],
        $h < 240 => [0.0, $x, $c],
        $h < 300 => [$x, 0.0, $c],
        default  => [$c, 0.0, $x],
    };
    return sprintf('#%02x%02x%02x',
        (int) round(($r1 + $m) * 255),
        (int) round(($g1 + $m) * 255),
        (int) round(($b1 + $m) * 255));
}

function theme_lightness(string $hex): float
{
    $rgb = array_map(static fn ($v) => hexdec($v) / 255, [substr($hex, 1, 2), substr($hex, 3, 2), substr($hex, 5, 2)]);
    return (max($rgb) + min($rgb)) / 2;
}

function theme_sat(string $hex): float
{
    $rgb = array_map(static fn ($v) => hexdec($v) / 255, [substr($hex, 1, 2), substr($hex, 3, 2), substr($hex, 5, 2)]);
    $max = max($rgb);
    $min = min($rgb);
    $l   = ($max + $min) / 2;
    if ($max === $min) {
        return 0.0;
    }
    return $l > 0.5 ? ($max - $min) / (2 - $max - $min) : ($max - $min) / ($max + $min);
}

function theme_mix(string $a, string $b, float $t): string
{
    $out = '#';
    foreach ([1, 3, 5] as $i) {
        $x = hexdec(substr($a, $i, 2)) * (1 - $t) + hexdec(substr($b, $i, 2)) * $t;
        $out .= sprintf('%02x', (int) round($x));
    }
    return $out;
}

/**
 * Full Tailwind colour map derived from the six admin-editable colours.
 *
 * @return array<string,string>
 */
function theme_colors(): array
{
    $d = settings_defaults();
    $get = static function (string $key) use ($d): string {
        $v = setting($key);
        return theme_hex_ok($v) ? $v : $d[$key];
    };

    $P  = $get('theme_primary');
    $S  = $get('theme_secondary');
    $BG = $get('theme_background');
    $SU = $get('theme_surface');
    $ON = $get('theme_on_surface');
    $IV = $get('theme_inverse_surface');

    // text colour to sit on a given background
    $on = static fn (string $hex): string => theme_lightness($hex) > 0.55 ? '#1d1b1b' : '#ffffff';
    $L  = static fn (string $hex): float => theme_lightness($hex);
    $SA = static fn (string $hex): float => theme_sat($hex);
    $t  = theme_hsl($P, 8.0);            // tertiary sits just beside the primary hue

    // Surface tints move in RGB so a near-white background stays neutral
    // (HSL treats "white-ish" as fully saturated, which tints cards pink).
    $isLight = $L($SU) > 0.5;
    $deep  = static fn (float $k): string => $isLight ? theme_mix($SU, '#000000', $k) : theme_mix($SU, '#ffffff', $k);
    $rise  = static fn (float $k): string => $isLight ? theme_mix($SU, '#ffffff', $k) : theme_mix($SU, '#000000', $k);
    $level = $isLight
        ? ['lowest' => $rise(0.9), 'low' => $deep(0.025), 'container' => $deep(0.05), 'high' => $deep(0.075), 'highest' => $deep(0.10), 'dim' => $deep(0.07)]
        : ['lowest' => $deep(0.12), 'low' => $deep(0.08), 'container' => $rise(0.04), 'high' => $rise(0.08), 'highest' => $rise(0.12), 'dim' => $deep(0.05)];

    return [
        'primary'                     => $P,
        'on-primary'                  => $on($P),
        'primary-container'           => theme_hsl($P, null, max(0.2, $SA($P) * 0.72), min(0.62, $L($P) + 0.10)),
        'on-primary-container'        => theme_hsl($P, null, min(1.0, max(0.6, $SA($P) * 1.3)), 0.76),
        'primary-fixed'               => theme_hsl($P, null, null, 0.90),
        'primary-fixed-dim'           => theme_hsl($P, null, null, 0.82),
        'inverse-primary'             => theme_hsl($P, null, null, 0.80),
        'surface-tint'                => $P,

        'secondary'                   => $S,
        'on-secondary'                => $on($S),
        'secondary-container'         => theme_hsl($S, null, 1.0, 0.80),
        'on-secondary-container'      => theme_hsl($S, null, null, 0.32),
        'secondary-fixed'             => theme_hsl($S, null, 1.0, 0.86),
        'secondary-fixed-dim'         => theme_hsl($S, null, 0.7, 0.71),
        'on-secondary-fixed'          => theme_hsl($S, null, null, 0.12),
        'on-secondary-fixed-variant'  => theme_hsl($S, null, null, 0.28),

        'tertiary'                    => $t,
        'on-tertiary'                 => $on($t),
        'tertiary-container'          => theme_hsl($t, null, null, 0.34),
        'on-tertiary-container'       => theme_hsl($t, null, null, 0.74),
        'tertiary-fixed'              => theme_hsl($t, null, null, 0.86),
        'tertiary-fixed-dim'          => theme_hsl($t, null, null, 0.74),
        'on-tertiary-fixed'           => theme_hsl($t, null, null, 0.15),
        'on-tertiary-fixed-variant'   => theme_hsl($t, null, null, 0.33),

        'background'                  => $BG,
        'on-background'               => $ON,
        'surface'                     => $SU,
        'surface-bright'              => $SU,
        'surface-dim'                 => $level['dim'],
        'surface-container-lowest'    => $level['lowest'],
        'surface-container-low'       => $level['low'],
        'surface-container'           => $level['container'],
        'surface-container-high'      => $level['high'],
        'surface-container-highest'   => $level['highest'],
        'surface-variant'             => $level['container'],
        'on-surface'                  => $ON,
        'on-surface-variant'          => theme_hsl($ON, null, null, min(0.62, $L($ON) + 0.18)),
        'outline'                     => theme_mix($ON, $SU, 0.45),
        'outline-variant'             => theme_mix($ON, $SU, 0.82),

        'inverse-surface'             => $IV,
        'inverse-on-surface'          => theme_hsl($IV, null, null, 0.94),
    ];
}

/** The shipped assets/tailwind-config.js decoded to an array (null if unreadable). */
function shipped_config(): ?array
{
    $raw = (string) @file_get_contents(__DIR__ . '/../assets/tailwind-config.js');
    if ($raw === '') {
        return null;
    }
    $eq   = strpos($raw, '=');
    $json = $eq === false ? $raw : rtrim(trim(substr($raw, $eq + 1)), "; \r\n");
    // quote the bare JS keys so the object becomes valid JSON
    $json = preg_replace('/([{,]\s*)([A-Za-z_$][A-Za-z0-9_$]*)\s*:/', '$1"$2":', $json) ?? $json;
    $cfg  = json_decode($json, true);
    return is_array($cfg) ? $cfg : null;
}

/** Tailwind fontFamily overrides for the selected typefaces. */
function theme_font_family(): array
{
    $headline = font_option('headline', setting('font_headline'))['stack'];
    $body     = font_option('body', setting('font_body'))['stack'];

    $cfg  = shipped_config();
    $keys = array_keys($cfg['theme']['extend']['fontFamily'] ?? []);
    if ($keys === []) {
        $keys = ['headline-lg', 'headline-md', 'headline-sm', 'body-lg', 'body-md', 'body-sm', 'label-nav', 'label-tag', 'label-price'];
    }
    $out = [];
    foreach ($keys as $key) {
        $key = (string) $key;
        if (str_starts_with($key, 'headline') || str_starts_with($key, 'display') || str_starts_with($key, 'title')) {
            $out[$key] = [$headline];
        } else {
            $out[$key] = [$body];
        }
    }
    $out['brand']   = [$headline];
    $out['numeric'] = [$body];
    return $out;
}

/**
 * The Tailwind config the page loads: shipped defaults with the live theme
 * swapped in. Falls back to a patch script if the shipped file changes shape.
 */
function tailwind_config_js(): string
{
    static $js = null;
    if ($js !== null) {
        return $js;
    }
    $cfg = shipped_config();
    if ($cfg !== null) {
        $cfg['theme']['extend']['colors']     = array_merge($cfg['theme']['extend']['colors'] ?? [], theme_colors());
        $cfg['theme']['extend']['fontFamily'] = array_merge($cfg['theme']['extend']['fontFamily'] ?? [], theme_font_family());
        $js = 'tailwind.config=' . json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';';
        return $js;
    }

    // shipped config was not parseable - keep it and patch the pieces we control
    $raw = (string) @file_get_contents(__DIR__ . '/../assets/tailwind-config.js');
    $js  = $raw . "\n"
        . 'tailwind.config.theme.extend.colors=Object.assign(tailwind.config.theme.extend.colors||{},'
        . json_encode(theme_colors(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');'
        . 'tailwind.config.theme.extend.fontFamily=Object.assign(tailwind.config.theme.extend.fontFamily||{},'
        . json_encode(theme_font_family(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');';
    return $js;
}

/* -------------------------------------------------- card arrangement */

/** Grid column classes for every product listing. */
function product_grid_classes(): string
{
    return match ((int) setting('grid_columns')) {
        2       => 'grid-cols-2',
        3       => 'grid-cols-2 xl:grid-cols-3',
        default => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4',
    };
}

/** Product image aspect ratio on cards (identical for every department). */
function card_ratio_classes(?string $department = null): string
{
    unset($department);
    return match (setting('card_ratio')) {
        'portrait'  => 'aspect-[3/4]',
        'square'    => 'aspect-square',
        'landscape' => 'aspect-[4/3]',
        default     => 'aspect-[4/5]',
    };
}

/** How many featured products the homepage shows. */
function home_featured_count(): int
{
    return max(4, min(12, (int) setting('home_featured')));
}

/* ------------------------------------------------ contact + location */

function contact_phone_display(): string
{
    return setting('contact_phone');
}

function contact_phone_href(): string
{
    return 'tel:' . preg_replace('/[^0-9+]/', '', setting('contact_phone'));
}

function whatsapp_url(string $message = ''): string
{
    $num  = preg_replace('/\D/', '', setting('contact_whatsapp'));
    $href = 'https://wa.me/' . $num;
    if ($message !== '') {
        $href .= '?text=' . rawurlencode($message);
    }
    return $href;
}

/**
 * WhatsApp enquiry deep link for a product: includes name, price,
 * the product image and the product page URL in the prefilled message.
 *
 * @param array $p product row (name, price, slug, image_url)
 */
function product_whatsapp_url(array $p): string
{
    $img = absolute_image_url((string) ($p['image_url'] ?? ''));
    $url = absolute_url('product.php?slug=' . urlencode((string) ($p['slug'] ?? '')));
    $msg = "Hi Zion Groups, I'd like to enquire about:\n\n"
        . (string) ($p['name'] ?? '')
        . ' - ' . price((float) ($p['price'] ?? 0))
        . ($img !== '' ? "\nImage: " . $img : '')
        . "\nProduct: " . $url;
    return whatsapp_url($msg);
}

function map_embed_url(): string
{
    $lat = (float) setting('map_lat');
    $lng = (float) setting('map_lng');
    $d   = 0.010;
    $bbox = sprintf('%.6f,%.6f,%.6f,%.6f', $lng - $d, $lat - $d * 0.8, $lng + $d, $lat + $d * 0.8);
    return 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox . '&layer=mapnik&marker=' . $lat . ',' . $lng;
}

function map_directions_url(): string
{
    $lat = (float) setting('map_lat');
    $lng = (float) setting('map_lng');
    return 'https://www.google.com/maps/dir/?api=1&destination=' . $lat . ',' . $lng;
}

function address_block(): string
{
    return implode(', ', array_filter([
        setting('address_line'),
        setting('address_city'),
        setting('address_country'),
    ]));
}

/** Short pickup/showroom name for chips, e.g. "Airport Residential Area". */
function showroom_label(): string
{
    $line = trim(setting('address_line'));
    $head = trim((string) strstr($line, ',', true));
    if ($head !== '' && mb_strlen($head) <= 24) {
        return $head;
    }
    if ($line !== '' && mb_strlen($line) <= 24) {
        return $line;
    }
    return 'Showroom';
}
