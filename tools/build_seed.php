<?php
/**
 * Builds seed.sql directly from the Stitch mockup HTML so that the long
 * Google-hosted image URLs are never hand-copied.
 *
 *   php tools/build_seed.php
 */

declare(strict_types=1);

$ROOT = dirname(__DIR__);
$MOCK = dirname($ROOT) . '/stitch_velora_e_commerce_platform';

/** Returns the <main>...</main> body block of a mockup screen. */
function screenHtml(string $name): string
{
    $raw = file_get_contents(dirname(__DIR__) . '/../stitch_velora_e_commerce_platform/' . $name . '/code.html');
    if (preg_match('#(?s)</header>(.*?)<footer#', $raw, $m)) {
        return $m[1];
    }
    return $raw;
}

// ---------------------------------------------------------------- helpers
function articleImages(string $html): array
{
    preg_match_all('/<article.*?<\/article>/s', $html, $m);
    $out = [];
    foreach ($m[0] as $card) {
        if (preg_match('/src="(https:\/\/lh3[^"]+)"/', $card, $i)) {
            $out[] = $i[1];
        }
    }
    return $out;
}

function imagesFrom(string $html): array
{
    preg_match_all('/src="(https:\/\/lh3[^"]+)"/', $html, $m);
    return $m[1];
}

$lingerieHtml = screenHtml('lingerie_category');
$instrHtml    = screenHtml('musical_instruments_category');
$homeHtml     = screenHtml('homepage');
$pdpLHtml     = screenHtml('product_detail_lingerie');
$cartHtml     = screenHtml('shopping_cart');

$lingerieImgs = articleImages($lingerieHtml);
$instrImgs    = articleImages($instrHtml);
$homeImgs     = imagesFrom($homeHtml);
$pdpImgs      = imagesFrom($pdpLHtml);
$cartImgs     = imagesFrom($cartHtml);

$C = static fn(string $s): string => "'" . str_replace("'", "''", $s) . "'";
$N = static fn(?string $s): string => ($s === null || $s === '') ? 'NULL' : "'" . str_replace("'", "''", $s) . "'";
$M = static fn(float $v): string => number_format($v, 2, '.', '');

// ---------------------------------------------------------------- categories
$categories = [
    // slug, name, parent slug, department, description, image
    ['lingerie',        'Lingerie',                  null,        'lingerie',    'Sensual high-fashion intimates, hand-finished in Accra.',        $homeImgs[0]],
    ['sets-intimates',  'Sets & Intimates',          'lingerie',  'lingerie',    'Matching sets, babydolls, bustiers and statement intimates.',    $lingerieImgs[0]],
    ['bras',            'Bras & Bralettes',          'lingerie',  'lingerie',    'Balconette, scalloped and wireless silhouettes.',                $lingerieImgs[1]],
    ['bodysuits',       'Bodysuits & Shapewear',     'lingerie',  'lingerie',    'Sculpting lace bodysuits and second-skin fits.',                 $lingerieImgs[2]],
    ['sleepwear',       'Sleepwear & Robes',         'lingerie',  'lingerie',    'Silk chemises, nightdresses and robes.',                         $homeImgs[9]],
    ['instruments',     'Musical Instruments',       null,        'instruments', 'Professional keyboards, guitars, drums and studio audio gear.',  $homeImgs[1]],
    ['keyboards',       'Keyboards & Synths',        'instruments','instruments','Workstations, synthesizers and stage pianos.',                   $homeImgs[2]],
    ['guitars',         'Guitars',                   'instruments','instruments','Electric, acoustic and bass guitars.',                           $homeImgs[1]],
    ['drums',           'Drums & Percussion',        'instruments','instruments','Acoustic kits, cymbals and electronic percussion.',             $homeImgs[3]],
    ['microphones',     'Microphones',               'instruments','instruments','Dynamic, condenser and broadcast microphones.',                 $homeImgs[4]],
    ['audio-gear',      'Audio & Gear',              'instruments','instruments','Headphones, interfaces, stands and accessories.',               $homeImgs[5]],
];

// ---------------------------------------------------------------- products
// name => meta
$lingerieMeta = [
    'Lace Bra Set'                => ['sets-intimates', 4.8, 124, 280.00, null, 'Best Seller',      'Accra Stock',          18, 'S, M, L, XL'],
    'Satin Balconette Bra'        => ['bras',           4.6,  93, 180.00, null, null,               'Accra Stock',          24, '32B - 38D'],
    'Sculpting Lace Bodysuit'     => ['bodysuits',      4.7,  76, 320.00, null, null,               'Accra Stock',          11, 'XS to XXL'],
    'Babydoll Slip & G-String Set'=> ['sets-intimates', 4.6,  62, 250.00, null, 'New',              'New This Week',        15, 'S, M, L'],
    'Seamless Thong (3-Pack)'     => ['sets-intimates', 4.5,  48, 120.00, 150.00, 'Sale',           'Accra Stock',          32, 'XS - XL'],
    'Silk Robe & Chemise Set'     => ['sleepwear',      4.7,  35, 210.00, null, null,               'Accra Stock',           9, 'Free Size'],
    'French Scalloped Bralette'   => ['bras',           4.9,  51, 165.00, null, null,               'Accra Stock',          21, 'XS, S, M, L'],
    'Velvet Touch Bustier'        => ['sets-intimates', 5.0,  29, 340.00, null, 'Limited Atelier',  'Made to Order - 5 Days', 4, 'S, M, L'],
];

$instrMeta = [
    'Yamaha PSR-SX900 61-Key Workstation'       => ['keyboards', 4.9, 38, 3500.00, null, 'Flagship Arranger',   'In Stock Accra',           6,
        ['61 Keys', '1,337 Voices', '7" Touchscreen', 'Chord Looper']],
    'Roland Juno-DS 61 Synthesizer'             => ['keyboards', 4.8, 24, 4200.00, null, 'Pro Stage Synth',     'In Stock Accra',           5,
        ['61 Velocity Keys', 'USB Audio/MIDI', 'Battery / AC', 'Mic Input w/ FX']],
    'Yamaha Pacifica 112V Electric Guitar'      => ['guitars',   4.7, 86, 4500.00, null, 'Yamaha Authorized',  'Showroom Demo Ready',      8,
        ['Solid Alder Body', 'Alnico V Pickups', 'Vintage Tremolo', 'Rosewood Fretboard']],
    'Roland FA-08 88-Key Music Workstation'     => ['keyboards', 5.0, 14, 6800.00, null, '88 Hammer Weighted', 'Heavy-Duty Courier Only',  3,
        ['88 Ivory-Feel Keys', '16-Track Sequencer', 'SuperNATURAL Engine', 'Sampler']],
    'Korg Minilogue XD Polyphonic Synthesizer'  => ['keyboards', 4.9, 42, 3100.00, null, 'Analog Hybrid',      'In Stock Accra',           7,
        ['4-Voice Analog', 'Multi-Engine', '16-Step Motion', 'Stereo DSP FX']],
    'Nord Stage 3 88-Key Stage Keyboard'        => ['keyboards', 5.0,  9, 14500.00, null, 'Swedish Mastercraft','Official Importer',       2,
        ['88 Hammer Action', 'Dual OLED Screens', 'Nord Lead A1 Synth', '2GB Piano Library']],
];

$brands = [];

$products = [];
$pid = 0;

$addProduct = static function (array $p) use (&$products, &$pid, &$brands): void {
    $pid++;
    $p['id'] = $pid;
    $products[] = $p;
    $brands[$p['brand']] = true;
};

// --- lingerie category cards
$i = 0;
foreach ($lingerieMeta as $name => $m) {
    [$cat, $rating, $revs, $price, $compare, $badge, $stockLabel, $stock, $sizes] = $m;
    $addProduct([
        'sku'       => 'LIN-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
        'slug'      => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-')),
        'cat'       => $cat,
        'dept'      => 'lingerie',
        'name'      => $name,
        'brand'     => [
            'Lace Bra Set' => 'Zion Atelier', 'Satin Balconette Bra' => 'Zion Atelier',
            'Sculpting Lace Bodysuit' => 'Oh La La', 'Babydoll Slip & G-String Set' => 'Zion Atelier',
            'Seamless Thong (3-Pack)' => 'Zion Essentials', 'Silk Robe & Chemise Set' => 'Zion Atelier',
            'French Scalloped Bralette' => 'Oh La La', 'Velvet Touch Bustier' => 'Zion Couture',
        ][$name],
        'short'     => 'Hand-finished luxury intimates from the Zion atelier, dispatched in unmarked packaging.',
        'desc'      => 'Crafted in small runs for the Zion boutique, this piece pairs delicate European lace with a supportive, considered fit. Every intimate order ships in a plain unmarked carton — couriers are blind to the contents anywhere in Ghana.',
        'price'     => $price,
        'compare'   => $compare,
        'stock'     => $stock,
        'featured'  => in_array($name, ['Lace Bra Set'], true) ? 1 : 0,
        'badge'     => $badge,
        'stockLabel'=> $stockLabel,
        'rating'    => $rating,
        'reviews'   => $revs,
        'image'     => $lingerieImgs[$i],
        'sizes'     => array_map('trim', explode(',', str_replace('to', ',', $sizes))),
        'colors'    => [],
        'specs'     => [],
    ]);
    $i++;
}

// --- lingerie colour swatches (taken from the card markup)
$swatches = [
    'Lace Bra Set'                => [['Burgundy', '#722737'], ['Noir', '#323030'], ['Rose', '#ffd9de']],
    'Satin Balconette Bra'        => [['Champagne', '#fed798'], ['Noir', '#323030']],
    'Sculpting Lace Bodysuit'     => [['Wine', '#551022'], ['Noir', '#323030']],
    'Babydoll Slip & G-String Set'=> [['Rose Blush', '#f9d8dd'], ['Ivory', '#f9f2f1']],
    'Seamless Thong (3-Pack)'     => [['Noir', '#323030'], ['Rose', '#ffd9de'], ['Ivory', '#f9f2f1']],
    'Silk Robe & Chemise Set'     => [['Champagne', '#fed798'], ['Wine', '#551022']],
    'French Scalloped Bralette'   => [['Rose Blush', '#f9d8dd'], ['Wine', '#551022']],
    'Velvet Touch Bustier'        => [['Wine', '#551022'], ['Noir', '#323030']],
];
foreach ($products as &$p) {
    if (isset($swatches[$p['name']])) {
        $p['colors'] = $swatches[$p['name']];
    }
}
unset($p);

// --- instrument category cards
$i = 0;
foreach ($instrMeta as $name => $m) {
    [$cat, $rating, $revs, $price, $compare, $badge, $stockLabel, $stock, $specs] = $m;
    $addProduct([
        'sku'       => 'INS-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
        'slug'      => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-')),
        'cat'       => $cat,
        'dept'      => 'instruments',
        'name'      => $name,
        'brand'     => [
            'Yamaha PSR-SX900 61-Key Workstation' => 'Yamaha Pro Sound',
            'Roland Juno-DS 61 Synthesizer'       => 'Roland Corporation',
            'Yamaha Pacifica 112V Electric Guitar'=> 'Yamaha Guitars',
            'Roland FA-08 88-Key Music Workstation' => 'Roland Studio',
            'Korg Minilogue XD Polyphonic Synthesizer' => 'Korg Japan',
            'Nord Stage 3 88-Key Stage Keyboard'  => 'Clavia Nord',
        ][$name],
        'short'     => 'Officially imported, warranty-backed professional gear, held in the Accra Central warehouse.',
        'desc'      => 'Supplied through Zion\'s authorised distribution channel with full manufacturer warranty coverage. Includes free unboxing and setup assistance within Greater Accra and Tema, plus nationwide insured courier delivery.',
        'price'     => $price,
        'compare'   => $compare,
        'stock'     => $stock,
        'featured'  => in_array($name, ['Yamaha Pacifica 112V Electric Guitar'], true) ? 1 : 0,
        'badge'     => $badge,
        'stockLabel'=> $stockLabel,
        'rating'    => $rating,
        'reviews'   => $revs,
        'image'     => $instrImgs[$i],
        'sizes'     => [],
        'colors'    => [],
        'specs'     => $specs,
    ]);
    $i++;
}

// --- extra products that appear on the homepage / cart / PDP but not in a grid
$addProduct([
    'sku' => 'LIN-009', 'slug' => 'lace-balconette-set', 'cat' => 'bras', 'dept' => 'lingerie',
    'name' => 'Lace Balconette Set', 'brand' => 'Zion Atelier',
    'short' => 'Signature scalloped eyelash lace balconette set with sculpted underwire architecture.',
    'desc' => "This elegant lace balconette set combines comfort with sophistication. The delicate scalloped lace design and supportive underwire fit make it perfect for everyday luxury or special moments.\n\n- Soft, non-scratch French scalloped eyelash lace cups\n- Sculpted underwire architecture for gentle forward lift\n- Customizable fit with double-row hook and eye closure\n- Includes matching mid-rise French lace bikini brief\n\nComposition: 88% Polyamide, 12% Elastane. Gusset lining: 100% breathable organic cotton. Hand wash lukewarm with gentle silk detergent; do not wring or tumble dry; dry flat away from direct sunlight.",
    'price' => 280.00, 'compare' => 340.00, 'stock' => 14, 'featured' => 0,
    'badge' => 'Atelier Exclusive', 'stockLabel' => 'Ghana In-Stock',
    'rating' => 5.0, 'reviews' => 124, 'image' => $pdpImgs[0],
    'sizes' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
    'colors' => [['Burgundy / Wine', '#722737'], ['Obsidian Black', '#171515'], ['Rose Blush', '#f9d8dd'], ['Champagne Nude', '#fed798']],
    'specs' => [
        ['Lace Composition', '88% Polyamide, 12% Elastane'],
        ['Gusset Lining', '100% Breathable Organic Cotton'],
        ['Closure', 'Double-row hook and eye'],
        ['Brief', 'Matching mid-rise French lace bikini'],
    ],
    'gallery' => array_slice($pdpImgs, 0, 5),
]);

$addProduct([
    'sku' => 'LIN-010', 'slug' => 'satin-nightdress', 'cat' => 'sleepwear', 'dept' => 'lingerie',
    'name' => 'Satin Nightdress', 'brand' => 'Zion Sleep',
    'short' => 'Pure silk-touch satin nightdress with adjustable spaghetti straps.',
    'desc' => 'Rose blush silk satin with a fluid drape and adjustable spaghetti straps. Photographed in a softly lit bedroom setting; designed for warm Accra nights and slow mornings alike.',
    'price' => 220.00, 'compare' => null, 'stock' => 17, 'featured' => 1,
    'badge' => 'New Arrival', 'stockLabel' => 'Pure Silk Touch',
    'rating' => 4.6, 'reviews' => 71, 'image' => $homeImgs[9],
    'sizes' => ['XS', 'S', 'M', 'L'],
    'colors' => [['Rose Blush', '#e8a3af'], ['Ivory', '#f4ebe1'], ['Noir', '#202020']],
    'specs' => [],
]);

$addProduct([
    'sku' => 'LIN-011', 'slug' => 'lace-bodysuit', 'cat' => 'bodysuits', 'dept' => 'lingerie',
    'name' => 'Lace Bodysuit', 'brand' => 'Zion Private Atelier',
    'short' => 'Full-coverage sculpting lace bodysuit in Burgundy Noir.',
    'desc' => 'A sculpting lace bodysuit with a smooth, second-skin finish. Shipped in a plain unmarked luxury kraft carton as standard on every Zion intimate order.',
    'price' => 250.00, 'compare' => 300.00, 'stock' => 12, 'featured' => 0,
    'badge' => 'Sale', 'stockLabel' => 'In Stock in Accra',
    'rating' => 4.7, 'reviews' => 58, 'image' => $cartImgs[0],
    'sizes' => ['S', 'M', 'L', 'XL'],
    'colors' => [['Burgundy Noir', '#551022']],
    'specs' => [],
]);

$addProduct([
    'sku' => 'INS-007', 'slug' => 'roland-synthesizer', 'cat' => 'keyboards', 'dept' => 'instruments',
    'name' => 'Roland Synthesizer', 'brand' => 'Roland Studio',
    'short' => '61 velocity-sensitive keys with USB-MIDI and a direct-import warranty.',
    'desc' => 'A versatile performance synthesizer for stage and studio, imported directly and covered by Roland regional warranty support.',
    'price' => 3000.00, 'compare' => null, 'stock' => 5, 'featured' => 1,
    'badge' => 'Pro Studio', 'stockLabel' => 'Direct Import',
    'rating' => 4.9, 'reviews' => 53, 'image' => $homeImgs[8],
    'sizes' => [], 'colors' => [],
    'specs' => [['Keys', '61 Velocity Keys'], ['Connectivity', 'USB-MIDI'], ['Warranty', '2-Year Regional']],
]);

$addProduct([
    'sku' => 'INS-008', 'slug' => 'shure-sm58-dynamic-vocal-microphone', 'cat' => 'microphones', 'dept' => 'instruments',
    'name' => 'Shure SM58 Dynamic Vocal Microphone', 'brand' => 'Shure Audio Professional',
    'short' => 'The industry-standard dynamic vocal microphone, bundled with a 5m XLR cable.',
    'desc' => 'Studio Edition bundle: Shure SM58 plus a high-purity 5m XLR cable. Cardioid dynamic capsule, hardened steel grille, and a lifetime of tour-proven reliability. Express same-day dispatch from Accra.',
    'price' => 1200.00, 'compare' => null, 'stock' => 20, 'featured' => 0,
    'badge' => 'Certified Authentic', 'stockLabel' => 'Express Same-Day Dispatch',
    'rating' => 4.9, 'reviews' => 64, 'image' => $cartImgs[2],
    'sizes' => [], 'colors' => [],
    'specs' => [['Type', 'Dynamic Cardioid'], ['Bundle', 'Studio Edition'], ['Cable', '5m High-Purity XLR']],
]);

// ---------------------------------------------------------------- bundling
$bundles = [
    'yamaha-psr-sx900-61-key-workstation' => [
        'Yamaha Heavy-Duty Stand', 'Double-X reinforced', 350.00, 1,
        'FC4A Piano Sustain Pedal', 'Realistic continuous feel', 220.00, 2,
        'Padded 61-Key Gig Bag', 'Water-repellent nylon', 280.00, 3,
    ],
];

// ---------------------------------------------------------------- emit SQL
// CAREFUL: seed.sql has been hand-maintained since it was first generated
// (the reviews INSERT at the end only exists there, plus copy edits).
// Regenerating overwrites those changes - edit seed.sql directly instead,
// and treat this script as the original generator, not the source of truth.
$sql = [];
$sql[] = '-- ============================================================================
-- Zion Groups of Companies - seed data (generated from the Stitch mockups)
-- Import:  mysql -u USER -p DB_NAME < seed.sql
-- phpMyAdmin: click the target database first, then Import this file.
-- ============================================================================';

$sql[] = 'SET NAMES utf8mb4;';
$sql[] = '';

$sql[] = '-- categories';
$sql[] = 'INSERT INTO `categories` (`id`,`parent_id`,`slug`,`name`,`department`,`description`,`image_url`,`sort_order`) VALUES';
$rows = [];
$ids = [];
foreach ($categories as $idx => [$slug, $name, $parent, $dept, $desc, $img]) {
    $id = $idx + 1;
    $ids[$slug] = $id;
}
foreach ($categories as $idx => [$slug, $name, $parent, $dept, $desc, $img]) {
    $id = $idx + 1;
    $rows[] = sprintf('(%d,%s,%s,%s,%s,%s,%s,%d)',
        $id,
        $parent === null ? 'NULL' : (string) $ids[$parent],
        $C($slug), $C($name), $C($dept), $C($desc), $N($img), $idx
    );
}
$sql[] = implode(",\n", $rows) . ';';
$sql[] = '';

$sql[] = '-- brands';
$sql[] = 'INSERT INTO `brands` (`id`,`slug`,`name`) VALUES';
$brandIds = [];
$brows = [];
$bid = 0;
foreach (array_keys($brands) as $b) {
    $bid++;
    $brandIds[$b] = $bid;
    $brows[] = sprintf('(%d,%s,%s)', $bid, $C(strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $b), '-'))), $C($b));
}
$sql[] = implode(",\n", $brows) . ';';
$sql[] = '';

$sql[] = '-- products';
$sql[] = 'INSERT INTO `products` (`id`,`sku`,`slug`,`category_id`,`brand_id`,`department`,`name`,`brand_label`,`short_description`,`description`,`price`,`compare_at_price`,`stock`,`is_active`,`is_featured`,`badge`,`stock_label`,`rating`,`review_count`,`image_url`) VALUES';
$prows = [];
foreach ($products as $p) {
    $prows[] = sprintf('(%d,%s,%s,%d,%d,%s,%s,%s,%s,%s,%s,%s,%d,1,%d,%s,%s,%s,%d,%s)',
        $p['id'], $C($p['sku']), $C($p['slug']),
        $ids[$p['cat']], $brandIds[$p['brand']],
        $C($p['dept']), $C($p['name']), $C($p['brand']),
        $C($p['short']), $C($p['desc']),
        $M($p['price']),
        $p['compare'] === null ? 'NULL' : $M($p['compare']),
        $p['stock'], $p['featured'],
        $N($p['badge']), $C($p['stockLabel']),
        number_format($p['rating'], 1, '.', ''), $p['reviews'],
        $C($p['image'])
    );
}
$sql[] = implode(",\n", $prows) . ';';
$sql[] = '';

$imgRows = [];
$varRows = [];
$specRows = [];
$bundleRows = [];
$im = 0; $vm = 0; $sm = 0; $bm = 0;

foreach ($products as $p) {
    $gallery = $p['gallery'] ?? [$p['image']];
    foreach ($gallery as $u) {
        $im++;
        $imgRows[] = sprintf('(%d,%d,%s,%s,%d)', $im, $p['id'], $C($u), $N($p['name']), $im);
    }
    foreach ($p['sizes'] as $k => $s) {
        $s = trim($s);
        if ($s === '') { continue; }
        $vm++;
        $varRows[] = sprintf('(%d,%d,%s,%s,NULL,%d,%d)', $vm, $p['id'], $C('size'), $C($s), max(3, (int) ceil($p['stock'] / 2)), $k);
    }
    foreach ($p['colors'] as $k => [$label, $hex]) {
        $vm++;
        $varRows[] = sprintf('(%d,%d,%s,%s,%s,%d,%d)', $vm, $p['id'], $C('color'), $C($label), $C($hex), max(3, (int) ceil($p['stock'] / 2)), $k);
    }
    foreach ($p['specs'] as $k => $spec) {
        $sm++;
        if (isset($spec[1])) {
            $specRows[] = sprintf('(%d,%d,%s,%s,%d)', $sm, $p['id'], $C($spec[0]), $C($spec[1]), $k);
        } else {
            $specRows[] = sprintf('(%d,%d,%s,%s,%d)', $sm, $p['id'], $C('Feature'), $C($spec), $k);
        }
    }
    if (isset($bundles[$p['slug']])) {
        $b = $bundles[$p['slug']];
        for ($k = 0; $k < count($b); $k += 4) {
            $bm++;
            $bundleRows[] = sprintf('(%d,%d,%s,%s,%s,%d)', $bm, $p['id'], $C($b[$k]), $C($b[$k + 1]), $M((float) $b[$k + 2]), (int) $b[$k + 3]);
        }
    }
}

if ($imgRows) {
    $sql[] = '-- product_images';
    $sql[] = 'INSERT INTO `product_images` (`id`,`product_id`,`url`,`alt`,`sort_order`) VALUES';
    $sql[] = implode(",\n", $imgRows) . ';';
    $sql[] = '';
}
if ($varRows) {
    $sql[] = '-- product_variants';
    $sql[] = 'INSERT INTO `product_variants` (`id`,`product_id`,`type`,`value`,`hex`,`stock`,`sort_order`) VALUES';
    $sql[] = implode(",\n", $varRows) . ';';
    $sql[] = '';
}
if ($specRows) {
    $sql[] = '-- product_specs';
    $sql[] = 'INSERT INTO `product_specs` (`id`,`product_id`,`label`,`value`,`sort_order`) VALUES';
    $sql[] = implode(",\n", $specRows) . ';';
    $sql[] = '';
}
if ($bundleRows) {
    $sql[] = '-- product_bundles';
    $sql[] = 'INSERT INTO `product_bundles` (`id`,`product_id`,`item_name`,`item_desc`,`item_price`,`sort_order`) VALUES';
    $sql[] = implode(",\n", $bundleRows) . ';';
    $sql[] = '';
}

// ------------------------------------------------------------- demo account
$adminHash = password_hash('Admin123!', PASSWORD_DEFAULT);
$demoHash  = password_hash('Customer123!', PASSWORD_DEFAULT);

$sql[] = '-- users (passwords: Admin123! / Customer123!)';
$sql[] = "INSERT INTO `users` (`id`,`name`,`email`,`phone`,`password_hash`,`role`,`momo_verified`,`is_active`) VALUES";
$sql[] = "(1,'Zion Admin','admin@ziongroups.com.gh','+233 50 123 4567','{$adminHash}','admin',1,1),";
$sql[] = "(2,'Kwame Mensah','kwame.mensah@ziongroups.com.gh','+233 24 492 8812','{$demoHash}','customer',1,1);";
$sql[] = '';

$sql[] = '-- addresses';
$sql[] = 'INSERT INTO `addresses` (`id`,`user_id`,`recipient`,`phone`,`region`,`city`,`street`,`is_default`) VALUES';
$sql[] = "(1,2,'Kwame Mensah','+233 24 492 8812','Greater Accra Region','East Legon','No. 14 Boundary Road, Behind Mensvic Grand Hotel, East Legon',1),";
$sql[] = "(2,2,'Kwame Mensah','+233 24 492 8812','Ashanti Region','Kumasi','12 Ahodwo Road, Nhyiaeso',0);";
$sql[] = '';

$sql[] = '-- promo codes';
$sql[] = 'INSERT INTO `promo_codes` (`id`,`code`,`description`,`type`,`value`,`is_active`) VALUES';
$sql[] = "(1,'ZION GROUPS10','10% welcome privilege for the Zion Club','percent',10.00,1),";
$sql[] = "(2,'VIP50','GH₵ 50 off orders above GH₵ 1,000','fixed',50.00,1);";
$sql[] = '';

$sql[] = '-- wishlist';
$sql[] = 'INSERT INTO `wishlist` (`id`,`user_id`,`product_id`) VALUES';
$sql[] = '(1,2,1),(2,2,3),(3,2,9),(4,2,12),(5,2,15);';
$sql[] = '';

// ------------------------------------------------------------- demo orders
$bySlug = [];
foreach ($products as $p) {
    $bySlug[$p['slug']] = $p['id'];
}

$orderNo = 'VEL-2024-8942';
$items = [
    [$bySlug['yamaha-psr-sx900-61-key-workstation'], 'Yamaha PSR-SX900 61-Key Workstation', $instrImgs[0], 'Standard 61-Key + Power Adapter', 3500.00, 1],
    [$bySlug['shure-sm58-dynamic-vocal-microphone'],  'Shure SM58 Dynamic Vocal Microphone',  $cartImgs[2],  'Studio Edition',                   1200.00, 1],
    [$bySlug['lace-balconette-set'],                  'Lace Balconette Set',                 $pdpImgs[0],   'Burgundy / Wine - M',              280.00, 1],
];
$subtotal = 0.0;
foreach ($items as $it) { $subtotal += $it[4] * $it[5]; }
$shipping = 0.00;
$total = $subtotal + $shipping;

$sql[] = '-- demo orders';
$sql[] = "INSERT INTO `orders` (`id`,`order_no`,`user_id`,`customer_name`,`email`,`phone`,`region`,`city`,`address`,`shipping_method`,`shipping_label`,`shipping_fee`,`subtotal`,`discount`,`total`,`payment_channel`,`payment_reference`,`payment_status`,`status`,`notes`,`created_at`) VALUES";
$sql[] = "(1,'{$orderNo}',2,'Kwame Mensah','kwame.mensah@ziongroups.com.gh','+233 24 492 8812','Greater Accra Region','East Legon','No. 14 Boundary Road, Behind Mensvic Grand Hotel, East Legon','metro','Accra Express (Same-Day / 24 hrs)'," .
    $M($shipping) . ',' . $M($subtotal) . ',0.00,' . $M($total) . ",'momo','MTN-MOMO-8812','paid','shipped','Discreet packaging required on all intimate items.','2024-10-24 09:12:00'),";
$sql[] = "(2,'VEL-2024-8957',2,'Kwame Mensah','kwame.mensah@ziongroups.com.gh','+233 24 492 8812','Greater Accra Region','East Legon','No. 14 Boundary Road, Behind Mensvic Grand Hotel, East Legon','metro','Accra Express (Same-Day / 24 hrs)',0.00,280.00,28.00,252.00,'telecel','TELECEL-4471','paid','delivered',NULL,'2024-09-02 15:40:00'),";
$sql[] = "(3,'VEL-2024-9011',2,'Kwame Mensah','kwame.mensah@ziongroups.com.gh','+233 24 492 8812','Ashanti Region','Kumasi','12 Ahodwo Road, Nhyiaeso','regional','Regional Road (Kumasi / Takoradi)',45.00,3100.00,0.00,3145.00,'cod',NULL,'pending','pending','Leave with front desk if unavailable.','2024-11-11 11:05:00');";
$sql[] = '';

$oid = 0;
$sql[] = 'INSERT INTO `order_items` (`id`,`order_id`,`product_id`,`product_name`,`product_image`,`variant_text`,`unit_price`,`qty`,`line_total`) VALUES';
$orows = [];
foreach ([1 => $items, 2 => [[$bySlug['lace-balconette-set'], 'Lace Balconette Set', $pdpImgs[0], 'Obsidian Black - S', 280.00, 1]], 3 => [[$bySlug['korg-minilogue-xd-polyphonic-synthesizer'], 'Korg Minilogue XD Polyphonic Synthesizer', $instrImgs[4], null, 3100.00, 1]]] as $orderId => $list) {
    foreach ($list as $it) {
        $oid++;
        $orows[] = sprintf('(%d,%d,%d,%s,%s,%s,%s,%d,%s)',
            $oid, $orderId, $it[0], $C($it[1]), $C($it[2]),
            $N($it[3]), $M($it[4]), $it[5], $M($it[4] * $it[5])
        );
    }
}
$sql[] = implode(",\n", $orows) . ';';
$sql[] = '';

$sql[] = 'INSERT INTO `order_events` (`order_id`,`status`,`note`,`created_at`) VALUES';
$sql[] = "(1,'pending','Order received','2024-10-24 09:12:00'),";
$sql[] = "(1,'confirmed','Payment confirmed via MTN MoMo','2024-10-24 09:14:00'),";
$sql[] = "(1,'packing','Quality & discretion check','2024-10-24 10:02:00'),";
$sql[] = "(1,'shipped','Van Dispatched (East Legon) - Zion Safe-Van #GW-4821-23','2024-10-24 13:30:00'),";
$sql[] = "(2,'confirmed','Payment confirmed via Telecel Cash','2024-09-02 15:41:00'),";
$sql[] = "(2,'delivered','Delivered & signed for','2024-09-02 18:22:00'),";
$sql[] = "(3,'pending','Order received','2024-11-11 11:05:00');";

$out = $ROOT . '/seed.sql';
file_put_contents($out, implode("\n", $sql) . "\n");

printf(
    "Wrote %s\n  categories: %d\n  products:   %d\n  images:     %d\n  variants:   %d\n  specs:      %d\n  bundles:    %d\n",
    $out, count($categories), count($products), $im, $vm, $sm, $bm
);
