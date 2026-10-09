<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(?string $dbName = null, ?string $user = null, ?string $pass = null): PDO
    {
        if ($dbName === null && $user === null && self::$instance !== null) {
            return self::$instance;
        }

        $host = Config::get('DB_HOST', '127.0.0.1');
        $port = Config::getInt('DB_PORT', 5432);
        $name = $dbName ?? Config::get('DB_NAME', 'sharesphere_dev');
        $username = $user ?? Config::get('DB_USER', 'sharesphere_app');
        $password = $pass ?? Config::get('DB_PASS', 'change-me');

        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $name);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
            if ($dbName === null && $user === null) {
                self::$instance = $pdo;
            }
            return $pdo;
        } catch (\PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public static function getOwnerConnection(?string $dbName = null): PDO
    {
        $name = $dbName ?? Config::get('DB_NAME', 'sharesphere_dev');
        $user = Config::get('DB_OWNER_USER', 'sharesphere_owner');
        $pass = Config::get('DB_OWNER_PASS', 'change-me');
        return self::getConnection($name, $user, $pass);
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
