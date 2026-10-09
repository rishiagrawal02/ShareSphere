<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\Exceptions\UnauthorizedException;
use App\Support\Clock\ClockInterface;
use App\Support\SessionManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SessionManager.
 *
 * Session I/O is tested via the in-memory PHP session superglobal.
 * A fake ClockInterface is injected to control timeout behaviour.
 */
class SessionManagerTest extends TestCase
{
    /** Fake clock that returns a controllable timestamp */
    private FakeClock $clock;

    protected function setUp(): void
    {
        // Reset session state before each test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $this->clock = new FakeClock(time());
        SessionManager::setClock($this->clock);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    public function testSetUserPopulatesSession(): void
    {
        SessionManager::setUser(42, 'donor');

        $this->assertSame(42, SessionManager::getUserId());
        $this->assertSame('donor', SessionManager::getUserRole());
    }

    public function testGetUserIdReturnsNullWhenNoSession(): void
    {
        // Start session with no user — should return null
        SessionManager::start();
        $this->assertNull(SessionManager::getUserId());
    }

    public function testIdleTimeoutDestroysSession(): void
    {
        SessionManager::setUser(1, 'admin');

        // Advance clock past idle timeout (30 min + 1 s)
        $this->clock->advance(1801);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('inactivity');

        SessionManager::start(); // triggers checkTimeout
    }

    public function testAbsoluteTimeoutDestroysSession(): void
    {
        SessionManager::setUser(1, 'admin');

        // Advance clock past absolute timeout (8 hours + 1 s) but keep last_seen recent
        $this->clock->advance(28801);
        $_SESSION['last_seen_at'] = $this->clock->timestamp() - 60; // seen 1 min ago

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Please log in again');

        SessionManager::start();
    }

    public function testDestroyRemovesUser(): void
    {
        SessionManager::setUser(5, 'ngo');
        SessionManager::destroy();

        $this->assertNull(SessionManager::getUserId());
    }
}

// ── Fake Clock helper ────────────────────────────────────────────────────────

class FakeClock implements ClockInterface
{
    private int $ts;

    public function __construct(int $startTimestamp)
    {
        $this->ts = $startTimestamp;
    }

    public function now(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->setTimestamp($this->ts);
    }

    public function timestamp(): int
    {
        return $this->ts;
    }

    public function advance(int $seconds): void
    {
        $this->ts += $seconds;
    }
}
