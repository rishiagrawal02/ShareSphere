<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\Exceptions\UnauthorizedException;
use App\Support\CsrfGuard;
use PHPUnit\Framework\TestCase;

class CsrfGuardTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    public function testGetTokenGeneratesAndPersistsToken(): void
    {
        $token1 = CsrfGuard::getToken();
        $this->assertNotEmpty($token1);
        $this->assertSame(64, strlen($token1));

        $token2 = CsrfGuard::getToken();
        $this->assertSame($token1, $token2);
    }

    public function testRotateTokenGeneratesNewToken(): void
    {
        $token1 = CsrfGuard::getToken();
        $token2 = CsrfGuard::rotateToken();

        $this->assertNotSame($token1, $token2);
        $this->assertSame($token2, CsrfGuard::getToken());
    }

    public function testVerifySucceedsOnMatchingToken(): void
    {
        $token = CsrfGuard::getToken();
        CsrfGuard::verify($token);
        $this->assertTrue(true);
    }

    public function testVerifyThrowsOnInvalidToken(): void
    {
        CsrfGuard::getToken();

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('CSRF token mismatch');

        CsrfGuard::verify('invalid_token_value');
    }
}
