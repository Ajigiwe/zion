<?php
/**
 * Telegram Intake - review what the bot staged and push it into the Bulk
 * Products Waiting Room with photos already matched to rows.
 *
 * Flow: bot stages (sheet + photos) -> this screen matches them ->
 * "Send to Waiting Room" -> bulk_products.php waiting room -> commit.
 * Publishing never happens from here directly: the Waiting Room keeps its
 * normal review + CSRF commit step.
 */

declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/product_bulk_service.php';
require_once __DIR__ . '/../includes/intake_service.php';
require_once __DIR__ . '/../includes/intake_matcher.php';

require_admin();

/** Parse a batch's stored sheet into bulk preview results, or null. */
function intake_review_rows(array $batch): ?array
{
    $csv = (string) ($batch['sheet_data'] ?? '');
    if (trim($csv) === '') {
        return null;
    }
    $parsed = bulk_parse_csv_string($csv);
    if (isset($parsed['error']) || $parsed['rows'] === []) {
        return null;
    }
    return ['parsed' => $parsed, 'preview' => bulk_preview_validation($parsed['rows'])];
}

function intake_method_pill(string $method): string
{
    return match ($method) {
        'sku'     => '<span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-0.5 rounded bg-secondary-fixed text-on-secondary-fixed">sku match</span>',
        'caption' => '<span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-0.5 rounded bg-primary-container text-on-primary">caption match</span>',
        'order'   => '<span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-0.5 rounded bg-primary-fixed text-primary">by order</span>',
        default   => '<span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-0.5 rounded border border-outline-variant text-on-surface-variant">no photo</span>',
    };
}

/* -------------------------------------------------------------- POST ----- */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action  = (string) ($_POST['action'] ?? '');
    $batchId = (int) ($_POST['batch_id'] ?? 0);
    $batch   = intake_batch($batchId);

    if ($action === 'discard_batch') {
        if ($batch === null) {
            flash_set('error', 'That batch no longer exists.');
        } else {
            $n = intake_discard($batchId);
            flash_set('info', sprintf('Batch discarded (%d staged photo(s) removed).', $n));
        }
        header('Location: ' . url('admin/intake.php'));
        exit;
    }

    if ($action === 'send_to_waiting_room') {
        $work = $batch === null ? null : intake_review_rows($batch);
        if ($work === null) {
            flash_set('error', 'That batch has no readable spreadsheet - send the .csv/.xlsx from Telegram first.');
            header('Location: ' . url('admin/intake.php'));
            exit;
        }

        $results  = $work['preview']['results'];
        $match    = intake_match($results, intake_items($batchId), [
            'order_fallback' => !empty($_POST['use_order']),
        ]);
        $posted   = (array) ($_POST['assign'] ?? []);
        $manual   = 0;

        foreach ($results as $i => &$res) {
            $paths = $match['assignments'][$i]['paths'] ?? [];
            if (array_key_exists($i, $posted)) {
                $value = trim((string) $posted[$i]);
                if ($value === 'none') {
                    $paths = [];
                } elseif ($value !== '') {
                    $paths  = [$value];
                    $manual++;
                }
            }
            if ($paths === []) {
                continue;
            }

            $hero = trim((string) ($res['data']['image_url'] ?? ''));
            if ($hero === '') {
                // first matched photo becomes the hero image
                $res['data']['image_url'] = $paths[0];
                $paths = array_slice($paths, 1);
            }
            if ($paths !== []) {
                // remaining photos join the gallery (CSV-supplied ones stay)
                $existing = trim((string) ($res['data']['gallery_images'] ?? ''));
                $gallery  = $existing !== ''
                    ? array_map('trim', preg_split('/[|,]/', $existing) ?: [])
                    : [];
                $gallery  = array_merge($gallery, $paths);
                $gallery  = array_values(array_filter($gallery, static fn($u) => $u !== ''));
                $res['data']['gallery_images'] = implode(' | ', $gallery);
            }
        }
        unset($res);

        $_SESSION['bulk_waiting_room'] = [
            'timestamp'   => time(),
            'source_name' => sprintf(
                'Telegram: %s + %d photo(s)',
                $batch['sheet_name'] !== '' ? $batch['sheet_name'] : 'sheet',
                intake_photo_count($batchId)
            ),
            'items' => $results,
        ];
        intake_claim($batchId);

        $auto = count($results) - $manual;
        flash_set(
            'success',
            sprintf(
                '%d products moved to the Waiting Room (%d auto-matched photo set(s), %d manual override(s)). Check the thumbnails there, then commit.',
                count($results),
                max(0, $auto),
                $manual
            )
        );
        header('Location: ' . url('admin/bulk_products.php?tab=waiting_room'));
        exit;
    }

    flash_set('error', 'Unknown action.');
    header('Location: ' . url('admin/intake.php'));
    exit;
}

/* --------------------------------------------------------------- GET ----- */

$reviewId = (int) ($_GET['review'] ?? 0);
$batch    = $reviewId > 0 ? intake_batch($reviewId) : null;
$configured = intake_is_configured();

admin_head('Telegram Intake', 'intake');
?>
<div class="max-w-6xl">
  <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold text-on-surface">Telegram Intake</h1>
      <p class="font-body-sm text-body-sm text-on-surface-variant">
        Product batches sent to the bot land here. Match photos to rows, review, then hand everything to the Waiting Room.
      </p>
    </div>
    <?php if ($batch !== null): ?>
      <a href="<?= e(url('admin/intake.php')) ?>"
         class="font-label-nav text-label-nav uppercase tracking-wider text-primary hover:underline">&larr; all batches</a>
    <?php endif; ?>
  </header>

  <?php if (!$configured): ?>
    <div class="mb-6 p-4 rounded-lg bg-[#f5e6c8] text-[#6b4e00] font-body-sm text-body-sm">
      <strong>Telegram intake is not configured.</strong> Add these to <code class="font-mono">.env</code>:
      <code class="font-mono">TG_BOT_TOKEN</code> (from @BotFather),
      <code class="font-mono">TG_WEBHOOK_SECRET</code> (any hex string),
      <code class="font-mono">TG_ALLOWED_CHAT_IDS</code> (your numeric id, from @userinfobot) -
      then run <code class="font-mono">php tools/tg_set_webhook.php</code>.
    </div>
  <?php endif; ?>

  <?php if ($batch !== null): $items = intake_items((int) $batch['id']); ?>
    <?php $work = intake_review_rows($batch); ?>
    <?php if ($work === null): ?>
      <div class="mb-6 p-4 rounded-lg bg-surface-container border border-outline-variant">
        <p class="font-body-sm text-body-sm text-on-surface"><strong>No spreadsheet in this batch yet.</strong></p>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
          Send the .csv/.xlsx from the client's chat (the bot replies with the row count), then reload this page.
        </p>
      </div>
      <?php if ($items !== []): ?>
        <div class="flex flex-wrap gap-3">
          <?php foreach ($items as $it): ?>
            <img src="<?= e(img_url((string) $it['file_path'])) ?>" alt=""
                 class="w-24 h-28 object-cover rounded-lg border border-outline-variant bg-surface-container"/>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php else:
      $results  = $work['preview']['results'];
      $useOrder = (($_GET['order'] ?? '1') === '1');
      $match    = intake_match($results, $items, ['order_fallback' => $useOrder]);

      $captions = [];
      foreach ($items as $it) {
          $cap = trim((string) $it['caption']);
          if ($cap !== '' && !isset($captions[(string) $it['file_path']])) {
              $captions[(string) $it['file_path']] = $cap;
          }
      }
      $stats = $match['stats'];
    ?>
      <div class="mb-4 p-4 rounded-lg bg-surface-container border border-outline-variant font-body-sm text-body-sm">
        <p class="text-on-surface">
          <strong><?= e($batch['sheet_name']) ?></strong> - <?= count($results) ?> row(s),
          <?= count($items) ?> photo(s) staged.
          Matched: <strong><?= (int) $stats['sku'] ?></strong> by SKU,
          <strong><?= (int) $stats['caption'] ?></strong> by caption,
          <strong><?= (int) $stats['order'] ?></strong> in send order.
          <?php if ((int) $stats['unmatched'] > 0): ?>
            <span class="text-[#8a5a00] font-bold"><?= (int) $stats['unmatched'] ?> row(s) still need an image.</span>
          <?php endif; ?>
          <?php if ((int) $stats['leftovers'] > 0): ?>
            <?= (int) $stats['leftovers'] ?> photo(s) not yet used (pick them in the dropdowns below).
          <?php endif; ?>
        </p>
      </div>

      <form method="post" action="<?= e(url('admin/intake.php')) ?>">
        <input type="hidden" name="action" value="send_to_waiting_room"/>
        <input type="hidden" name="batch_id" value="<?= (int) $batch['id'] ?>"/>

        <div class="mb-4 flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
          <input type="checkbox" id="useOrder" name="use_order" value="1" <?= $useOrder ? 'checked' : '' ?>/>
          <label for="useOrder">Also pair leftover photos with leftover rows in the order they were sent
            <a href="<?= e(url('admin/intake.php?review=' . (int) $batch['id'] . '&order=' . ($useOrder ? '0' : '1'))) ?>"
               class="text-primary hover:underline">(re-match)</a></label>
        </div>

        <div class="overflow-x-auto rounded-lg border border-outline-variant">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-surface-container text-left">
                <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">#</th>
                <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">Photos</th>
                <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">SKU</th>
                <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">Product</th>
                <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">Match</th>
                <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">Attach</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($results as $i => $res):
                $data     = $res['data'];
                $auto     = $match['assignments'][$i]['paths'] ?? [];
                $method   = $match['assignments'][$i]['method'] ?? '';
                $invalid  = empty($res['valid']);
              ?>
                <tr class="border-t border-outline-variant align-top <?= $invalid ? 'bg-[#fdecea]' : '' ?>">
                  <td class="p-3 font-mono text-xs text-on-surface-variant"><?= $i + 1 ?></td>
                  <td class="p-3">
                    <div class="flex gap-2">
                      <?php foreach ($auto as $path): ?>
                        <img src="<?= e(img_url($path)) ?>" alt=""
                             class="w-14 h-16 object-cover rounded border border-outline-variant bg-surface-container"/>
                      <?php endforeach; ?>
                      <?php if ($auto === []): ?>
                        <span class="text-[11px] text-outline">none</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="p-3 font-mono text-xs text-on-surface"><?= e((string) $data['sku']) ?></td>
                  <td class="p-3 font-body-sm text-body-sm text-on-surface max-w-xs">
                    <?= e((string) $data['name']) ?>
                    <?php if (!empty($res['warnings'])): ?>
                      <div class="text-[11px] text-[#8a5a00] mt-1">
                        <?php foreach ($res['warnings'] as $w): ?>
                          <div><?= $w /* already escaped by the bulk service */ ?></div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="p-3"><?= intake_method_pill($method) ?></td>
                  <td class="p-3">
                    <select name="assign[<?= $i ?>]"
                            class="w-44 px-2 py-1.5 border border-outline-variant rounded font-body-sm text-body-sm bg-surface focus:border-primary outline-none">
                      <option value="">auto<?= $auto !== [] ? ' - ' . count($auto) . ' photo(s)' : ' - none' ?></option>
                      <?php foreach ($match['leftover'] as $path): ?>
                        <option value="<?= e($path) ?>">
                          <?= e(basename($path)) ?><?= isset($captions[$path]) ? ' - ' . e(mb_substr($captions[$path], 0, 40)) : '' ?>
                        </option>
                      <?php endforeach; ?>
                      <option value="none">no image</option>
                    </select>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-3">
          <button type="submit"
                  class="px-5 py-2.5 rounded-lg bg-primary text-on-primary font-label-nav text-label-nav uppercase tracking-wider hover:opacity-90 transition">
            Send <?= count($results) ?> products to the Waiting Room
          </button>
          <span class="font-body-sm text-body-sm text-on-surface-variant">
            Nothing goes live until you commit it there.
          </span>
        </div>
      </form>

      <form method="post" action="<?= e(url('admin/intake.php')) ?>" class="mt-4"
            onsubmit="return confirm('Discard this batch and delete its staged photos?');">
        <input type="hidden" name="action" value="discard_batch"/>
        <input type="hidden" name="batch_id" value="<?= (int) $batch['id'] ?>"/>
        <button type="submit" class="font-label-nav text-label-nav uppercase tracking-wider text-error hover:underline">
          Discard this batch instead
        </button>
      </form>
    <?php endif; ?>

  <?php else:
    $batches = intake_batches(null, 25);
  ?>
    <div class="mb-6 p-4 rounded-lg bg-surface-container border border-outline-variant font-body-sm text-body-sm text-on-surface-variant">
      <strong class="text-on-surface">How a batch travels:</strong>
      1. client sends the sheet + photos to the bot &rarr;
      2. bot stages them here (photos are validated and stored in <code class="font-mono">storage/uploads</code>) &rarr;
      3. <em>Review</em> matches photos onto rows &rarr;
      4. <em>Send to Waiting Room</em> &rarr; 5. commit as usual in Bulk Products.
      Client should caption each photo (or album) with the product name - that is the matching signal.
    </div>

    <?php if ($batches === []): ?>
      <div class="p-6 rounded-lg border border-dashed border-outline-variant text-center font-body-sm text-body-sm text-on-surface-variant">
        Nothing has arrived yet. Message your bot on Telegram: send a product sheet, then photos.
        Use <code class="font-mono">/status</code> in the chat to see what is staged.
      </div>
    <?php else: ?>
      <div class="overflow-x-auto rounded-lg border border-outline-variant">
        <table class="w-full border-collapse">
          <thead>
            <tr class="bg-surface-container text-left">
              <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">#</th>
              <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">Status</th>
              <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">Sheet</th>
              <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">Photos</th>
              <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">Chat</th>
              <th class="p-3 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant">Received</th>
              <th class="p-3"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($batches as $b): ?>
              <tr class="border-t border-outline-variant align-top">
                <td class="p-3 font-mono text-xs text-on-surface-variant">#<?= (int) $b['id'] ?></td>
                <td class="p-3"><span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-0.5 rounded <?= $b['status'] === 'pending' ? 'bg-primary-fixed text-primary' : ($b['status'] === 'claimed' ? 'bg-secondary-fixed text-on-secondary-fixed' : 'border border-outline-variant text-on-surface-variant') ?>"><?= e((string) $b['status']) ?></span></td>
                <td class="p-3 font-body-sm text-body-sm text-on-surface">
                  <?php if ($b['sheet_name'] !== ''): ?>
                    <?= e((string) $b['sheet_name']) ?>
                    <span class="text-on-surface-variant">(<?= (int) $b['row_count'] ?> rows)</span>
                  <?php else: ?>
                    <span class="text-outline italic">no sheet yet</span>
                  <?php endif; ?>
                </td>
                <td class="p-3 font-mono text-xs text-on-surface"><?= (int) $b['photo_count'] ?></td>
                <td class="p-3 font-mono text-xs text-on-surface-variant"><?= (int) $b['chat_id'] ?></td>
                <td class="p-3 font-body-sm text-body-sm text-on-surface-variant"><?= e((string) $b['created_at']) ?></td>
                <td class="p-3 text-right whitespace-nowrap">
                  <?php if ($b['status'] === 'pending'): ?>
                    <?php if ($b['sheet_name'] !== ''): ?>
                      <a href="<?= e(url('admin/intake.php?review=' . (int) $b['id'])) ?>"
                         class="font-label-nav text-label-nav uppercase tracking-wider text-primary hover:underline">Review &amp; match</a>
                    <?php else: ?>
                      <span class="font-body-sm text-body-sm text-outline">waiting for sheet</span>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('admin/intake.php')) ?>" class="inline ml-3"
                          onsubmit="return confirm('Discard batch #<?= (int) $b['id'] ?> and its photos?');">
                      <input type="hidden" name="action" value="discard_batch"/>
                      <input type="hidden" name="batch_id" value="<?= (int) $b['id'] ?>"/>
                      <button type="submit" class="font-label-nav text-label-nav uppercase tracking-wider text-error hover:underline">discard</button>
                    </form>
                  <?php else: ?>
                    <span class="font-body-sm text-body-sm text-on-surface-variant">
                      <?= $b['status'] === 'claimed' ? 'sent to Waiting Room' : 'discarded' ?>
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php admin_foot();
