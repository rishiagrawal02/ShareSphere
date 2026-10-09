<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Support\Config;
use App\Support\Database;

$rootPath = dirname(__DIR__, 2);
Config::load($rootPath);

$seedFile = $rootPath . '/db/seed/dev_seed.sql';
if (!file_exists($seedFile)) {
    echo "Seed file not found: $seedFile\n";
    exit(1);
}

try {
    $pdo = Database::getOwnerConnection();
    $sql = file_get_contents($seedFile);

    echo "Running seed: dev_seed.sql... ";
    $pdo->beginTransaction();
    $pdo->exec($sql);
    $pdo->commit();
    echo "DONE\n";

    $catCount = (int) $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $compatCount = (int) $pdo->query("SELECT COUNT(*) FROM category_compatibility")->fetchColumn();

    echo "Seeding completed successfully. Categories: $catCount, Compatibility Pairs: $compatCount.\n";
} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
