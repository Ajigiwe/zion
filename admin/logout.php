<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';

logout_user();
flash_set('info', 'You have been signed out of the back office.');
header('Location: ' . url('admin/login.php'));
exit;
