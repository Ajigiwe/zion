<?php
/**
 * Point Telegram at this server's webhook (CLI only).
 *
 * Usage:
 *   php tools/tg_set_webhook.php                        # uses APP_URL/tg_webhook.php
 *   php tools/tg_set_webhook.php https://host/webhook.php
 *   php tools/tg_set_webhook.php --delete               # stop deliveries
 *   php tools/tg_set_webhook.php --info                 # same as tg_webhook_info.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/intake_service.php';

$cfg = intake_config();

$missing = [];
if ($cfg['token'] === '') {
    $missing[] = 'TG_BOT_TOKEN';
}
if ($cfg['secret'] === '') {
    $missing[] = 'TG_WEBHOOK_SECRET';
}
if ($missing !== []) {
    fwrite(STDERR, 'Missing in .env: ' . implode(', ', $missing) . "\n");
    fwrite(STDERR, "Generate a secret with: php -r \"echo bin2hex(random_bytes(16));\"\n");
    exit(1);
}
if (preg_match('/^[A-Za-z0-9_-]{1,256}$/', $cfg['secret']) !== 1) {
    fwrite(STDERR, 'TG_WEBHOOK_SECRET may only contain A-Z a-z 0-9 _ - (a hex string is safest).\n');
    exit(1);
}

$args   = array_slice($argv, 1);
$url    = '';
$delete = false;
$info   = false;
foreach ($args as $arg) {
    if ($arg === '--delete') {
        $delete = true;
    } elseif ($arg === '--info') {
        $info = true;
    } elseif (!str_starts_with($arg, '-') && $url === '') {
        $url = $arg;
    }
}

if ($info) {
    require __DIR__ . '/tg_webhook_info.php';
    exit(0);
}

if ($delete) {
    $res = tg_api($cfg, 'deleteWebhook');
    if (is_array($res) && !empty($res['ok'])) {
        echo "Webhook removed - Telegram is no longer delivering updates.\n";
        exit(0);
    }
    fwrite(STDERR, 'Telegram refused: ' . ($res['description'] ?? 'no response') . "\n");
    exit(1);
}

if ($url === '') {
    $url = rtrim(env('APP_URL'), '/') . '/tg_webhook.php';
}
if (!str_starts_with($url, 'https://')) {
    fwrite(STDERR, "Telegram needs an https URL, got: {$url}\n");
    fwrite(STDERR, "Pass one explicitly: php tools/tg_set_webhook.php https://yourdomain/tg_webhook.php\n");
    exit(1);
}

$res = tg_api($cfg, 'setWebhook', [
    'url'             => $url,
    'secret_token'    => $cfg['secret'],
    'allowed_updates' => ['message'],
]);

if (is_array($res) && !empty($res['ok'])) {
    echo "Webhook registered: {$url}\n";
    echo 'Secret token: matches TG_WEBHOOK_SECRET (' . strlen($cfg['secret']) . " chars, value not shown)\n";
    echo "Allowed updates: message only\n";
    echo "Check it with: php tools/tg_webhook_info.php\n";
    exit(0);
}

fwrite(STDERR, 'Telegram refused: ' . ($res['description'] ?? 'no response') . "\n");
exit(1);
