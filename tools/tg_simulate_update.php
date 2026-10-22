<?php
/**
 * Feed a fake Telegram update through the exact dispatch the webhook uses -
 * the local-development / test twin of tg_webhook.php (Telegram cannot reach
 * localhost, and no bot token is needed because the files come from disk).
 *
 * Usage:
 *   php tools/tg_simulate_update.php command "/status"        [--chat 12345]
 *   php tools/tg_simulate_update.php text "hello there"       [--chat 12345]
 *   php tools/tg_simulate_update.php sheet path/products.csv  [--chat 12345]
 *   php tools/tg_simulate_update.php sheet path/products.xlsx [--chat 12345]
 *   php tools/tg_simulate_update.php photo path/img.jpg --caption "Aura Bodysuit" \
 *                                          [--album g1] [--message-id 42] [--chat 12345]
 *
 * --chat must be in TG_ALLOWED_CHAT_IDS unless you pass --force (which then
 * prints a warning - production would silently ignore that chat).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/intake_service.php';
require_once dirname(__DIR__) . '/includes/intake_xlsx.php';

function sim_arg(array $args, string $name, string $default = ''): string
{
    $n = count($args);
    for ($i = 0; $i < $n; $i++) {
        if ($args[$i] === $name && $i + 1 < $n) {
            return $args[$i + 1];
        }
    }
    return $default;
}

$args  = array_slice($argv, 1);
$force = in_array('--force', $args, true);
// collect positionals, skipping flags and the value that follows each one
$positionals = [];
for ($i = 0; $i < count($args); $i++) {
    if (str_starts_with($args[$i], '--')) {
        if (in_array($args[$i], ['--chat', '--album', '--message-id', '--caption'], true)) {
            $i++;
        }
        continue;
    }
    $positionals[] = $args[$i];
}

$kind = (string) ($positionals[0] ?? '');
if ($kind === '') {
    fwrite(STDERR, "Usage: php tools/tg_simulate_update.php <command|text|sheet|photo> [args] [--chat ID]\n");
    exit(1);
}

$cfg     = intake_config();
$chatRaw = sim_arg($args, '--chat');
$chatId  = $chatRaw !== '' ? (int) $chatRaw : (int) ($cfg['chats'][0] ?? 0);
if ($chatId === 0) {
    fwrite(STDERR, "No chat id. Add TG_ALLOWED_CHAT_IDS=<your numeric id> to .env, or pass --chat 12345\n");
    fwrite(STDERR, "(get your id from @userinfobot on Telegram)\n");
    exit(1);
}
$whitelisted = in_array($chatId, $cfg['chats'], true);
if (!$whitelisted && !$force) {
    fwrite(STDERR, "chat {$chatId} is not in TG_ALLOWED_CHAT_IDS - dispatch will ignore it. Use --force to run anyway.\n");
    exit(1);
}
if (!$whitelisted && $force) {
    echo "WARNING: chat {$chatId} is NOT whitelisted - running with --force (production would drop this).\n";
}

$localFiles = [];
$messageId  = (int) sim_arg($args, '--message-id', (string) (int) round(microtime(true) * 1000));

$msg = ['message_id' => $messageId, 'chat' => ['id' => $chatId]];

switch ($kind) {
    case 'command':
    case 'text':
        $msg['text'] = (string) ($positionals[1] ?? '/status');
        break;

    case 'sheet':
        $path = (string) ($positionals[1] ?? '');
        $bytes = $path !== '' && is_file($path) ? file_get_contents($path) : false;
        if ($bytes === false) {
            fwrite(STDERR, "File not found: {$path}\n");
            exit(1);
        }
        $fileId = 'local-sheet-' . $messageId;
        $localFiles[$fileId] = $bytes;
        $msg['document'] = [
            'file_id'   => $fileId,
            'file_name' => basename($path),
            'file_size' => strlen($bytes),
        ];
        break;

    case 'photo':
        $path  = (string) ($positionals[1] ?? '');
        $bytes = $path !== '' && is_file($path) ? file_get_contents($path) : false;
        if ($bytes === false) {
            fwrite(STDERR, "File not found: {$path}\n");
            exit(1);
        }
        $fileId = 'local-photo-' . $messageId;
        $localFiles[$fileId] = $bytes;
        $msg['photo'] = [['file_id' => $fileId, 'width' => 0, 'height' => 0]];
        $cap = sim_arg($args, '--caption');
        if ($cap !== '') {
            $msg['caption'] = $cap;
        }
        $album = sim_arg($args, '--album');
        if ($album !== '') {
            $msg['media_group_id'] = $album;
        }
        break;

    default:
        fwrite(STDERR, "Unknown kind '{$kind}' - use command, text, sheet or photo.\n");
        exit(1);
}

$reply = intake_dispatch($cfg, $msg, $localFiles);

echo "update: {$kind} (chat {$chatId}, message_id {$messageId})\n";
if (!$whitelisted) {
    echo "result: ignored (not whitelisted)\n";
} elseif ($reply !== '') {
    echo "bot would reply:\n---\n{$reply}\n---\n";
} else {
    echo "result: accepted, silent\n";
}

$batch = intake_active_batch($chatId);
if ($batch !== null) {
    echo sprintf(
        "pending batch #%d: sheet=%s rows=%d photos=%d\n",
        (int) $batch['id'],
        $batch['sheet_name'] !== '' ? $batch['sheet_name'] : '-',
        (int) $batch['row_count'],
        intake_photo_count((int) $batch['id'])
    );
} else {
    echo "pending batch: none\n";
}
exit(0);
