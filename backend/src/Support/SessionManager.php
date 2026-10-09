<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Exceptions\UnauthorizedException;
use App\Support\Clock\ClockInterface;
use App\Support\Clock\SystemClock;

class SessionManager
{
    private const COOKIE_NAME = 'ss_session';
    private const IDLE_TIMEOUT_SECONDS = 1800; // 30 minutes
    private const ABSOLUTE_TIMEOUT_SECONDS = 28800; // 8 hours

    private static ?ClockInterface $clock = null;

    public static function setClock(ClockInterface $clock): void
    {
        self::$clock = $clock;
    }

    public static function getClock(): ClockInterface
    {
        if (self::$clock === null) {
            self::$clock = new SystemClock();
        }
        return self::$clock;
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::checkTimeout();
            return;
        }

        $sessionPath = dirname(__DIR__, 2) . '/storage/sessions';
        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0700, true);
        }

        ini_set('session.save_path', $sessionPath);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.name', self::COOKIE_NAME);

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (Config::get('APP_ENV') === 'production');

        session_set_cookie_params([
            'lifetime' => 0, // Session cookie expires when browser closes
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        self::checkTimeout();
    }

    public static function setUser(int $userId, string $role): void
    {
        self::start();
        $now = self::getClock()->timestamp();
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = $role;
        $_SESSION['created_at'] = $now;
        $_SESSION['last_seen_at'] = $now;
    }

    public static function getUserId(): ?int
    {
        self::start();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function getUserRole(): ?string
    {
        self::start();
        return $_SESSION['role'] ?? null;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();
        }
    }

    private static function checkTimeout(): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }

        $now = self::getClock()->timestamp();
        $lastSeen = $_SESSION['last_seen_at'] ?? $now;
        $createdAt = $_SESSION['created_at'] ?? $now;

        // Idle timeout (30 min)
        if (($now - $lastSeen) > self::IDLE_TIMEOUT_SECONDS) {
            self::destroy();
            throw new UnauthorizedException('Session expired due to inactivity', 'SESSION_EXPIRED');
        }

        // Absolute timeout (8 hours)
        if (($now - $createdAt) > self::ABSOLUTE_TIMEOUT_SECONDS) {
            self::destroy();
            throw new UnauthorizedException('Session expired. Please log in again', 'SESSION_EXPIRED');
        }

        $_SESSION['last_seen_at'] = $now;
    }
}
