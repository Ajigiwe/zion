<?php
/**
 * Zion Groups of Companies - Backup, Restore & Data Wipe Service.
 *
 * Provides pure-PHP database export/import, media zip archiving,
 * automated pre-wipe/pre-restore safety snapshots, granular data wiping,
 * and secure file stream downloads.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/**
 * Return the absolute path to the secure backups directory.
 * Ensures the directory exists and is shielded from public HTTP access.
 */
function backup_storage_dir(): string
{
    $dir = dirname(__DIR__) . '/storage/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }

    // Shield against direct HTTP downloads of backup files
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        $content = "Deny from all\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n";
        @file_put_contents($htaccess, $content);
    }

    $indexHtml = $dir . '/index.html';
    if (!is_file($indexHtml)) {
        @file_put_contents($indexHtml, '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Forbidden</h1></body></html>');
    }

    return $dir;
}

/**
 * Format bytes into human readable format (KB, MB, GB).
 */
function backup_format_bytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * (int) $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Scan the backups directory and return sorted list of available backups.
 */
function backup_list_files(): array
{
    $dir = backup_storage_dir();
    $files = [];

    $entries = @scandir($dir) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.htaccess' || $entry === 'index.html') {
            continue;
        }
        $fullPath = $dir . '/' . $entry;
        if (!is_file($fullPath)) {
            continue;
        }

        $size = (int) @filesize($fullPath);
        $mtime = (int) @filemtime($fullPath);
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));

        $type = 'unknown';
        if (str_contains($entry, '_full_') || str_ends_with($entry, '.zip')) {
            $type = 'full';
        } elseif (str_contains($entry, '_db_') || str_ends_with($entry, '.sql')) {
            $type = 'database';
        } elseif (str_contains($entry, '_uploads_')) {
            $type = 'uploads';
        }

        $isSafety = str_starts_with($entry, 'safety_backup_');

        $files[] = [
            'filename'       => $entry,
            'path'           => $fullPath,
            'size'           => $size,
            'size_formatted' => backup_format_bytes($size),
            'timestamp'      => $mtime,
            'date_formatted' => date('M j, Y H:i:s', $mtime),
            'type'           => $type,
            'extension'      => $ext,
            'is_safety'      => $isSafety,
        ];
    }

    // Sort descending by timestamp (newest first)
    usort($files, static fn(array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);
    return $files;
}

/**
 * Get system and database summary statistics.
 */
function backup_system_stats(): array
{
    $pdo = db();

    // Database table row counts
    $tables = [
        'products', 'categories', 'brands', 'orders', 'order_items',
        'users', 'reviews', 'contact_messages', 'cart_items', 'settings'
    ];

    $counts = [];
    foreach ($tables as $t) {
        try {
            $counts[$t] = (int) db_val("SELECT COUNT(*) FROM `{$t}`", [], 0);
        } catch (Throwable $e) {
            $counts[$t] = 0;
        }
    }

    // Database disk size in MB
    $dbSize = 0;
    try {
        $st = $pdo->prepare('SELECT SUM(data_length + index_length) AS size FROM information_schema.TABLES WHERE table_schema = ?');
        $st->execute([DB_NAME]);
        $row = $st->fetch();
        $dbSize = (int) ($row['size'] ?? 0);
    } catch (Throwable $e) {
        $dbSize = 0;
    }

    // Media uploads stats
    $uploadsDir = dirname(__DIR__) . '/storage/uploads';
    $uploadCount = 0;
    $uploadSize = 0;
    if (is_dir($uploadsDir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile() && $file->getFilename() !== '.gitkeep') {
                $uploadCount++;
                $uploadSize += $file->getSize();
            }
        }
    }

    // Backups count & size
    $backups = backup_list_files();
    $backupTotalSize = array_sum(array_column($backups, 'size'));

    return [
        'db_name'          => DB_NAME,
        'db_size'          => $dbSize,
        'db_size_formatted'=> backup_format_bytes($dbSize),
        'counts'           => $counts,
        'uploads_count'    => $uploadCount,
        'uploads_size'     => $uploadSize,
        'uploads_size_fmt' => backup_format_bytes($uploadSize),
        'backups_count'    => count($backups),
        'backups_size'     => $backupTotalSize,
        'backups_size_fmt' => backup_format_bytes($backupTotalSize),
    ];
}

/**
 * Generate a complete SQL dump of all database tables and schema.
 * Returns the SQL dump string or writes directly to file if $targetFilePath is given.
 */
function backup_export_database(?string $targetFilePath = null): string
{
    @set_time_limit(300);
    @ini_set('memory_limit', '256M');

    $pdo = db();
    $sql = "-- ============================================================================\n";
    $sql .= "-- Zion Groups of Companies - Complete Database Backup Dump\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s T') . "\n";
    $sql .= "-- Database: " . DB_NAME . "\n";
    $sql .= "-- Server Version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
    $sql .= "-- ============================================================================\n\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

    // Fetch all table names in this database
    $st = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
    $tables = $st->fetchAll(PDO::FETCH_NUM);

    foreach ($tables as [$tableName]) {
        $sql .= "-- ----------------------------------------------------------------\n";
        $sql .= "-- Structure for table `{$tableName}`\n";
        $sql .= "-- ----------------------------------------------------------------\n";
        $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";

        $createSt = $pdo->query("SHOW CREATE TABLE `{$tableName}`");
        $createRow = $createSt->fetch(PDO::FETCH_NUM);
        $createSql = $createRow[1] ?? '';
        if ($createSql !== '') {
            $sql .= $createSql . ";\n\n";
        }

        // Dump table data in batches
        $sql .= "-- Dumping data for table `{$tableName}`\n";
        $countSt = $pdo->query("SELECT COUNT(*) FROM `{$tableName}`");
        $rowCount = (int) $countSt->fetchColumn();

        if ($rowCount > 0) {
            $batchSize = 200;
            $offset = 0;

            while ($offset < $rowCount) {
                $dataSt = $pdo->query("SELECT * FROM `{$tableName}` LIMIT {$batchSize} OFFSET {$offset}");
                $rows = $dataSt->fetchAll(PDO::FETCH_ASSOC);
                if (empty($rows)) {
                    break;
                }

                $columns = array_keys($rows[0]);
                $colNames = implode('`, `', $columns);

                $sql .= "INSERT INTO `{$tableName}` (`{$colNames}`) VALUES\n";
                $rowLines = [];

                foreach ($rows as $row) {
                    $vals = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $vals[] = 'NULL';
                        } elseif (is_int($val) || is_float($val)) {
                            $vals[] = (string) $val;
                        } else {
                            $vals[] = $pdo->quote((string) $val);
                        }
                    }
                    $rowLines[] = '(' . implode(', ', $vals) . ')';
                }

                $sql .= implode(",\n", $rowLines) . ";\n";
                $offset += $batchSize;
            }
            $sql .= "\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $sql .= "-- Dump completed on " . date('Y-m-d H:i:s T') . "\n";

    if ($targetFilePath !== null) {
        $targetDir = dirname($targetFilePath);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0770, true);
        }
        file_put_contents($targetFilePath, $sql);
    }

    return $sql;
}

/**
 * Create a ZIP archive of all files in storage/uploads/ directory.
 */
function backup_export_uploads(?string $targetFilePath = null): string
{
    @set_time_limit(300);
    @ini_set('memory_limit', '256M');

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP Zip extension is required to create zip archives.');
    }

    if ($targetFilePath === null) {
        $targetFilePath = backup_storage_dir() . '/zion_uploads_' . date('Ymd_His') . '.zip';
    }

    $uploadsDir = dirname(__DIR__) . '/storage/uploads';
    $zip = new ZipArchive();
    $res = $zip->open($targetFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($res !== true) {
        throw new RuntimeException('Could not create zip archive: ' . $targetFilePath . ' (Error code: ' . $res . ')');
    }

    $fileCount = 0;
    if (is_dir($uploadsDir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($it as $file) {
            if ($file->isFile()) {
                $filePath = $file->getRealPath();
                $relativePath = 'uploads/' . ltrim(substr($filePath, strlen($uploadsDir)), '/\\');
                $zip->addFile($filePath, str_replace('\\', '/', $relativePath));
                $fileCount++;
            }
        }
    }

    // Add manifest
    $manifest = [
        'app'           => SITE_NAME,
        'type'          => 'uploads',
        'created_at'    => date('Y-m-d H:i:s'),
        'files_count'   => $fileCount,
    ];
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $zip->close();

    return $targetFilePath;
}

/**
 * Create a Full System Backup (Database SQL + Uploads media in a single ZIP).
 */
function backup_export_full(?string $targetFilePath = null, ?string $adminEmail = null): string
{
    @set_time_limit(300);
    @ini_set('memory_limit', '256M');

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP Zip extension is required for full backups.');
    }

    if ($targetFilePath === null) {
        $targetFilePath = backup_storage_dir() . '/zion_full_' . date('Ymd_His') . '.zip';
    }

    // 1. Generate SQL dump
    $sqlContent = backup_export_database();
    $sqlHash = hash('sha256', $sqlContent);

    // 2. Build ZIP archive
    $zip = new ZipArchive();
    $res = $zip->open($targetFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($res !== true) {
        throw new RuntimeException('Could not open zip archive for writing: ' . $targetFilePath);
    }

    // Add database dump
    $zip->addFromString('database.sql', $sqlContent);

    // Add uploads
    $uploadsDir = dirname(__DIR__) . '/storage/uploads';
    $fileCount = 0;
    if (is_dir($uploadsDir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($it as $file) {
            if ($file->isFile()) {
                $filePath = $file->getRealPath();
                $relativePath = 'uploads/' . ltrim(substr($filePath, strlen($uploadsDir)), '/\\');
                $zip->addFile($filePath, str_replace('\\', '/', $relativePath));
                $fileCount++;
            }
        }
    }

    // Add metadata manifest
    $stats = backup_system_stats();
    $manifest = [
        'app'          => SITE_NAME,
        'app_version'  => '1.0.0',
        'type'         => 'full',
        'created_at'   => date('Y-m-d H:i:s T'),
        'created_by'   => $adminEmail ?? 'admin',
        'database'     => DB_NAME,
        'db_hash'      => $sqlHash,
        'table_counts' => $stats['counts'],
        'media_files'  => $fileCount,
    ];
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $zip->close();
    return $targetFilePath;
}

/**
 * Create an automated timestamped emergency rollback safety backup before any restore or wipe.
 */
function backup_create_safety_snapshot(string $reason = 'safety'): string
{
    $dir = backup_storage_dir();
    $file = $dir . '/safety_backup_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $reason) . '_' . date('Ymd_His') . '.sql';
    backup_export_database($file);
    return basename($file);
}

/**
 * Execute raw multi-statement SQL with foreign key checks disabled.
 */
function backup_execute_sql_dump(string $sqlContent): void
{
    @set_time_limit(300);
    @ini_set('memory_limit', '256M');

    $pdo = db();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');

    // Normalize newlines
    $sqlContent = str_replace(["\r\n", "\r"], "\n", $sqlContent);

    // Split SQL into individual statements safely
    $statements = [];
    $buffer = '';
    $inString = false;
    $stringChar = '';
    $escaped = false;
    $len = strlen($sqlContent);

    for ($i = 0; $i < $len; $i++) {
        $c = $sqlContent[$i];

        if ($inString) {
            $buffer .= $c;
            if ($escaped) {
                $escaped = false;
            } elseif ($c === '\\') {
                $escaped = true;
            } elseif ($c === $stringChar) {
                $inString = false;
            }
            continue;
        }

        // Check for line comments (-- or #)
        if ($c === '-' && ($i + 1 < $len) && $sqlContent[$i + 1] === '-') {
            // Read until end of line
            $nextNewline = strpos($sqlContent, "\n", $i);
            if ($nextNewline === false) {
                break;
            }
            $i = $nextNewline;
            continue;
        }

        if ($c === '#') {
            $nextNewline = strpos($sqlContent, "\n", $i);
            if ($nextNewline === false) {
                break;
            }
            $i = $nextNewline;
            continue;
        }

        // Check for block comment (/* ... */)
        if ($c === '/' && ($i + 1 < $len) && $sqlContent[$i + 1] === '*') {
            $nextEnd = strpos($sqlContent, '*/', $i + 2);
            if ($nextEnd === false) {
                break;
            }
            $i = $nextEnd + 1;
            continue;
        }

        if ($c === "'" || $c === '"' || $c === '`') {
            $inString = true;
            $stringChar = $c;
            $buffer .= $c;
            continue;
        }

        if ($c === ';') {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $c;
    }

    $finalStmt = trim($buffer);
    if ($finalStmt !== '') {
        $statements[] = $finalStmt;
    }

    foreach ($statements as $stmt) {
        if ($stmt === '') {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (Throwable $e) {
            // Ignore trivial warnings or rethrow critical syntax errors
            $msg = $e->getMessage();
            if (!str_contains($msg, 'already exists') && !str_contains($msg, 'Unknown table')) {
                throw $e;
            }
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
}

/**
 * Restore system or database from a backup file (.sql or .zip).
 * Automatically preserves current admin account session.
 */
function backup_restore_from_file(string $filePath, ?int $preserveAdminUserId = null): array
{
    @set_time_limit(300);
    @ini_set('memory_limit', '256M');

    if (!is_file($filePath) || !is_readable($filePath)) {
        throw new InvalidArgumentException('Backup file not found or not readable: ' . basename($filePath));
    }

    // 1. Snapshot current admin credentials to guarantee non-lockout
    $activeAdmin = null;
    if ($preserveAdminUserId !== null && $preserveAdminUserId > 0) {
        $activeAdmin = db_one('SELECT * FROM users WHERE id = ?', [$preserveAdminUserId]);
    }
    if ($activeAdmin === null && is_admin()) {
        $activeAdmin = current_user();
    }

    // 2. Create emergency safety backup before proceeding
    $safetySnapshot = backup_create_safety_snapshot('before_restore');

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $restoredDb = false;
    $restoredMedia = 0;

    if ($ext === 'sql') {
        $sql = file_get_contents($filePath);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('SQL backup file is empty.');
        }
        backup_execute_sql_dump($sql);
        $restoredDb = true;
    } elseif ($ext === 'zip') {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP Zip extension is required to unpack zip backups.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('Failed to open ZIP backup archive.');
        }

        // Security check: ensure no Zip Slip path traversal
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (str_contains($entry, '../') || str_contains($entry, '..\\') || str_starts_with($entry, '/') || str_starts_with($entry, '\\')) {
                $zip->close();
                throw new SecurityException('Malformed or unsafe path inside ZIP backup file.');
            }
        }

        // Restore SQL dump if inside ZIP
        $sqlContent = $zip->getFromName('database.sql');
        if ($sqlContent !== false && trim($sqlContent) !== '') {
            backup_execute_sql_dump($sqlContent);
            $restoredDb = true;
        }

        // Restore uploads if inside ZIP
        $uploadsDir = dirname(__DIR__) . '/storage/uploads';
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0775, true);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'uploads/') && !str_ends_with($name, '/')) {
                $relName = substr($name, strlen('uploads/'));
                $destPath = $uploadsDir . '/' . $relName;
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0775, true);
                }
                $stream = $zip->getStream($name);
                if ($stream) {
                    $out = fopen($destPath, 'wb');
                    if ($out) {
                        stream_copy_to_stream($stream, $out);
                        fclose($out);
                        $restoredMedia++;
                    }
                    fclose($stream);
                }
            }
        }
        $zip->close();
    } else {
        throw new InvalidArgumentException('Unsupported backup format. Please provide a .sql or .zip file.');
    }

    // 3. Admin Account Verification & Protection:
    // If the database was restored, ensure an admin exists so the user is never locked out.
    if ($restoredDb) {
        $adminCount = (int) db_val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1", [], 0);
        if ($adminCount === 0 && $activeAdmin !== null) {
            // Re-insert the preserved admin account
            db_exec(
                'INSERT INTO users (id, name, email, phone, password_hash, role, momo_verified, is_active)
                 VALUES (?, ?, ?, ?, ?, "admin", ?, 1)
                 ON DUPLICATE KEY UPDATE role="admin", is_active=1, password_hash=VALUES(password_hash)',
                [
                    (int) $activeAdmin['id'],
                    $activeAdmin['name'],
                    $activeAdmin['email'],
                    $activeAdmin['phone'] ?? null,
                    $activeAdmin['password_hash'],
                    (int) ($activeAdmin['momo_verified'] ?? 0),
                ]
            );
        }
    }

    user_forget();

    return [
        'restored_database' => $restoredDb,
        'restored_media'    => $restoredMedia,
        'safety_snapshot'   => $safetySnapshot,
    ];
}

/**
 * Execute Granular or Complete Data Wipe.
 * Automatically takes a safety snapshot before any wipe operation.
 *
 * Types:
 * - 'transactions': Wipes orders, cart items, wishlists, customer addresses, contact messages, reviews, and customer accounts.
 * - 'catalog': Wipes products, images, variants, specs, bundles, categories, brands, and uploaded media.
 * - 'seed_reset': Re-runs default seed.sql data while preserving current admin account & core settings.
 * - 'factory_reset': Clears all data, resets settings, preserving ONLY the current active administrator.
 */
function backup_data_wipe(string $type, int $currentAdminId): array
{
    @set_time_limit(300);
    @ini_set('memory_limit', '256M');

    $activeAdmin = db_one('SELECT * FROM users WHERE id = ?', [$currentAdminId]);
    if ($activeAdmin === null || ($activeAdmin['role'] ?? '') !== 'admin') {
        throw new RuntimeException('Valid administrator credentials required to perform data wipe.');
    }

    // 1. Always create a pre-wipe safety backup first!
    $safetySnapshot = backup_create_safety_snapshot('before_wipe_' . $type);

    $pdo = db();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');

    $wipedDetails = [];

    switch ($type) {
        case 'transactions':
            // Wipe orders, items, events
            db_exec('TRUNCATE TABLE `order_events`');
            db_exec('TRUNCATE TABLE `order_items`');
            db_exec('TRUNCATE TABLE `orders`');
            db_exec('TRUNCATE TABLE `cart_items`');
            db_exec('TRUNCATE TABLE `wishlist`');
            db_exec('TRUNCATE TABLE `addresses`');
            db_exec('TRUNCATE TABLE `contact_messages`');
            db_exec('TRUNCATE TABLE `reviews`');

            // Remove non-admin customer accounts
            db_exec("DELETE FROM `users` WHERE `role` = 'customer'");

            // Refresh product ratings to 0 since reviews were cleared
            db_exec('UPDATE `products` SET `rating` = 0.0, `review_count` = 0');

            $wipedDetails = [
                'type'    => 'transactions',
                'message' => 'All orders, customer activity, cart sessions, reviews, and customer accounts have been wiped.',
            ];
            break;

        case 'products_only':
            // Wipe product items, images, specs, variants, bundles, reviews, carts, wishlists
            // WITHOUT touching categories or brands!
            db_exec('TRUNCATE TABLE `product_bundles`');
            db_exec('TRUNCATE TABLE `product_specs`');
            db_exec('TRUNCATE TABLE `product_variants`');
            db_exec('TRUNCATE TABLE `product_images`');
            db_exec('TRUNCATE TABLE `cart_items`');
            db_exec('TRUNCATE TABLE `wishlist`');
            db_exec('TRUNCATE TABLE `reviews`');
            db_exec('TRUNCATE TABLE `products`');

            // Clean uploaded product media (preserving .htaccess, .gitkeep, index.html)
            $uploadsDir = dirname(__DIR__) . '/storage/uploads';
            $cleanedFiles = 0;
            if (is_dir($uploadsDir)) {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($it as $file) {
                    if ($file->isFile() && !in_array($file->getFilename(), ['.htaccess', '.gitkeep', 'index.html'], true)) {
                        @unlink($file->getRealPath());
                        $cleanedFiles++;
                    } elseif ($file->isDir()) {
                        @rmdir($file->getRealPath());
                    }
                }
            }

            $wipedDetails = [
                'type'    => 'products_only',
                'message' => "All products, variants, specs, bundles, and {$cleanedFiles} uploaded media files have been cleared. Categories and Brands remain intact.",
            ];
            break;

        case 'catalog':
            // Wipe all product catalog tables
            db_exec('TRUNCATE TABLE `product_bundles`');
            db_exec('TRUNCATE TABLE `product_specs`');
            db_exec('TRUNCATE TABLE `product_variants`');
            db_exec('TRUNCATE TABLE `product_images`');
            db_exec('TRUNCATE TABLE `cart_items`');
            db_exec('TRUNCATE TABLE `wishlist`');
            db_exec('TRUNCATE TABLE `reviews`');
            db_exec('TRUNCATE TABLE `products`');
            db_exec('TRUNCATE TABLE `brands`');
            db_exec('TRUNCATE TABLE `categories`');

            // Clean uploads directory (preserving .htaccess and .gitkeep)
            $uploadsDir = dirname(__DIR__) . '/storage/uploads';
            $cleanedFiles = 0;
            if (is_dir($uploadsDir)) {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($it as $file) {
                    if ($file->isFile() && !in_array($file->getFilename(), ['.htaccess', '.gitkeep', 'index.html'], true)) {
                        @unlink($file->getRealPath());
                        $cleanedFiles++;
                    } elseif ($file->isDir()) {
                        @rmdir($file->getRealPath());
                    }
                }
            }

            $wipedDetails = [
                'type'    => 'catalog',
                'message' => "All products, categories, brands, and {$cleanedFiles} uploaded media files have been cleared.",
            ];
            break;

        case 'seed_reset':
            // Re-import schema and seed data
            $seedFile = dirname(__DIR__) . '/seed.sql';
            if (!is_file($seedFile)) {
                throw new RuntimeException('Default seed.sql file not found.');
            }

            // Wipe catalog + transactions
            $tables = [
                'reviews', 'contact_messages', 'order_events', 'order_items', 'orders',
                'cart_items', 'wishlist', 'addresses', 'product_bundles', 'product_specs',
                'product_variants', 'product_images', 'products', 'brands', 'categories', 'promo_codes'
            ];
            foreach ($tables as $t) {
                db_exec("TRUNCATE TABLE `{$t}`");
            }

            // Execute seed.sql
            $seedSql = file_get_contents($seedFile);
            backup_execute_sql_dump($seedSql);

            // Re-ensure current admin user is preserved
            db_exec(
                'INSERT INTO users (id, name, email, phone, password_hash, role, momo_verified, is_active)
                 VALUES (?, ?, ?, ?, ?, "admin", ?, 1)
                 ON DUPLICATE KEY UPDATE role="admin", is_active=1, password_hash=VALUES(password_hash)',
                [
                    (int) $activeAdmin['id'],
                    $activeAdmin['name'],
                    $activeAdmin['email'],
                    $activeAdmin['phone'] ?? null,
                    $activeAdmin['password_hash'],
                    (int) ($activeAdmin['momo_verified'] ?? 0),
                ]
            );

            $wipedDetails = [
                'type'    => 'seed_reset',
                'message' => 'The catalogue and store presets have been reset to fresh seed demo data.',
            ];
            break;

        case 'factory_reset':
            // Complete fresh slate: wipe all data except current administrator
            $allTables = [
                'reviews', 'contact_messages', 'settings', 'order_events', 'order_items',
                'orders', 'promo_codes', 'cart_items', 'wishlist', 'addresses',
                'product_bundles', 'product_specs', 'product_variants', 'product_images',
                'products', 'brands', 'categories'
            ];
            foreach ($allTables as $t) {
                db_exec("TRUNCATE TABLE `{$t}`");
            }

            // Clear all users except current admin
            db_exec('DELETE FROM `users` WHERE `id` != ?', [(int) $activeAdmin['id']]);

            // Set basic default settings
            set_settings([
                'site_name'                => SITE_NAME,
                'site_tagline'             => SITE_TAGLINE,
                'site_description'         => 'Luxury intimate apparel and high-performance musical instruments in Ghana.',
                'theme_preset'             => 'crimson',
                'free_shipping_threshold'  => '1000',
                'shipping_metro_fee'       => '0',
                'shipping_regional_fee'    => '45',
            ]);

            $wipedDetails = [
                'type'    => 'factory_reset',
                'message' => 'Factory reset completed. All data wiped clean. Your administrator account has been preserved.',
            ];
            break;

        default:
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
            throw new InvalidArgumentException('Unknown data wipe mode: ' . $type);
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
    user_forget();

    $wipedDetails['safety_snapshot'] = $safetySnapshot;
    return $wipedDetails;
}

/**
 * Stream a backup file safely to the browser for direct download.
 */
function backup_stream_download(string $filename): void
{
    $dir = backup_storage_dir();
    $sanitized = basename($filename);
    $fullPath = $dir . '/' . $sanitized;

    if (!is_file($fullPath) || !is_readable($fullPath)) {
        http_response_code(404);
        exit('Requested backup file does not exist.');
    }

    $mime = match (strtolower(pathinfo($sanitized, PATHINFO_EXTENSION))) {
        'sql'   => 'text/plain; charset=utf-8',
        'zip'   => 'application/zip',
        'gz'    => 'application/gzip',
        default => 'application/octet-stream',
    };

    // Clear output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $sanitized . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($fullPath));

    readfile($fullPath);
    exit;
}

/**
 * Safely delete a backup file from storage/backups/.
 */
function backup_delete_file(string $filename): bool
{
    $dir = backup_storage_dir();
    $sanitized = basename($filename);
    $fullPath = $dir . '/' . $sanitized;

    if (is_file($fullPath)) {
        return @unlink($fullPath);
    }
    return false;
}
