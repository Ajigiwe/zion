<?php
/** Local image library: accepts one image upload and stores it in storage/uploads. */

declare(strict_types=1);
require_once __DIR__ . '/_layout.php';

header('Content-Type: application/json; charset=utf-8');

function upload_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    upload_fail(405, 'Uploads must be sent with POST.');
}

require_admin();

$token = $_POST['csrf'] ?? '';
if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
    upload_fail(419, 'Your session token expired. Refresh the page and try again.');
}

$file = $_FILES['file'] ?? null;
if (!is_array($file)) {
    upload_fail(400, 'No file was received.');
}

$err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($err !== UPLOAD_ERR_OK) {
    upload_fail(400, match ($err) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That image is larger than the server allows.',
        UPLOAD_ERR_PARTIAL                        => 'The upload was interrupted - please try again.',
        default                                   => 'The upload failed (error ' . $err . ').',
    });
}

$tmp = (string) ($file['tmp_name'] ?? '');
if ($tmp === '' || !is_uploaded_file($tmp)) {
    upload_fail(400, 'That file did not arrive as an upload.');
}
if ((int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
    upload_fail(413, 'Images must be 8 MB or smaller.');
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    'image/avif' => 'avif',
    'image/bmp'  => 'bmp',
];
$mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
if (!isset($allowed[$mime])) {
    upload_fail(415, 'Upload a JPG, PNG, WebP, GIF, AVIF or BMP image.');
}
$info = @getimagesize($tmp);
if ($info === false) {
    upload_fail(415, 'That file is not a readable image.');
}

$dir = dirname(__DIR__) . '/storage/uploads';
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    upload_fail(500, 'The storage/uploads folder could not be created.');
}

$name = gmdate('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
    upload_fail(500, 'The upload could not be saved to storage/uploads.');
}
@chmod($dir . '/' . $name, 0644);

$rel = 'storage/uploads/' . $name;
echo json_encode([
    'ok'     => true,
    'path'   => $rel,
    'url'    => url($rel),
    'mime'   => $mime,
    'bytes'  => (int) ($file['size'] ?? 0),
    'width'  => (int) $info[0],
    'height' => (int) $info[1],
]);
