<?php
/**
 * Show Telegram's view of the webhook: pending updates, last delivery error.
 * CLI only - the fastest way to see why messages are not arriving.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/intake_service.php';

$cfg = intake_config();
if ($cfg['token'] === '') {
    fwrite(STDERR, "TG_BOT_TOKEN is not set in .env\n");
    exit(1);
}

$res = tg_api($cfg, 'getWebhookInfo');
if (!is_array($res) || empty($res['ok'])) {
    fwrite(STDERR, 'Telegram refused: ' . ($res['description'] ?? 'no response') . "\n");
    exit(1);
}

$r = $res['result'] ?? [];
echo 'URL:              ' . ($r['url'] ?? '(not set)') . "\n";
echo 'Has certificate:  ' . (!empty($r['has_custom_certificate']) ? 'yes' : 'no') . "\n";
echo 'Pending updates:  ' . (int) ($r['pending_update_count'] ?? 0) . "\n";
echo 'Allowed updates:  ' . implode(',', (array) ($r['allowed_updates'] ?? [])) . "\n";
echo 'Max connections:  ' . (int) ($r['max_connections'] ?? 0) . "\n";

$lastError = (string) ($r['last_error_message'] ?? '');
echo 'Last error:       ' . ($lastError !== '' ? $lastError : 'none') . "\n";
if (!empty($r['last_error_date'])) {
    echo 'Last error date:  ' . date('Y-m-d H:i:s', (int) $r['last_error_date']) . "\n";
}
$syncError = (string) ($r['last_synchronization_error_date'] ?? '');
if ($syncError !== '') {
    echo 'Last sync issue:  ' . date('Y-m-d H:i:s', (int) $syncError) . "\n";
}

if (($r['url'] ?? '') === '') {
    echo "\nNo webhook registered. Run: php tools/tg_set_webhook.php\n";
    exit(1);
}
if ($lastError !== '') {
    exit(1);
}
exit(0);
