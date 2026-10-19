<?php
/**
 * Zion Groups of Companies - Deploy Entry Point (Alias for sync_deploy.php).
 *
 * Runs via CLI or Browser:
 *   CLI:     php deploy.php
 *   Browser: /deploy.php?token=<DEPLOY_TOKEN from .env>   (403 without it)
 *
 * See sync_deploy.php - this alias shares its access control.
 */

declare(strict_types=1);
require __DIR__ . '/sync_deploy.php';
