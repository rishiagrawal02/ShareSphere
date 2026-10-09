<?php

declare(strict_types=1);

namespace App\Support;

use Dotenv\Dotenv;
use RuntimeException;

class Config
{
    private static bool $loaded = false;
    private static array $requiredKeys = [
        'APP_ENV',
        'APP_URL',
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
    ];

    public static function load(string $rootPath): void
    {
        if (self::$loaded) {
            return;
        }

        if (file_exists($rootPath . '/.env')) {
            $dotenv = Dotenv::createImmutable($rootPath);
            $dotenv->load();
        }

        self::validateRequired();
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return $value;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $val = self::get($key);
        return $val !== null ? (int) $val : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $val = self::get($key);
        if ($val === null) {
            return $default;
        }
        return in_array(strtolower((string) $val), ['1', 'true', 'yes', 'on'], true);
    }

    private static function validateRequired(): void
    {
        $missing = [];
        foreach (self::$requiredKeys as $key) {
            $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
            if ($val === false || $val === null || $val === '') {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            throw new RuntimeException('Missing required environment variables: ' . implode(', ', $missing));
        }
    }
}
