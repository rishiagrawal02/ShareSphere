<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

class Db
{
    /**
     * Executes a callback within a database transaction, automatically rolling back on exception.
     */
    public static function transaction(callable $callback, ?PDO $pdo = null): mixed
    {
        $connection = $pdo ?? Database::getConnection();

        $alreadyInTransaction = $connection->inTransaction();

        if (!$alreadyInTransaction) {
            $connection->beginTransaction();
        }

        try {
            $result = $callback($connection);

            if (!$alreadyInTransaction) {
                $connection->commit();
            }

            return $result;
        } catch (Throwable $e) {
            if (!$alreadyInTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Acquires a row lock with SELECT ... FOR UPDATE.
     */
    public static function forUpdate(string $table, string $idColumn, int|string $id, PDO $pdo): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$idColumn} = :id FOR UPDATE");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }
}
