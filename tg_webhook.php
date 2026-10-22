<?php
/**
 * Telegram webhook - receives product batches (spreadsheet + photos) from
 * whitelisted chats and stages them for the admin intake review.
 *
 * Security model:
 *   - 503 until TG_WEBHOOK_SECRET exists in .env (an unconfigured endpoint
 *     refuses rather than making Telegram retry for hours)
 *   - 401 unless X-Telegram-Bot-Api-Secret-Token matches TG_WEBHOOK_SECRET
 *     (that header is set by tools/tg_set_webhook.php when Telegram registers
 *     this URL, so only Telegram itself can POST here)
 *   - updates from any other chat_id are acknowledged and dropped
 *   - nothing here publishes: committing stays in the admin panel behind CSRF
 *
 * A missing TG_BOT_TOKEN is not fatal here: replies and downloads fail
 * silently through tg_api(), which is friendlier than a retry loop.
 *
 * CLI equivalent (for local development): tools/tg_simulate_update.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/intake_service.php';
require_once __DIR__ . '/includes/intake_xlsx.php';

header('Content-Type: application/json; charset=utf-8');

$cfg = intake_config();
if ($cfg['secret'] === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'not configured']);
    exit;
}

$given = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
if ($given === '' || !hash_equals($cfg['secret'], $given)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$update = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($update)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad json']);
    exit;
}

// Whitelist, ingest, matching handoff - all shared with the CLI simulator.
$reply = intake_dispatch($cfg, $update);
if ($reply !== '') {
    tg_send_text($cfg, (int) ($update['message']['chat']['id'] ?? 0), $reply);
}

// Always 200 once authenticated: a silent no-op must not make Telegram retry.
echo json_encode(['ok' => true]);
