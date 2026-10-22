<?php
/**
 * Telegram intake - photo-to-row matching.
 *
 * Pure functions, no DB and no Telegram: given the validated rows from the
 * bulk importer and the staged photos of a batch, decide which photos belong
 * to which product row. Four signals, strongest first:
 *
 *   1. SKU      - caption contains the row's SKU ("new: VL-LI-00101 ...")
 *   2. Caption  - token overlap between the caption and the product name;
 *                 exact ties are left for the human rather than guessed
 *   3. Order    - unmatched photos go to unmatched rows in send order
 *                 (clients send photos in row order; toggleable)
 *   4. Human    - whatever is left shows up as leftovers in the review
 */

declare(strict_types=1);

/** Lowercased, punctuation-free, de-duplicated tokens. */
function intake_tokens(string $s): array
{
    $s = (string) preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($s));
    $parts = preg_split('/\s+/u', trim($s)) ?: [];
    static $stop = [
        'the', 'a', 'an', 'of', 'for', 'and', 'or', 'with', 'in', 'on', 'to',
        'by', 'is', 'new', 'set', 'pcs', 'pair', 'pack', 'from', 'this', 'unit',
    ];
    $out = [];
    foreach ($parts as $one) {
        if ($one !== '' && !in_array($one, $stop, true)) {
            $out[$one] = true;
        }
    }
    return array_keys($out);
}

/** 'Aura Bodysuit!' -> 'aura bodysuit' - for equality comparisons. */
function intake_squeeze(string $s): string
{
    $s = (string) preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($s));
    return trim((string) preg_replace('/\s+/u', ' ', $s));
}

/** 'VL-LI-00101' -> 'vlli00101' - for "does the caption contain the SKU?". */
function intake_sku_key(string $sku): string
{
    return (string) preg_replace('/[^0-9a-z]+/i', '', $sku);
}

/**
 * Fold staged items into "shots": one album (media_group) = one shot = one
 * product's gallery; a photo sent alone is its own shot. Caption of a shot =
 * first non-empty caption among its members. Input order (message_id) wins.
 *
 * Exception - a captionless album is NOT one product. The phone flow saves a
 * WhatsApp batch to the gallery and forwards it as a single Telegram album,
 * which would otherwise glue every product of the batch onto one row. No
 * caption means nothing groups them, so each member becomes its own shot and
 * the order fallback lines them up against the sheet rows instead.
 *
 * @param list<array{file_path: string, caption?: string, media_group?: string, id?: int}> $items
 * @return list<array{paths: list<string>, caption: string}>
 */
function intake_group_shots(array $items): array
{
    $groups = [];
    $order  = [];
    foreach ($items as $item) {
        if ((string) ($item['media_group'] ?? '') === 'sheet') {
            continue;   // sheet marker, not a photo
        }
        $group = trim((string) ($item['media_group'] ?? ''));
        $key   = $group !== '' ? 'g:' . $group : 's:' . (string) ($item['id'] ?? count($groups));
        if (!isset($groups[$key])) {
            $groups[$key] = ['paths' => [], 'caption' => ''];
            $order[] = $key;
        }
        $path = (string) ($item['file_path'] ?? '');
        if ($path !== '') {
            $groups[$key]['paths'][] = $path;
        }
        if ($groups[$key]['caption'] === '') {
            $cap = trim((string) ($item['caption'] ?? ''));
            if ($cap !== '') {
                $groups[$key]['caption'] = $cap;
            }
        }
    }
    $shots = [];
    foreach ($order as $key) {
        $group = $groups[$key];
        if ($key[0] === 'g' && $group['caption'] === '') {
            foreach ($group['paths'] as $one) {
                $shots[] = ['paths' => [$one], 'caption' => ''];
            }
            continue;
        }
        $shots[] = $group;
    }
    return $shots;
}

/**
 * Match staged photos onto validated bulk rows.
 *
 * @param list<array> $rows  bulk_preview_validation()['results'] entries
 *                           (or plain ['sku'=>..,'name'=>..] data rows)
 * @param list<array> $items intake_items() rows, newest payload order
 * @param array{order_fallback?: bool} $opts
 * @return array{
 *   assignments: array<int, array{paths: list<string>, method: string}>,
 *   unmatched_rows: list<int>,
 *   leftover: list<string>,
 *   stats: array{sku: int, caption: int, order: int, unmatched: int, photos: int, leftovers: int}
 * }
 */
function intake_match(array $rows, array $items, array $opts = []): array
{
    $useOrder = (bool) ($opts['order_fallback'] ?? true);
    $shots    = intake_group_shots($items);

    $facts = [];
    foreach ($rows as $i => $row) {
        $data   = (isset($row['data']) && is_array($row['data'])) ? $row['data'] : $row;
        $name   = (string) ($data['name'] ?? '');
        $facts[$i] = [
            'sku'    => (string) ($data['sku'] ?? ''),
            'name'   => intake_squeeze($name),
            'tokens' => intake_tokens($name),
        ];
    }

    $assign    = [];
    $shotTaken = [];

    // ---- 1. SKU mentioned in the caption
    foreach ($shots as $si => $shot) {
        if ($shot['caption'] === '') {
            continue;
        }
        $capKey = intake_sku_key($shot['caption']);
        if ($capKey === '') {
            continue;
        }
        foreach ($facts as $i => $f) {
            if (isset($assign[$i]) || $f['sku'] === '') {
                continue;
            }
            $skuKey = intake_sku_key($f['sku']);
            if (mb_strlen($skuKey) >= 4 && str_contains($capKey, $skuKey)) {
                $assign[$i]     = ['paths' => $shot['paths'], 'method' => 'sku'];
                $shotTaken[$si] = true;
                break;
            }
        }
    }

    // ---- 2. caption vs product name (contested scores go to the human)
    $candidates = [];
    foreach ($shots as $si => $shot) {
        if (isset($shotTaken[$si]) || $shot['caption'] === '') {
            continue;
        }
        $tokens = intake_tokens($shot['caption']);
        if ($tokens === []) {
            continue;
        }
        $capName = intake_squeeze($shot['caption']);
        foreach ($facts as $i => $f) {
            if (isset($assign[$i])) {
                continue;
            }
            $exact = $f['name'] !== '' && $f['name'] === $capName;
            $score = 0;
            foreach ($tokens as $t) {
                if (in_array($t, $f['tokens'], true)) {
                    $score += mb_strlen($t) >= 5 ? 2 : 1;
                }
            }
            if (!$exact && $score < 2) {
                continue;
            }
            $candidates[] = ['score' => $exact ? 1000 : $score, 'shot' => $si, 'row' => $i];
        }
    }
    usort($candidates, static fn(array $a, array $b) => $b['score'] <=> $a['score']);

    $byScore = [];
    foreach ($candidates as $c) {
        $byScore[$c['score']][] = $c;   // insertion order = descending score
    }
    foreach ($byScore as $group) {
        $shotHits = [];
        $rowHits  = [];
        foreach ($group as $c) {
            $shotHits[$c['shot']] = ($shotHits[$c['shot']] ?? 0) + 1;
            $rowHits[$c['row']]   = ($rowHits[$c['row']] ?? 0) + 1;
        }
        foreach ($group as $c) {
            if (isset($shotTaken[$c['shot']]) || isset($assign[$c['row']])) {
                continue;
            }
            if ($shotHits[$c['shot']] > 1 || $rowHits[$c['row']] > 1) {
                continue;   // equally good for two rows - let a human decide
            }
            $assign[$c['row']]     = ['paths' => $shots[$c['shot']]['paths'], 'method' => 'caption'];
            $shotTaken[$c['shot']] = true;
        }
    }

    // ---- 3. order fallback: remaining rows get remaining photos, in order
    if ($useOrder) {
        $freeRows = [];
        foreach (array_keys($facts) as $i) {
            if (!isset($assign[$i])) {
                $freeRows[] = $i;
            }
        }
        $freeShots = [];
        foreach ($shots as $si => $shot) {
            if (!isset($shotTaken[$si])) {
                $freeShots[] = $si;
            }
        }
        $n = min(count($freeRows), count($freeShots));
        for ($k = 0; $k < $n; $k++) {
            $i             = $freeRows[$k];
            $si            = $freeShots[$k];
            $assign[$i]     = ['paths' => $shots[$si]['paths'], 'method' => 'order'];
            $shotTaken[$si] = true;
        }
    }

    // ---- leftovers, unmatched rows, stats
    $leftover = [];
    $photoN   = 0;
    foreach ($shots as $si => $shot) {
        foreach ($shot['paths'] as $p) {
            $photoN++;
            if (!isset($shotTaken[$si])) {
                $leftover[] = $p;
            }
        }
    }

    $stats = ['sku' => 0, 'caption' => 0, 'order' => 0, 'unmatched' => 0];
    $unmatched = [];
    foreach (array_keys($facts) as $i) {
        if (isset($assign[$i])) {
            $stats[$assign[$i]['method']]++;
        } else {
            $stats['unmatched']++;
            $unmatched[] = $i;
        }
    }
    $stats['photos']    = $photoN;
    $stats['leftovers'] = count($leftover);

    return [
        'assignments'    => $assign,
        'unmatched_rows' => $unmatched,
        'leftover'       => $leftover,
        'stats'          => $stats,
    ];
}
