<?php
/**
 * Mail transport. MAIL_MAILER picks the driver:
 *   log  - append to storage/mail.log (development default, always works)
 *   php  - PHP's mail() / sendmail
 *   smtp - direct SMTP submission (MAIL_HOST / MAIL_USER / MAIL_PASS / MAIL_ENCRYPT)
 */

declare(strict_types=1);

/** Append a message to the on-disk mail log. */
function mail_log(string $to, string $subject, string $body): bool
{
    $dir = __DIR__ . '/../storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $entry = sprintf(
        "===== %s =====\nTo: %s\nSubject: %s\n\n%s\n\n",
        date('Y-m-d H:i:s'),
        $to,
        $subject,
        $body
    );
    return @file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
}

/** Read one SMTP server reply line group; throws on unexpected status. */
function smtp_read($sock): string
{
    $out = '';
    while (($line = fgets($sock, 515)) !== false) {
        $out .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $out;
}

function smtp_expect($sock, string $expectPrefix): void
{
    $reply = smtp_read($sock);
    if (!str_starts_with($reply, $expectPrefix)) {
        throw new RuntimeException('SMTP: expected ' . $expectPrefix . ', got ' . trim($reply));
    }
}

/** Minimal SMTP submission with optional STARTTLS + AUTH LOGIN. */
function smtp_send(string $to, string $subject, string $body, bool $isHtml): bool
{
    if (MAIL_HOST === '') {
        return mail_log($to, $subject, $body);
    }
    $secure = strtolower(MAIL_ENCRYPT);
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . MAIL_HOST;
    $sock = @fsockopen($remote, MAIL_PORT, $errno, $errstr, 15);
    if ($sock === false) {
        return mail_log($to, $subject, $body . "\n\n[SMTP connect failed: $errstr]");
    }
    stream_set_timeout($sock, 15);

    try {
        smtp_expect($sock, '220');
        $host = gethostname() ?: 'zion';
        fwrite($sock, "EHLO $host\r\n");
        smtp_expect($sock, '250');

        if ($secure === 'tls') {
            fwrite($sock, "STARTTLS\r\n");
            smtp_expect($sock, '220');
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP: STARTTLS negotiation failed');
            }
            fwrite($sock, "EHLO $host\r\n");
            smtp_expect($sock, '250');
        }

        if (MAIL_USER !== '') {
            fwrite($sock, "AUTH LOGIN\r\n");
            smtp_expect($sock, '334');
            fwrite($sock, base64_encode(MAIL_USER) . "\r\n");
            smtp_expect($sock, '334');
            fwrite($sock, base64_encode(MAIL_PASS) . "\r\n");
            smtp_expect($sock, '235');
        }

        fwrite($sock, 'MAIL FROM:<' . MAIL_FROM . ">\r\n");
        smtp_expect($sock, '250');
        fwrite($sock, 'RCPT TO:<' . $to . ">\r\n");
        smtp_expect($sock, '250');
        fwrite($sock, "DATA\r\n");
        smtp_expect($sock, '354');

        $headers = 'From: ' . MIMEEncodeName(MAIL_FROM_NAME) . ' <' . MAIL_FROM . ">\r\n"
                 . 'To: <' . $to . ">\r\n"
                 . 'Subject: ' . MIMEEncodeName($subject) . "\r\n"
                 . 'Date: ' . date('r') . "\r\n"
                 . 'MIME-Version: 1.0' . "\r\n"
                 . ($isHtml
                     ? "Content-Type: text/html; charset=UTF-8\r\n"
                     : "Content-Type: text/plain; charset=UTF-8\r\n")
                 . "\r\n";

        // dot-stuffing per RFC 5321
        $data = preg_replace('/^\./m', '..', $body);
        fwrite($sock, $headers . $data . "\r\n.\r\n");
        smtp_expect($sock, '250');
        fwrite($sock, "QUIT\r\n");
        fclose($sock);
        return true;
    } catch (Throwable $e) {
        @fclose($sock);
        return mail_log($to, $subject, $body . "\n\n[SMTP failed: " . $e->getMessage() . ']');
    }
}

/** RFC 2047 encode a header value when it is not plain ASCII. */
function MIMEEncodeName(string $value): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $value)) {
        return $value;
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

/**
 * Send a message through the configured transport.
 * Returns true when the message left the app (or was logged in dev).
 */
function send_mail(string $to, string $subject, string $body, bool $isHtml = false): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    return match (MAIL_MAILER) {
        'php'   => @mail(
            $to,
            MIMEEncodeName($subject),
            $body,
            'From: ' . MIMEEncodeName(MAIL_FROM_NAME) . ' <' . MAIL_FROM . ">\r\n"
            . ($isHtml ? "Content-Type: text/html; charset=UTF-8\r\n" : "Content-Type: text/plain; charset=UTF-8\r\n")
        ),
        'smtp'  => smtp_send($to, $subject, $body, $isHtml),
        default => mail_log($to, $subject, $body),
    };
}

/** Notify the operations inbox about products at or below the stock threshold. */
function send_low_stock_alert(array $products, string $reason = 'Stock check'): bool
{
    if ($products === [] || ADMIN_EMAIL === '') {
        return false;
    }
    $lines = [];
    foreach ($products as $p) {
        $lines[] = sprintf(
            '- %s (SKU %s) - %d left%s',
            $p['name'],
            $p['sku'] ?? 'n/a',
            (int) $p['stock'],
            (int) $p['stock'] === 0 ? ' - OUT OF STOCK' : ''
        );
    }
    $subject = 'Low stock: ' . count($products) . ' product' . (count($products) === 1 ? '' : 's') . ' need reordering';
    $body    = $reason . " - " . date('d M Y, H:i') . "\n\n"
             . implode("\n", $lines) . "\n\n"
             . "Zion Groups of Companies - Retail Operations\n" . APP_URL . "/admin/products.php\n";
    return send_mail(ADMIN_EMAIL, $subject, $body);
}
