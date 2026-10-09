#!/usr/bin/env php
<?php

/**
 * ShareSphere – Donation Expiry Worker
 *
 * Marks expired donations with no active allocations as 'closed'.
 *
 * Usage:
 *   php bin/expire-donations.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Support\Config;
use App\Support\Database;

$rootPath = dirname(__DIR__, 2);
if (!file_exists($rootPath . '/.env')) {
    $rootPath = dirname(__DIR__);
}
Config::load($rootPath);

$pdo = Database::getConnection();

echo "[" . date('Y-m-d H:i:s') . "] Checking for expired donations...\n";

$stmt = $pdo->prepare("
    UPDATE donations
    SET status = 'closed',
        available_quantity = 0,
        updated_at = NOW()
    WHERE expires_at IS NOT NULL
      AND expires_at < NOW()
      AND status IN ('draft', 'active')
      AND id NOT IN (
          SELECT DISTINCT donation_id FROM allocations WHERE status IN ('reserved', 'confirmed', 'collected')
      )
");

$stmt->execute();
$closedCount = $stmt->rowCount();

echo "[" . date('Y-m-d H:i:s') . "] Closed {$closedCount} expired donation(s).\n";
exit(0);
