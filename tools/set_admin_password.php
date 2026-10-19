<?php
/**
 * Set or reset a user's password from the command line.
 *
 *   php tools/set_admin_password.php <email>               (prompts twice)
 *   php tools/set_admin_password.php <email> <password>    (avoid: shell history)
 *
 * Why this exists: this repository is public, so seed.sql cannot ship a
 * password anybody could read. A fresh import therefore creates accounts with
 * random, unknown passwords - run this once to choose one you know.
 *
 * An email that does not exist yet is created as an admin, which is how a
 * brand new install bootstraps its first administrator.
 *
 * CLI only: /tools/ is denied by .htaccess (only doctor.php is web-reachable),
 * and the SAPI guard below is the second lock on that door.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config.php';

function ask(string $label): string
{
    echo $label;
    $line = fgets(STDIN);
    return rtrim((string) $line, "\r\n");
}

$email = trim((string) ($argv[1] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tools/set_admin_password.php <email> [password]\n");
    exit(2);
}

$pw = (string) ($argv[2] ?? '');

if ($pw !== '') {
    fwrite(STDERR, "note: an argument on the command line stays in your shell history - prefer the prompt.\n");
} else {
    // Only POSIX shells have stty; on Windows the prompt echoes (dev machine).
    $hide = DIRECTORY_SEPARATOR === '/' && trim((string) @shell_exec('stty -g 2>/dev/null')) !== '';
    if ($hide) {
        @shell_exec('stty -echo 2>/dev/null');
    }
    $first  = ask('New password: ');
    $second = ask('Repeat password: ');
    if ($hide) {
        @shell_exec('stty echo 2>/dev/null');
        echo "\n";
    }
    if ($first !== $second) {
        fwrite(STDERR, "The two passwords did not match.\n");
        exit(1);
    }
    $pw = $first;
}

if (strlen($pw) < 8) {
    fwrite(STDERR, "Passwords must be at least 8 characters.\n");
    exit(1);
}

$hash = password_hash($pw, PASSWORD_DEFAULT);
$user = db_one('SELECT id, role, is_active FROM users WHERE email = ?', [$email]);

if ($user !== null) {
    db_exec('UPDATE users SET password_hash = ?, is_active = 1 WHERE id = ?', [$hash, (int) $user['id']]);
    printf(
        "Password updated for %s (id %d, role %s%s).\n",
        $email,
        (int) $user['id'],
        (string) $user['role'],
        (int) $user['is_active'] === 1 ? '' : ', re-activated'
    );
} else {
    // Bootstrap case: schema imported, seed not (or seed without a usable login).
    $name = ucwords(str_replace(['.', '_', '-'], ' ', substr($email, 0, (int) strpos($email, '@'))));
    db_exec(
        'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)',
        [$name !== '' ? $name : 'Administrator', $email, $hash, 'admin']
    );
    $row = db_one('SELECT id, role FROM users WHERE email = ?', [$email]);
    printf("CREATED %s as a new %s account (id %s).\n", $email, (string) ($row['role'] ?? 'admin'), (string) ($row['id'] ?? '?'));
}

echo "Verify with: php tools/doctor.php  (look for the 'admin password' row)\n";
