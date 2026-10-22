<?php
/**
 * Telegram product intake - ingestion service.
 *
 * tg_webhook.php validates the request and hands the raw update to
 * intake_dispatch(); everything below stages the spreadsheet and photos from
 * an approved Telegram chat into intake_batches / intake_items. The admin
 * panel (admin/intake.php) matches those photos onto the CSV rows and hands
 * the result to the existing Bulk Products Waiting Room - committing to the
 * live catalogue stays an admin action behind CSRF, never a bot action.
 *
 * The tables are ensured lazily the same way login_attempts is, so an
 * existing install picks them up without re-importing schema.sql.
 */

declare(strict_types=1);

require_once __DIR__ . '/product_bulk_service.php';

const INTAKE_MAX_PHOTOS      = 400;
const INTAKE_MAX_IMAGE_BYTES = 8 * 1024 * 1024;   // same cap as admin/upload.php
const INTAKE_MAX_SHEET_BYTES = 2 * 1024 * 1024;

/* ------------------------------------------------------------- tables ---- */

function intake_tables(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;
    db_exec("CREATE TABLE IF NOT EXISTS `intake_batches` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chat_id`    BIGINT NOT NULL,
  `label`      VARCHAR(160) NOT NULL DEFAULT '',
  `sheet_name` VARCHAR(190) NOT NULL DEFAULT '',
  `sheet_data` MEDIUMTEXT NULL,
  `row_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `status`     ENUM('pending','claimed','discarded') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_intake_status` (`status`,`chat_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    db_exec("CREATE TABLE IF NOT EXISTS `intake_items` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id`    BIGINT UNSIGNED NOT NULL,
  `message_id`  BIGINT UNSIGNED NOT NULL,
  `media_group` VARCHAR(64) NOT NULL DEFAULT '',
  `file_path`   VARCHAR(255) NOT NULL,
  `caption`     VARCHAR(512) NOT NULL DEFAULT '',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_intake_batch_message` (`batch_id`,`message_id`),
  KEY `idx_intake_batch_order` (`batch_id`,`message_id`),
  CONSTRAINT `fk_intake_batch` FOREIGN KEY (`batch_id`)
    REFERENCES `intake_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/* ----------------------------------------------------------- configure --- */

/** Bot token, webhook secret and the whitelist of approved chat ids. */
function intake_config(): array
{
    $chats = [];
    foreach (explode(',', env('TG_ALLOWED_CHAT_IDS')) as $one) {
        $one = trim($one);
        if ($one !== '' && preg_match('/^-?\d+$/', $one) === 1) {
            $chats[] = (int) $one;
        }
    }
    return [
        'token'  => trim(env('TG_BOT_TOKEN')),
        'secret' => trim(env('TG_WEBHOOK_SECRET')),
        'chats'  => $chats,
    ];
}

function intake_is_configured(): bool
{
    $cfg = intake_config();
    return $cfg['token'] !== '' && $cfg['secret'] !== '' && $cfg['chats'] !== [];
}

/* --------------------------------------------------------- telegram api -- */

/**
 * Call one Telegram Bot API method. Returns the decoded body, or null when
 * the call failed (never throws - a dead network must not break a webhook).
 */
function tg_api(array $cfg, string $method, array $params = []): ?array
{
    if ($cfg['token'] === '') {
        return null;   // unconfigured: stay offline, make no outbound call
    }
    $url     = 'https://api.telegram.org/bot' . $cfg['token'] . '/' . $method;
    $payload = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return null;
    }
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n",
        'content' => $payload,
        'timeout' => 12,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        error_log('tg_api: ' . $method . ' request failed');
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

/** Best-effort chat reply. Failures are logged, never fatal. */
function tg_send_text(array $cfg, int $chatId, string $text): bool
{
    $res = tg_api($cfg, 'sendMessage', [
        'chat_id'                 => $chatId,
        'text'                    => $text,
        'disable_web_page_preview' => true,
    ]);
    return is_array($res) && !empty($res['ok']);
}

/** Download a file, aborting as soon as $maxBytes is exceeded. */
function tg_download(string $url, int $maxBytes): ?string
{
    $in = @fopen($url, 'rb', false, stream_context_create(['http' => ['timeout' => 45]]));
    if ($in === false) {
        return null;
    }
    $buf = '';
    while (!feof($in)) {
        $chunk = fread($in, 65536);
        if ($chunk === false) {
            break;
        }
        $buf .= $chunk;
        if (strlen($buf) > $maxBytes) {
            fclose($in);
            return null;
        }
    }
    fclose($in);
    return $buf === '' ? null : $buf;
}

/* -------------------------------------------------------------- batches -- */

function intake_active_batch_id(int $chatId): ?int
{
    intake_tables();
    $row = db_one(
        "SELECT id FROM intake_batches WHERE chat_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
        [$chatId]
    );
    return $row === null ? null : (int) $row['id'];
}

function intake_active_batch(int $chatId): ?array
{
    intake_tables();
    return db_one(
        "SELECT * FROM intake_batches WHERE chat_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
        [$chatId]
    );
}

function intake_new_batch(int $chatId, string $label): int
{
    intake_tables();
    db_exec(
        "INSERT INTO intake_batches (chat_id, label, created_at, updated_at) VALUES (?, ?, NOW(), NOW())",
        [$chatId, mb_substr(trim($label), 0, 160)]
    );
    return (int) db_val('SELECT LAST_INSERT_ID()', [], 0);
}

function intake_batch(int $batchId): ?array
{
    intake_tables();
    return db_one('SELECT * FROM intake_batches WHERE id = ?', [$batchId]);
}

/** Newest first. With $status, only batches in that state. */
function intake_batches(?string $status = null, int $limit = 25): array
{
    intake_tables();
    $limit = max(1, min(200, $limit));
    $sql   = 'SELECT b.*, COUNT(i.id) AS photo_count
                FROM intake_batches b
                LEFT JOIN intake_items i ON i.batch_id = b.id';
    $params = [];
    if ($status !== null) {
        $sql .= ' WHERE b.status = ?';
        $params[] = $status;
    }
    $sql .= ' GROUP BY b.id ORDER BY b.id DESC LIMIT ' . $limit;
    return db_all($sql, $params);
}

/** Cheap enough for the sidebar badge: no CREATE TABLE, missing table = 0. */
function intake_pending_count(): int
{
    try {
        return (int) db_val("SELECT COUNT(*) FROM intake_batches WHERE status = 'pending'", [], 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function intake_items(int $batchId): array
{
    intake_tables();
    return db_all('SELECT * FROM intake_items WHERE batch_id = ? ORDER BY message_id ASC, id ASC', [$batchId]);
}

function intake_photo_count(int $batchId): int
{
    intake_tables();
    return (int) db_val('SELECT COUNT(*) FROM intake_items WHERE batch_id = ?', [$batchId], 0);
}

function intake_sheet_name(int $batchId): string
{
    intake_tables();
    return (string) db_val('SELECT sheet_name FROM intake_batches WHERE id = ?', [$batchId], '');
}

function intake_set_sheet(int $batchId, string $name, string $csv, int $rows): void
{
    intake_tables();
    db_exec(
        'UPDATE intake_batches SET sheet_name = ?, sheet_data = ?, row_count = ?, updated_at = NOW() WHERE id = ?',
        [mb_substr($name, 0, 190), $csv, $rows, $batchId]
    );
}

function intake_claim(int $batchId): void
{
    intake_tables();
    db_exec("UPDATE intake_batches SET status = 'claimed', updated_at = NOW() WHERE id = ?", [$batchId]);
}

/** Discard a batch and delete the photos staged for it (not yet published). */
function intake_discard(int $batchId): int
{
    intake_tables();
    $removed = 0;
    $root    = dirname(__DIR__);
    foreach (intake_items($batchId) as $item) {
        $rel = (string) $item['file_path'];
        if (str_starts_with($rel, 'storage/uploads/') && is_file($root . '/' . $rel)) {
            if (@unlink($root . '/' . $rel)) {
                $removed++;
            }
        }
    }
    db_exec("UPDATE intake_batches SET status = 'discarded', updated_at = NOW() WHERE id = ?", [$batchId]);
    return $removed;
}

/** Has this Telegram message already been stored (webhook retries happen)? */
function intake_message_seen(int $chatId, int $messageId): bool
{
    intake_tables();
    $n = (int) db_val(
        'SELECT COUNT(*) FROM intake_items i
           JOIN intake_batches b ON b.id = i.batch_id
          WHERE b.chat_id = ? AND i.message_id = ?',
        [$chatId, $messageId],
        0
    );
    return $n > 0;
}

/* --------------------------------------------------------------- ingest -- */

/** Validate like admin/upload.php and store into storage/uploads. */
function intake_save_image(string $bytes): ?string
{
    if ($bytes === '' || strlen($bytes) > INTAKE_MAX_IMAGE_BYTES) {
        return null;
    }
    static $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'image/avif' => 'avif',
        'image/bmp'  => 'bmp',
    ];
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    if (!isset($allowed[$mime])) {
        return null;
    }
    if (@getimagesizefromstring($bytes) === false) {
        return null;
    }
    $dir = dirname(__DIR__) . '/storage/uploads';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }
    $name = gmdate('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (@file_put_contents($dir . '/' . $name, $bytes) === false) {
        return null;
    }
    @chmod($dir . '/' . $name, 0644);
    return 'storage/uploads/' . $name;
}

/** Largest available size of a Telegram photo, downloaded. */
function intake_photo_bytes(array $cfg, array $msg): ?string
{
    $sizes = $msg['photo'] ?? null;
    if (!is_array($sizes) || $sizes === []) {
        return null;
    }
    $biggest = null;
    foreach ($sizes as $one) {
        if (is_array($one) && isset($one['file_id'])) {
            $biggest = $one;   // Telegram lists sizes smallest -> largest
        }
    }
    if ($biggest === null || $cfg['token'] === '') {
        return null;
    }
    $info     = tg_api($cfg, 'getFile', ['file_id' => (string) $biggest['file_id']]);
    $filePath = (string) ($info['result']['file_path'] ?? '');
    if ($filePath === '') {
        return null;
    }
    return tg_download(
        'https://api.telegram.org/file/bot' . $cfg['token'] . '/' . $filePath,
        INTAKE_MAX_IMAGE_BYTES
    );
}

/**
 * Stage one photo message into the chat's pending batch.
 * $bytes lets the simulator and the tests inject a file without Telegram.
 *
 * @return array{ok: bool, path?: ?string, dup?: bool, reply?: string}
 */
function intake_ingest_photo(array $cfg, int $chatId, array $msg, ?string $bytes = null): array
{
    intake_tables();

    $messageId = (int) ($msg['message_id'] ?? 0);
    if ($messageId > 0 && intake_message_seen($chatId, $messageId)) {
        return ['ok' => true, 'dup' => true, 'path' => null];
    }

    $batchId = intake_active_batch_id($chatId);
    if ($batchId === null) {
        $batchId = intake_new_batch($chatId, 'Photos from Telegram');
    }
    if (intake_photo_count($batchId) >= INTAKE_MAX_PHOTOS) {
        return ['ok' => false, 'reply' => sprintf(
            'This batch already holds %d photos. Open the shop admin to process it, then /clear to start fresh.',
            INTAKE_MAX_PHOTOS
        )];
    }

    if ($bytes === null) {
        $bytes = intake_photo_bytes($cfg, $msg);
    }
    if ($bytes === null) {
        return ['ok' => false, 'reply' => 'I could not download that photo. Send it again, or send it as a document (file) to keep full quality.'];
    }
    $path = intake_save_image($bytes);
    if ($path === null) {
        return ['ok' => false, 'reply' => 'That file is not a storable image (JPG, PNG, WebP, GIF, AVIF or BMP up to 8 MB).'];
    }

    try {
        db_exec(
            'INSERT INTO intake_items (batch_id, message_id, media_group, file_path, caption, created_at)
             VALUES (?,?,?,?,?,NOW())',
            [
                $batchId,
                $messageId > 0 ? $messageId : (int) round(microtime(true) * 1000),
                mb_substr((string) ($msg['media_group_id'] ?? ''), 0, 64),
                $path,
                mb_substr(trim((string) ($msg['caption'] ?? '')), 0, 512),
            ]
        );
    } catch (PDOException $e) {
        // duplicate (batch_id,message_id): a Telegram retry - drop our copy
        @unlink(dirname(__DIR__) . '/' . $path);
        return ['ok' => true, 'dup' => true, 'path' => null];
    } catch (Throwable $e) {
        @unlink(dirname(__DIR__) . '/' . $path);
        error_log('intake: could not store photo - ' . $e->getMessage());
        return ['ok' => false, 'reply' => 'The photo could not be saved on the server (storage/uploads permissions?).'];
    }

    return ['ok' => true, 'path' => $path, 'batch_id' => $batchId];
}

/** Make sure the sheet bytes really are CSV text (Excel often saves ANSI). */
function intake_normalize_csv(string $bytes): string
{
    if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
        $bytes = substr($bytes, 3);
    }
    if (!mb_check_encoding($bytes, 'UTF-8')) {
        $bytes = mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
    }
    return $bytes;
}

/**
 * Stage a .csv / .xlsx document into the chat's pending batch (creating one,
 * or a fresh batch when the current one already holds a sheet).
 *
 * @return array{ok: bool, rows?: int, name?: string, batch_id?: int, photos_pending?: int, error?: string}
 */
function intake_ingest_sheet(array $cfg, int $chatId, array $doc, ?string $bytes = null): array
{
    intake_tables();

    $name = mb_substr(trim((string) ($doc['file_name'] ?? 'sheet')), 0, 190) ?: 'sheet';
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx'], true)) {
        return ['ok' => false, 'error' => 'Send the sheet as .csv or .xlsx (in Excel: File > Save As > CSV UTF-8).'];
    }
    if ((int) ($doc['file_size'] ?? 0) > INTAKE_MAX_SHEET_BYTES) {
        return ['ok' => false, 'error' => 'That file is over 2 MB - a product sheet should be much smaller.'];
    }

    if ($bytes === null) {
        if ($cfg['token'] === '') {
            return ['ok' => false, 'error' => 'I could not download that file (bot not configured).'];
        }
        $info     = tg_api($cfg, 'getFile', ['file_id' => (string) ($doc['file_id'] ?? '')]);
        $filePath = (string) ($info['result']['file_path'] ?? '');
        if ($filePath === '') {
            return ['ok' => false, 'error' => 'I could not download that file - send it again.'];
        }
        $bytes = tg_download(
            'https://api.telegram.org/file/bot' . $cfg['token'] . '/' . $filePath,
            INTAKE_MAX_SHEET_BYTES
        );
        if ($bytes === null) {
            return ['ok' => false, 'error' => 'I could not download that file - send it again.'];
        }
    }

    if ($ext === 'csv') {
        $csv = intake_normalize_csv($bytes);
    } else {
        $tmp = tempnam(sys_get_temp_dir(), 'tgx');
        if ($tmp === false || file_put_contents($tmp, $bytes) === false) {
            return ['ok' => false, 'error' => 'That .xlsx could not be opened. Re-save it and send again.'];
        }
        try {
            $csv = intake_xlsx_to_csv($tmp);
        } catch (Throwable $e) {
            @unlink($tmp);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        @unlink($tmp);
    }

    $parsed = bulk_parse_csv_string($csv);
    if (isset($parsed['error']) || $parsed['rows'] === []) {
        return ['ok' => false, 'error' => $parsed['error']
            ?? 'No product rows found. The first row must be the header (sku,name,price,...).'];
    }

    $batchId = intake_active_batch_id($chatId);
    if ($batchId === null || intake_sheet_name($batchId) !== '') {
        $batchId = intake_new_batch($chatId, $name);
    }
    intake_set_sheet($batchId, $name, $csv, count($parsed['rows']));

    return [
        'ok'            => true,
        'rows'          => count($parsed['rows']),
        'name'          => $name,
        'batch_id'      => $batchId,
        'photos_pending' => intake_photo_count($batchId),
    ];
}

/* ------------------------------------------------------------ dispatch --- */

/**
 * Handle one raw Telegram update.
 * Returns the reply text to send back (empty string = stay silent).
 * Only whitelisted chats can cause any work; every other chat is a no-op, so
 * strangers cannot probe the endpoint or fill storage/uploads.
 *
 * $localFiles maps a file_id to bytes that were NOT downloaded from Telegram
 * - only the CLI simulator and the tests use it, the webhook passes nothing.
 */
function intake_dispatch(array $cfg, array $update, array $localFiles = []): string
{
    $msg = $update['message'] ?? null;
    if (!is_array($msg)) {
        return '';   // edited messages, callbacks, polls: nothing to do
    }

    $chatId = (int) ($msg['chat']['id'] ?? 0);
    if ($chatId === 0 || !in_array($chatId, $cfg['chats'], true)) {
        return '';
    }

    $text = trim((string) ($msg['text'] ?? ''));
    if ($text !== '' && $text[0] === '/') {
        return intake_command_reply($cfg, $chatId, $text);
    }

    if (isset($msg['document'])) {
        $local = $localFiles[(string) ($msg['document']['file_id'] ?? '')] ?? null;
        $res   = intake_ingest_sheet($cfg, $chatId, $msg['document'], $local);
        if (!$res['ok']) {
            return (string) ($res['error'] ?? 'That sheet could not be read.');
        }
        $tail = $res['photos_pending'] > 0
            ? ' Photos are already staged in the same batch.'
            : ' Now send the photos - one album per product, product name as the caption.';
        return sprintf('Sheet received: %d product rows from %s.%s', $res['rows'], $res['name'], $tail);
    }

    if (isset($msg['photo'])) {
        $sizes = is_array($msg['photo']) ? $msg['photo'] : [];
        $last  = null;
        foreach ($sizes as $one) {
            if (is_array($one)) {
                $last = $one;
            }
        }
        $local = $localFiles[(string) ($last['file_id'] ?? '')] ?? null;
        $res   = intake_ingest_photo($cfg, $chatId, $msg, $local);
        if (!$res['ok']) {
            return (string) ($res['reply'] ?? 'That photo could not be stored.');
        }
        return '';   // silent: batches arrive thirty photos at a time
    }

    return "I only take spreadsheets and photos.\n\n"
        . "1. Send the .csv or .xlsx product sheet\n"
        . "2. Then the photos - ideally one album per product, captioned with the product name\n\n"
        . "Commands: /status  /clear";
}

/** /start /help /status /clear - returns what the bot should say. */
function intake_command_reply(array $cfg, int $chatId, string $text): string
{
    $parts = preg_split('/\s+/', trim($text)) ?: [''];
    $cmd   = strtolower(explode('@', (string) ($parts[0] ?? ''), 2)[0]);

    switch ($cmd) {
        case '/start':
        case '/help':
            return "Zion product intake.\n\n"
                . "1. Send the .csv or .xlsx product sheet\n"
                . "2. Send the photos - one album per product, captioned with the product name "
                . "(the caption is what matches photo to row)\n"
                . "3. In the shop admin open Telegram Intake > Review > Send to Waiting Room, "
                . "check the thumbnails, then commit.\n\n"
                . "/status - what is staged\n/clear - throw the current batch away";

        case '/status':
            $batch = intake_active_batch($chatId);
            if ($batch === null) {
                return 'Nothing staged yet. Send a sheet or photos and I will hold them for the admin.';
            }
            $sheet = $batch['sheet_name'] !== ''
                ? sprintf('%s (%d rows)', $batch['sheet_name'], (int) $batch['row_count'])
                : 'no sheet yet';
            return sprintf(
                "Staged batch #%d\nSheet: %s\nPhotos: %d\n\nOpen the shop admin > Telegram Intake to match and publish.",
                (int) $batch['id'],
                $sheet,
                intake_photo_count((int) $batch['id'])
            );

        case '/clear':
            $batch = intake_active_batch($chatId);
            if ($batch === null) {
                return 'Nothing to clear.';
            }
            $photos = intake_photo_count((int) $batch['id']);
            intake_discard((int) $batch['id']);
            return sprintf('Discarded batch #%d (%d photo(s) removed).', (int) $batch['id'], $photos);

        default:
            return 'Commands: /status, /clear, /help';
    }
}
