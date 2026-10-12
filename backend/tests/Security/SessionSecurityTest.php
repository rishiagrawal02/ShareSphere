<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Http\ErrorHandler;
use App\Http\Exceptions\UnauthorizedException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repositories\UserRepository;
use App\Support\Clock\FrozenClock;
use App\Support\Config;
use App\Support\Database;
use App\Support\SessionManager;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * T-19.1-05, T-19.1-06, T-19.1-16: Session and Cookie Security tests.
 * Covers session fixation, cookie security parameters, idle/absolute timeouts,
 * and immediate invalidation upon account suspension.
 */
class SessionSecurityTest extends TestCase
{
    private PDO $pdo;
    private Router $router;
    private UserRepository $userRepo;
    private int $userId;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $root = dirname(__DIR__, 2);
        Config::load($root);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec('TRUNCATE TABLE users CASCADE');

        $this->userRepo = new UserRepository($this->pdo);
        $user = $this->userRepo->create([
            'name'           => 'Eva Session',
            'email'          => 'eva@test.org',
            'password_hash'  => password_hash('Pass123!', PASSWORD_DEFAULT),
            'role'           => 'donor',
            'account_status' => 'active',
        ]);
        $this->userId = (int) $user['id'];

        $this->router = new Router();
        $routesFn = require $root . '/src/routes.php';
        $routesFn($this->router);
    }

    protected function tearDown(): void
    {
        SessionManager::setClock(new \App\Support\Clock\SystemClock());
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    private function dispatch(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (\Throwable $e) {
            return ErrorHandler::handle($e, $request);
        }
    }

    /**
     * T-19.1-05: Session fixation prevention via ID regeneration on login.
     */
    public function testSessionRegenerationPreventsFixation(): void
    {
        SessionManager::start();
        $initialSessionId = session_id();
        $this->assertNotEmpty($initialSessionId);

        // Authenticate user and regenerate session
        SessionManager::setUser($this->userId, 'donor');
        SessionManager::regenerate();
        $newSessionId = session_id();

        $this->assertNotEmpty($newSessionId);
        $this->assertNotSame($initialSessionId, $newSessionId, 'Session ID must rotate upon authentication');
        $this->assertSame($this->userId, SessionManager::getUserId());
    }

    /**
     * T-19.1-06: Cookie configuration standards: HttpOnly, SameSite, and Strict Mode.
     */
    public function testSessionCookieSecurityFlags(): void
    {
        SessionManager::start();
        $params = session_get_cookie_params();

        $this->assertTrue($params['httponly'], 'Cookie must have HttpOnly enabled');
        $this->assertSame('Lax', $params['samesite'], 'Cookie must have SameSite set to Lax');
        $this->assertSame('1', ini_get('session.use_strict_mode'), 'Session strict mode must be enabled');
        $this->assertSame('1', ini_get('session.use_only_cookies'), 'Session must use only cookies');
    }

    /**
     * T-19.1-16: Suspended user token / active session reuse immediate revocation.
     */
    public function testSuspendedUserSessionImmediatelyRevoked(): void
    {
        SessionManager::setUser($this->userId, 'donor');

        // Initial request works
        $req = new Request('GET', '/api/profile', []);
        $res = $this->dispatch($req);
        $this->assertSame(200, $res->getStatusCode(), 'Active user should access profile');

        // Suspend user in database
        $this->pdo->prepare("UPDATE users SET account_status = 'suspended' WHERE id = :id")
            ->execute([':id' => $this->userId]);

        // Subsequent request with the same session is immediately rejected with 401
        $resSuspended = $this->dispatch($req);
        $this->assertSame(401, $resSuspended->getStatusCode(), 'Suspended user must receive 401');

        $body = json_decode($resSuspended->getContent(), true);
        $this->assertSame('ACCOUNT_SUSPENDED', $body['error']['code'] ?? null);

        // Session was destroyed
        $this->assertNull(SessionManager::getUserId(), 'Session must be destroyed when user is suspended');
    }

    /**
     * Idle timeout invalidates session after 30 minutes of inactivity.
     */
    public function testSessionIdleTimeout(): void
    {
        $baseTime = new DateTimeImmutable('2026-10-12 10:00:00');
        $clock = new FrozenClock($baseTime);
        SessionManager::setClock($clock);

        SessionManager::setUser($this->userId, 'donor');
        $this->assertSame($this->userId, SessionManager::getUserId());

        // Fast forward 31 minutes
        $clock->advance('+31 minutes');

        // Next request fails due to idle timeout
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Session expired due to inactivity');
        SessionManager::getUserId();
    }
}
