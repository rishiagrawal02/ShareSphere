<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Exceptions\UnauthorizedException;

/**
 * Synchronizer-token pattern CSRF guard.
 *
 * Tokens are stored in the server-side session and compared in constant time.
 * Safe methods (GET, HEAD, OPTIONS, TRACE) are exempted.
 */
class CsrfGuard
{
    private const SESSION_KEY = '_csrf_token';
    private const TOKEN_LENGTH = 32; // bytes → 64 hex chars

    /** Return the current CSRF token, generating one if absent. */
    public static function getToken(): string
    {
        SessionManager::start();

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /** Rotate the token (call after a successful state-changing action). */
    public static function rotateToken(): string
    {
        SessionManager::start();
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Verify CSRF token from request headers or body.
     * Throws UnauthorizedException on mismatch.
     */
    public static function verify(string $provided): void
    {
        SessionManager::start();
        $stored = $_SESSION[self::SESSION_KEY] ?? '';

        if ($stored === '' || !hash_equals($stored, $provided)) {
            throw new UnauthorizedException('CSRF token mismatch', 'CSRF_MISMATCH');
        }
    }
}
