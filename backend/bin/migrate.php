<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Support\Config;
use App\Support\Database;

$rootPath = dirname(__DIR__, 2);
Config::load($rootPath);

// Ensure migrations directory exists
$migrationsDir = $rootPath . '/db/migrations';
if (!is_dir($migrationsDir)) {
    mkdir($migrationsDir, 0755, true);
}

// Parse command line arguments
$args = array_slice($argv, 1);
$command = $args[0] ?? 'status';

$steps = 1;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--steps=')) {
        $steps = (int) substr($arg, 8);
    }
}

try {
    $pdo = Database::getOwnerConnection();

    // Ensure schema_migrations table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(255) PRIMARY KEY,
            checksum VARCHAR(64) NOT NULL,
            applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        );
    ");

    switch ($command) {
        case 'up':
            runUp($pdo, $migrationsDir);
            break;
        case 'down':
            runDown($pdo, $migrationsDir, $steps);
            break;
        case 'status':
            showStatus($pdo, $migrationsDir);
            break;
        default:
            echo "Unknown command: $command\nUsage: php migrate.php [up|down|status] [--steps=N]\n";
            exit(1);
    }
} catch (\Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

function getMigrationFiles(string $dir, string $type): array
{
    $files = glob($dir . '/*.' . $type . '.sql');
    sort($files, SORT_STRING);
    return $files;
}

function runUp(\PDO $pdo, string $dir): void
{
    $applied = $pdo->query("SELECT version, checksum FROM schema_migrations ORDER BY version ASC")->fetchAll(\PDO::FETCH_KEY_PAIR);
    $files = getMigrationFiles($dir, 'up');

    if (empty($files)) {
        echo "No migration files found.\n";
        return;
    }

    $appliedCount = 0;
    foreach ($files as $file) {
        $filename = basename($file);
        $version = substr($filename, 0, strpos($filename, '.up.sql'));
        $content = file_get_contents($file);
        $checksum = hash('sha256', $content);

        if (isset($applied[$version])) {
            if ($applied[$version] !== $checksum) {
                throw new \RuntimeException("Checksum mismatch for applied migration: $version! Migration was modified after being applied.");
            }
            continue;
        }

        echo "Applying: $filename... ";
        $pdo->beginTransaction();
        try {
            $pdo->exec($content);
            $stmt = $pdo->prepare("INSERT INTO schema_migrations (version, checksum) VALUES (:v, :c)");
            $stmt->execute([':v' => $version, ':c' => $checksum]);
            $pdo->commit();
            echo "DONE\n";
            $appliedCount++;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new \RuntimeException("Error executing $filename: " . $e->getMessage(), 0, $e);
        }
    }

    if ($appliedCount === 0) {
        echo "Nothing to migrate. Database is up to date.\n";
    } else {
        echo "Successfully applied $appliedCount migration(s).\n";
    }
}

function runDown(\PDO $pdo, string $dir, int $steps): void
{
    $stmt = $pdo->query("SELECT version FROM schema_migrations ORDER BY version DESC");
    $appliedVersions = $stmt->fetchAll(\PDO::FETCH_COLUMN);

    if (empty($appliedVersions)) {
        echo "No migrations applied to roll back.\n";
        return;
    }

    $toRollback = array_slice($appliedVersions, 0, $steps);
    foreach ($toRollback as $version) {
        $downFile = $dir . '/' . $version . '.down.sql';
        if (!file_exists($downFile)) {
            throw new \RuntimeException("Missing rollback file: $version.down.sql");
        }

        echo "Rolling back: $version... ";
        $content = file_get_contents($downFile);

        $pdo->beginTransaction();
        try {
            $pdo->exec($content);
            $deleteStmt = $pdo->prepare("DELETE FROM schema_migrations WHERE version = :v");
            $deleteStmt->execute([':v' => $version]);
            $pdo->commit();
            echo "DONE\n";
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new \RuntimeException("Error rolling back $version: " . $e->getMessage(), 0, $e);
        }
    }

    echo "Rollback completed.\n";
}

function showStatus(\PDO $pdo, string $dir): void
{
    $applied = $pdo->query("SELECT version, applied_at FROM schema_migrations ORDER BY version ASC")->fetchAll(\PDO::FETCH_KEY_PAIR);
    $files = getMigrationFiles($dir, 'up');

    echo sprintf("%-30s | %-10s | %-25s\n", "Migration Version", "Status", "Applied At");
    echo str_repeat("-", 70) . "\n";

    foreach ($files as $file) {
        $filename = basename($file);
        $version = substr($filename, 0, strpos($filename, '.up.sql'));
        $status = isset($applied[$version]) ? "Applied" : "Pending";
        $appliedAt = $applied[$version] ?? "-";
        echo sprintf("%-30s | %-10s | %-25s\n", $version, $status, $appliedAt);
    }
}
