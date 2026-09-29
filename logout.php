<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

logout_user();
unset($_SESSION['promo_code']);
flash_set('info', 'You have been signed out.');
header('Location: ' . url('index.php'));
exit;
