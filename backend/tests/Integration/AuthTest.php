<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\UnauthorizedException;
use App\Http\Exceptions\ValidationFailedException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Support\Config;
use App\Support\CsrfGuard;
use App\Support\Database;
use App\Support\SessionManager;
use PDO;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    private PDO $pdo;
    private AuthService $authService;
    private UserRepository $userRepo;
    private Router $router;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $rootPath = dirname(__DIR__, 2);
        Config::load($rootPath);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec("TRUNCATE TABLE users CASCADE");

        $this->userRepo = new UserRepository($this->pdo);
        $this->authService = new AuthService($this->userRepo);

        $this->router = new Router();
        $routesFn = require dirname(__DIR__, 2) . '/src/routes.php';
        $routesFn($this->router);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    public function testDonorRegistrationSuccess(): void
    {
        $userData = [
            'name' => 'Alice Donor',
            'email' => 'alice@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'phone' => '+1234567890',
            'accept_terms' => true,
        ];

        $user = $this->authService->registerDonor($userData);

        $this->assertNotEmpty($user['id']);
        $this->assertSame('Alice Donor', $user['name']);
        $this->assertSame('alice@example.com', $user['email']);
        $this->assertSame('donor', $user['role']);
        $this->assertSame('active', $user['account_status']);

        // Verify session set
        $this->assertSame((int)$user['id'], SessionManager::getUserId());
        $this->assertSame('donor', SessionManager::getUserRole());
    }

    public function testDonorRegistrationFailsOnDuplicateEmail(): void
    {
        $userData = [
            'name' => 'Alice Donor',
            'email' => 'alice@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'accept_terms' => true,
        ];

        $this->authService->registerDonor($userData);

        $this->expectException(ConflictException::class);
        $this->authService->registerDonor($userData);
    }

    public function testDonorRegistrationFailsOnPasswordMismatch(): void
    {
        $userData = [
            'name' => 'Bob Donor',
            'email' => 'bob@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'DifferentPass123!',
            'accept_terms' => true,
        ];

        $this->expectException(ValidationFailedException::class);
        $this->authService->registerDonor($userData);
    }

    public function testLoginSuccessAndLogout(): void
    {
        $this->authService->registerDonor([
            'name' => 'Charlie Donor',
            'email' => 'charlie@example.com',
            'password' => 'SuperSecret123!',
            'password_confirmation' => 'SuperSecret123!',
            'accept_terms' => true,
        ]);

        SessionManager::destroy();
        $this->assertNull(SessionManager::getUserId());

        $user = $this->authService->login('charlie@example.com', 'SuperSecret123!');
        $this->assertSame('Charlie Donor', $user['name']);
        $this->assertArrayNotHasKey('password_hash', $user);
        $this->assertSame((int)$user['id'], SessionManager::getUserId());

        // Test Me endpoint via AuthService
        $me = $this->authService->getCurrentUser();
        $this->assertNotNull($me);
        $this->assertSame('charlie@example.com', $me['email']);

        // Logout
        $this->authService->logout();
        $this->assertNull(SessionManager::getUserId());
    }

    public function testLoginFailsOnInvalidCredentials(): void
    {
        $this->expectException(UnauthorizedException::class);
        $this->authService->login('nonexistent@example.com', 'Password123!');
    }

    public function testLoginFailsOnSuspendedAccount(): void
    {
        $user = $this->userRepo->create([
            'name' => 'Suspended User',
            'email' => 'suspended@example.com',
            'password_hash' => password_hash('ValidPassword123!', PASSWORD_DEFAULT),
            'role' => 'donor',
            'account_status' => 'suspended',
        ]);

        $this->expectException(ForbiddenException::class);
        $this->authService->login('suspended@example.com', 'ValidPassword123!');
    }

    public function testRouterMeEndpointWithAuthMiddleware(): void
    {
        // Unauthenticated request to /api/auth/me should throw UnauthorizedException
        try {
            $req = new Request('GET', '/api/auth/me');
            $this->router->dispatch($req);
            $this->fail('Expected UnauthorizedException');
        } catch (UnauthorizedException $e) {
            $this->assertSame(401, $e->getStatusCode());
        }

        // Authenticate
        $user = $this->authService->registerDonor([
            'name' => 'Route User',
            'email' => 'route@example.com',
            'password' => 'Password12345!',
            'password_confirmation' => 'Password12345!',
            'accept_terms' => true,
        ]);

        $req = new Request('GET', '/api/auth/me');
        $response = $this->router->dispatch($req);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('ok', $data['status']);
        $this->assertSame('route@example.com', $data['user']['email']);
    }

    public function testCsrfMiddlewareBlocksPostWithoutToken(): void
    {
        $body = [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ];

        $req = new Request('POST', '/api/auth/login', [], ['content-type' => 'application/json'], [], [], json_encode($body), $body);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('CSRF token mismatch');

        $this->router->dispatch($req);
    }

    public function testCsrfMiddlewareAllowsPostWithValidToken(): void
    {
        // Pre-create user
        $this->userRepo->create([
            'name' => 'CSRF User',
            'email' => 'csrf@example.com',
            'password_hash' => password_hash('ValidPassword123!', PASSWORD_DEFAULT),
            'role' => 'donor',
            'account_status' => 'active',
        ]);

        $token = CsrfGuard::getToken();

        $body = [
            'email' => 'csrf@example.com',
            'password' => 'ValidPassword123!',
        ];

        $req = new Request(
            'POST',
            '/api/auth/login',
            [],
            [
                'content-type' => 'application/json',
                'x-csrf-token' => $token,
            ],
            [],
            [],
            json_encode($body),
            $body
        );

        $response = $this->router->dispatch($req);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('ok', $data['status']);
    }
}
