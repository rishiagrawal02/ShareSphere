<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Support\Config;
use App\Support\Database;
use App\Support\Db;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DbTransactionTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $rootPath = dirname(__DIR__, 2);
        Config::load($rootPath);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec("TRUNCATE TABLE categories CASCADE");
    }

    public function testTransactionCommitsSuccessfully(): void
    {
        Db::transaction(function (PDO $pdo) {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (:name, :desc)");
            $stmt->execute([':name' => 'TxCategory', ':desc' => 'Test Desc']);
        }, $this->pdo);

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM categories WHERE name = 'TxCategory'");
        $count = (int) $stmt->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testTransactionRollsBackOnException(): void
    {
        try {
            Db::transaction(function (PDO $pdo) {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (:name, :desc)");
                $stmt->execute([':name' => 'FailedCategory', ':desc' => 'Test Desc']);
                throw new RuntimeException('Forced failure to trigger rollback');
            }, $this->pdo);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertSame('Forced failure to trigger rollback', $e->getMessage());
        }

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM categories WHERE name = 'FailedCategory'");
        $count = (int) $stmt->fetchColumn();
        $this->assertSame(0, $count);
    }
}
