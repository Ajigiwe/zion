<?php
/**
 * Generates the 4-pillar category tree INSERT for seed.sql.
 *
 *   php tools/gen_categories.php          -> prints the INSERT block + slug=>id map
 *   php tools/gen_categories.php --write  -> replaces the categories block in seed.sql
 *
 * Tree structure follows the Zion Groups site spec:
 * Music & Musical Instruments / Professional Audio & Sound /
 * Church & Worship Equipment (featured) / Lingerie, three levels deep.
 */

declare(strict_types=1);

$ROOT = dirname(__DIR__);

// images reused from the existing category rows (Google-hosted, stable)
const IMG_L1    = 'https://lh3.googleusercontent.com/aida-public/AB6AXuCuuyCXMxQ6L1Py1XNZIJINPJdIyXsHh7o5WPqMzJasN53SepEyObpSiqpJJdcIcg2koF1CuNrE1hwKZUVipNooIr2FFoWvQ9eGpEIdTwUARm8IUmNK8S1RVNaiULZo4u-xdYDgV38Exe1gr4Z-yJQmTl9xWIDJA5oCSOHwkRfrzwEuX-p_LhYmQCL5VH-UKq6vyGFGNCPEKe6efkdz-2ooQ22vjRx3TFPPUZZaW10N6nFLGmPasp8i';
const IMG_SETS   = 'https://lh3.googleusercontent.com/aida-public/AB6AXuAc_EklCvGXb9FuBl6wJb63po1kEMpowEcuWgwlSIpSxDJhYIPQkAY4rrqBqBcH0WngJTGQrHwetubgCmZVL8RpfUuWqUAAM_trw6y8P7gi8U4K1kH7dK2nH0Bg3k0eC6g6KnNahGhAYmnxqOpJ3pSvakMJEQ7sEKhzLZ2iMgOYoLZm8hsVEFVTp7htu6IPOI4rajl1xMtCVnwZvZIK561Jvf0TKnhOKUogHHlW4A8HH9YWLJTPQvKr';
const IMG_BRA    = 'https://lh3.googleusercontent.com/aida-public/AB6AXuDhyr5V3dMCMqO4IrmCt1zmn1WIQzg3ROQV1ZFWMp2ltF_Qj-_O7896HtTBbUsrvjxmqwUjApk4aSPpfDb4m7NZvROH_YOf0jqNX0Pl2yp_tvlw-o9WyhOpWfTF3o4ckSONkMkvwIfE9obmL3vOPxlk796taPUTLVdloqUAbO1XdDEIc5dAJKerSfctGMyy9tdhs19HzVPEDW3nI49t4JaFg-eOhvn5yNDjwkHP1QUs8Ji2TyZoPjNJ';
const IMG_BODY   = 'https://lh3.googleusercontent.com/aida-public/AB6AXuCdVPNjbnmW7MjUC6WZzmVbAO0NOefax8rY1OS85wTeJUBesejYH_ZM9n4-_JgFpxu6Q4Iy6HaxmEZIq8lCgwTuxShfI6p43LFfkZZIAvzRgwGyXR5T3n1vXpYqaz3qA3fLc-4d6fYpcRgzQqna5SwYQYETMbYvJ_NVtkbOtcJ46uf7ArGXsE1yJgtude-NfmQhpXv2k_c6sVa33m-8o7daWf-TpB7MSrNgGyL6I0h90KT8-58gMv6J';
const IMG_SLEEP  = 'https://lh3.googleusercontent.com/aida-public/AB6AXuD8N1WCGo3P1UQbJ-zEDSxo_GWI-GO9I0IxnrBDap_thCD8TW2sb_eQgxTdhWJohKI67Be5nbu7gsUuXi-qNQJ7tCS6d5iJrnOfv92Q6N13nZTJVPFGtpZqFe-iWz5YNs3p_B0Wj3dCGzJGvLrvl_Tiug_EaPLl0F_nbxzokmZfKXIIJMSbnF_lCOU0u2TEf6XaMz1b03JPqJLpjhFf4Xd7XrVJ86yj_EjTSpbaUvRhuoFMC0nADMbN';
const IMG_INST   = 'https://lh3.googleusercontent.com/aida-public/AB6AXuC3gkXVgVHBIoEanTFNlNwlZ63GIk_rw4w9muAmSICdz66aKzz-8fZvd6ORKlQdcHrCIHcHFUvFoDAKZc4snr5xybo0BSdQQRdkXIoXGyO4CSFJlT8pse4TRp3T9fREG5lAn1ZdMciz7sU7FsMQC4Y53rvsbJbatLKEJPVhItHMw8nM9P5lJ0JymvyjN_L4DzOUrGJkmwLntkbGrWRU27xFIu8S-MAZiUTmLnoTEEjTD4PWVrOGVHFg';
const IMG_KEYS   = 'https://lh3.googleusercontent.com/aida-public/AB6AXuATHjn6FY__HoBwQBroxqri5eHWnfN-MYXMMcM4LG6Tc9lDKe5G09WJOn-81a1DUp8ScUKW8Pqx5IDRF27XGnYXc0WVSiZ7zs2pQfrwXRSr93sbtqrbxMJ4YxUO-mEQ_R37uf4POEX6M4bBhJ6BuvIbnlr5ZqJTnM69Y-GMC67kgDooZQiXgeClNmxdTY_V6VCLHu4ImNKGaprBzIhYmCuaJccaSrXA5zDqc0C9T1_8IpOg-wpmuvw1';
const IMG_GUIT   = IMG_INST;
const IMG_DRUM   = 'https://lh3.googleusercontent.com/aida-public/AB6AXuBxsRVc1RqwluNFD9b5z49C_wM7oNTh2qYzGXO7y8PvdAnlGAtq_9bjik8SavgdRQQb1vQrwZGEMkMujYhh0oiN2EtP_YA06s9Q5qKcHEmWX-4cHJOGxSDfCXwq2_h5P4wQbZGBkc6CnzmnqyFEHf_LYcUd0HJg2oClvRvgERjvdMtKUhMHtbq8IoP1ikvxSXGlJGIOxbWU1doMg9JQvqmWMElcirsWufunW__IGVekiH1xe-5nJ-sf';
const IMG_MIC    = 'https://lh3.googleusercontent.com/aida-public/AB6AXuCTaQgbxncg7mIa5fjRVgmXYVK1e2CXE72OBqW-XHN61Ci-ORAqysZiSr7VqSpLB_Sg-vs-9c4SVokIbSxFDKcSpxvk_4-J8ctgCegQRTnOrXqk2OAlUwb-16nKpgOT_IV-90yg_1nfMZ1lnkQDkqPfgFrfU6f9SMO3fQ9tJYeDdIv1GavPY26rr5tOcyJTvtQiSI0hPb3LBrTa9HYUmCVWsNUM_5iCXKopHzoNYqeO8qAlj9aFoKaZ';
const IMG_AUDIO  = 'https://lh3.googleusercontent.com/aida-public/AB6AXuCOUNXo3ViuAueol4-UxvimfnW8xorboyZTSyt7iZuH_pn2VC7wR-XlsO4btKb0KIMhix_WSBj5prJIP3B8_JHLpfSQDn9N2Hi3ViR2PfLfE4Mt18mrQMsKNw1sMnSgu3jbukGiOamwrgfCbIjyrKu4-FubxkDwqiaa65CUoLOghKqGzv3DcufrWZtMoizIkMmJier_Ey3GAJt6ndvw3BfSHBmRV77n5NwuKG8773VIjM0HZNc49qW_';

// slug => [name, dept, description, image, children[]]
$TREE = [
    ['music-instruments', 'Music & Musical Instruments', 'instruments',
        'Keyboards, guitars, drums, wind and traditional instruments for players, choirs, churches and studios.', IMG_INST, [
        ['keyboards', 'Keyboards & Pianos', IMG_KEYS,
            'Digital keyboards, portable keyboards, digital pianos, synthesizers and MIDI controllers.', [
            ['digital-keyboards', 'Digital Keyboards'],
            ['portable-keyboards', 'Portable Keyboards'],
            ['digital-pianos', 'Digital Pianos'],
            ['synthesizers', 'Synthesizers'],
            ['midi-keyboards', 'MIDI Keyboards'],
            ['keyboard-accessories', 'Keyboard Accessories'],
        ]],
        ['guitars', 'Guitars', IMG_GUIT,
            'Acoustic, electric, bass and classical guitars with strings, picks and cases.', [
            ['acoustic-guitars', 'Acoustic Guitars'],
            ['electric-guitars', 'Electric Guitars'],
            ['bass-guitars', 'Bass Guitars'],
            ['classical-guitars', 'Classical Guitars'],
            ['guitar-accessories', 'Guitar Accessories'],
            ['guitar-strings', 'Guitar Strings'],
            ['guitar-picks', 'Guitar Picks'],
            ['guitar-bags-cases', 'Guitar Bags & Cases'],
        ]],
        ['drums', 'Drums & Percussion', IMG_DRUM,
            'Drum kits, electronic drums, snares, cymbals and hand percussion.', [
            ['drum-sets', 'Drum Sets'],
            ['electronic-drums', 'Electronic Drums'],
            ['snare-drums', 'Snare Drums'],
            ['cymbals', 'Cymbals'],
            ['percussion-instruments', 'Percussion Instruments'],
            ['drum-accessories', 'Drum Accessories'],
        ]],
        ['wind-instruments', 'Wind Instruments', IMG_GUIT,
            'Trumpets, saxophones, flutes, clarinets and harmonicas.', [
            ['trumpets', 'Trumpets'],
            ['saxophones', 'Saxophones'],
            ['flutes', 'Flutes'],
            ['clarinets', 'Clarinets'],
            ['harmonicas', 'Harmonicas'],
            ['other-wind', 'Other Wind Instruments'],
        ]],
        ['traditional-instruments', 'Traditional Instruments', IMG_DRUM,
            'African drums, talking drums, shakers and other traditional instruments.', [
            ['african-drums', 'African Drums'],
            ['talking-drums', 'Talking Drums'],
            ['shakers', 'Shakers'],
            ['tambourines', 'Tambourines'],
            ['other-traditional', 'Other Traditional Instruments'],
        ]],
    ]],
    ['professional-audio', 'Professional Audio & Sound', 'instruments',
        'Microphones, speakers, mixers, amplifiers and studio equipment for live sound and recording.', IMG_AUDIO, [
        ['microphones', 'Microphones', IMG_MIC,
            'Wired, wireless, condenser, dynamic and lavalier microphones.', [
            ['wired-microphones', 'Wired Microphones'],
            ['wireless-microphones', 'Wireless Microphones'],
            ['condenser-microphones', 'Condenser Microphones'],
            ['dynamic-microphones', 'Dynamic Microphones'],
            ['lavalier-microphones', 'Lavalier Microphones'],
            ['microphone-accessories', 'Microphone Accessories'],
        ]],
        ['speakers', 'Speakers', IMG_AUDIO,
            'Active and passive speakers, subwoofers, portable PA and line array systems.', [
            ['active-speakers', 'Active Speakers'],
            ['passive-speakers', 'Passive Speakers'],
            ['subwoofers', 'Subwoofers'],
            ['portable-speakers', 'Portable Speakers'],
            ['line-array', 'Line Array'],
        ]],
        ['mixers', 'Mixers', IMG_AUDIO,
            'Analog and digital mixers, audio interfaces and DJ mixers.', [
            ['analog-mixers', 'Analog Mixers'],
            ['digital-mixers', 'Digital Mixers'],
            ['audio-interfaces', 'Audio Interfaces'],
            ['dj-mixers', 'DJ Mixers'],
        ]],
        ['amplifiers', 'Amplifiers', IMG_AUDIO,
            'Power, guitar, bass and PA amplifiers.', [
            ['power-amplifiers', 'Power Amplifiers'],
            ['guitar-amplifiers', 'Guitar Amplifiers'],
            ['bass-amplifiers', 'Bass Amplifiers'],
            ['pa-amplifiers', 'PA Amplifiers'],
        ]],
        ['studio-equipment', 'Studio Equipment', IMG_AUDIO,
            'Studio monitors, interfaces, headphones, MIDI controllers and recording accessories.', [
            ['studio-monitors', 'Studio Monitors'],
            ['studio-audio-interfaces', 'Audio Interfaces'],
            ['headphones', 'Headphones'],
            ['studio-mics', 'Studio Mics'],
            ['midi-controllers', 'MIDI Controllers'],
            ['recording-accessories', 'Recording Accessories'],
        ]],
        ['cables-accessories', 'Cables & Accessories', IMG_AUDIO,
            'XLR, instrument, speaker and power cables, adapters and connectors.', [
            ['xlr-cables', 'XLR Cables'],
            ['instrument-cables', 'Instrument Cables'],
            ['speaker-cables', 'Speaker Cables'],
            ['power-cables', 'Power Cables'],
            ['adapters', 'Adapters'],
            ['connectors', 'Connectors'],
        ]],
    ]],
    ['church-worship', 'Church & Worship Equipment', 'instruments',
        'Complete sound, instrument and worship setups for churches and ministries.', IMG_INST, [
        ['worship-instruments', 'Worship Instruments', IMG_KEYS,
            'Keyboards, guitars, drums and percussion for the worship team.', [
            ['worship-keyboards', 'Keyboards'],
            ['worship-guitars', 'Guitars'],
            ['worship-drums', 'Drums'],
            ['worship-percussion', 'Percussion'],
        ]],
        ['church-audio', 'Church Audio', IMG_MIC,
            'Microphones, speakers, mixers, amplifiers and monitors for the sanctuary.', [
            ['church-microphones', 'Microphones'],
            ['church-speakers', 'Speakers'],
            ['church-mixers', 'Mixers'],
            ['church-amplifiers', 'Amplifiers'],
            ['church-monitors', 'Monitors'],
        ]],
        ['church-accessories', 'Church Accessories', IMG_AUDIO,
            'Mic stands, speaker stands, cables, power equipment and music stands.', [
            ['mic-stands', 'Mic Stands'],
            ['speaker-stands', 'Speaker Stands'],
            ['church-cables', 'Cables'],
            ['power-equipment', 'Power Equipment'],
            ['music-stands', 'Music Stands'],
        ]],
    ]],
    ['lingerie', 'Lingerie', 'lingerie',
        'Luxury intimate apparel - bras, panties, matching sets, nightwear, bodysuits and shapewear.', IMG_L1, [
        ['bras', 'Bras', IMG_BRA,
            'Everyday, lace, push-up, sports and strapless bras plus bralettes.', [
            ['everyday-bras', 'Everyday Bras'],
            ['lace-bras', 'Lace Bras'],
            ['push-up-bras', 'Push-Up Bras'],
            ['sports-bras', 'Sports Bras'],
            ['strapless-bras', 'Strapless Bras'],
            ['bralettes', 'Bralettes'],
        ]],
        ['panties', 'Panties', IMG_SETS,
            'Briefs, thongs, boyshorts, lace and high-waist panties.', [
            ['briefs', 'Briefs'],
            ['thongs', 'Thongs'],
            ['boyshorts', 'Boyshorts'],
            ['lace-panties', 'Lace Panties'],
            ['high-waist-panties', 'High-Waist Panties'],
        ]],
        ['lingerie-sets', 'Lingerie Sets', IMG_SETS,
            'Bra and panty sets, lace, satin and bridal sets.', [
            ['bra-panty-sets', 'Bra & Panty Sets'],
            ['lace-sets', 'Lace Sets'],
            ['satin-sets', 'Satin Sets'],
            ['bridal-sets', 'Bridal Sets'],
        ]],
        ['nightwear', 'Nightwear', IMG_SLEEP,
            'Nightgowns, pajamas, satin sleepwear and robes.', [
            ['nightgowns', 'Nightgowns'],
            ['pajamas', 'Pajamas'],
            ['satin-sleepwear', 'Satin Sleepwear'],
            ['robes', 'Robes'],
        ]],
        ['bodysuits', 'Bodysuits', IMG_BODY,
            'Lace and fashion bodysuits.', [
            ['lace-bodysuits', 'Lace Bodysuits'],
            ['fashion-bodysuits', 'Fashion Bodysuits'],
        ]],
        ['shapewear', 'Shapewear', IMG_BODY,
            'Waist trainers, tummy control, shaping shorts and shapewear bodysuits.', [
            ['waist-trainers', 'Waist Trainers'],
            ['tummy-control', 'Tummy Control'],
            ['shaping-shorts', 'Shaping Shorts'],
            ['shapewear-bodysuits', 'Shapewear Bodysuits'],
        ]],
        ['lingerie-accessories', 'Lingerie Accessories', IMG_L1,
            'Stockings, garters, sleep masks and other accessories.', [
            ['stockings', 'Stockings'],
            ['garters', 'Garters'],
            ['sleep-masks', 'Sleep Masks'],
            ['other-lingerie-accessories', 'Other Accessories'],
        ]],
    ]],
];

// ------------------------------------------------------------------ flatten
$rows  = [];   // [id, parent_id, slug, name, dept, desc, image, sort]
$bySlug = [];
$id = 0;

$emit = static function (array $node, ?int $parentId, string $dept, string $img, int $depth) use (&$rows, &$bySlug, &$id, &$emit): void {
    if ($depth === 0) {
        [$slug, $name, $dept, $desc, $img, $children] = $node;
    } elseif ($depth === 1) {
        [$slug, $name, $img, $desc, $children] = $node;
    } else {
        [$slug, $name] = $node;
        $desc = '';
        $children = [];
    }
    $id++;
    $myId = $id;
    $rows[] = [$myId, $parentId, $slug, $name, $dept, $desc, $img, $myId - 1];
    $bySlug[$slug] = $myId;
    foreach ($children as $child) {
        $emit($child, $myId, $dept, $img, $depth + 1);
    }
};
foreach ($TREE as $root) {
    $emit($root, null, '', '', 0);
}

// ------------------------------------------------------------------- output
$C = static fn(string $s): string => "'" . str_replace("'", "''", $s) . "'";
$N = static fn(string $s): string => $s === '' ? 'NULL' : $C($s);

$sqlRows = [];
foreach ($rows as [$rid, $pid, $slug, $name, $dept, $desc, $img, $sort]) {
    $sqlRows[] = sprintf('(%d,%s,%s,%s,%s,%s,%s,%d)',
        $rid,
        $pid === null ? 'NULL' : (string) $pid,
        $C($slug), $C($name), $C($dept), $N($desc), $N($img), $sort
    );
}
$block = "INSERT INTO `categories` (`id`,`parent_id`,`slug`,`name`,`department`,`description`,`image_url`,`sort_order`) VALUES\n"
    . implode(",\n", $sqlRows) . ';';

if (in_array('--write', $argv ?? [], true)) {
    $file = $ROOT . '/seed.sql';
    $src  = file_get_contents($file);
    $new  = preg_replace(
        '/INSERT INTO `categories` .*?;\r?\n/s',
        str_replace("\n", "\r\n", $block) . "\r\n",
        $src,
        1,
        $count
    );
    if ($count !== 1) {
        fwrite(STDERR, "FAILED: categories block not found/replaced\n");
        exit(1);
    }
    file_put_contents($file, $new);
    printf("seed.sql updated: %d categories\n", count($rows));
} else {
    echo $block . "\n\n-- slug => id\n";
    foreach ($bySlug as $slug => $cid) {
        echo "$slug=$cid\n";
    }
}
