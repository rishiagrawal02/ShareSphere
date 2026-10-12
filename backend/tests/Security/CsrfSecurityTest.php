<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Http\ErrorHandler;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repositories\CategoryRepository;
use App\Repositories\UserRepository;
use App\Support\Config;
use App\Support\CsrfGuard;
use App\Support\Database;
use App\Support\SessionManager;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * T-19.1-04: Cross-Site Request Forgery (CSRF) defense testing.
 * Verifies that state-mutating requests (POST, PUT, PATCH, DELETE) strictly require
 * a synchronized session token and block forged requests without a valid token.
 */
class CsrfSecurityTest extends TestCase
{
    private PDO $pdo;
    private Router $router;
    private int $donorId;
    private int $catId;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $root = dirname(__DIR__, 2);
        Config::load($root);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec('TRUNCATE TABLE users, ngos, categories, donations CASCADE');

        $this->router = new Router();
        $routesFn = require $root . '/src/routes.php';
        $routesFn($this->router);

        $userRepo = new UserRepository($this->pdo);
        $donor = $userRepo->create([
            'name' => 'Dana Donor',
            'email' => 'dana@test.org',
            'password_hash' => 'hash',
            'role' => 'donor',
        ]);
        $this->donorId = (int) $donor['id'];

        $catRepo = new CategoryRepository($this->pdo);
        $cat = $catRepo->create('Furniture', 'Desks and chairs', true);
        $this->catId = (int) $cat['id'];
    }

    protected function tearDown(): void
    {
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

    public function testSafeMethodsDoNotRequireCsrfToken(): void
    {
        SessionManager::setUser($this->donorId, 'donor');

        // GET request to /api/donations with no CSRF token
        $req = new Request('GET', '/api/donations', []);
        $res = $this->dispatch($req);

        $this->assertSame(200, $res->getStatusCode(), 'GET requests must not require CSRF token');
    }

    public function testStateChangingRequestWithoutCsrfTokenIsRejected(): void
    {
        SessionManager::setUser($this->donorId, 'donor');

        // POST request to /api/donations without CSRF token header
        $payload = [
            'title' => 'Office Chair',
            'category_id' => 1,
            'description' => 'Black ergonomic chair',
            'condition' => 'good',
            'total_quantity' => 1,
            'latitude' => 18.52,
            'longitude' => 73.85,
            'address_text' => '123 Main St',
        ];

        $req = new Request(
            'POST',
            '/api/donations',
            [],
            ['content-type' => 'application/json'],
            [],
            [],
            json_encode($payload),
            $payload
        );

        $res = $this->dispatch($req);
        $this->assertSame(401, $res->getStatusCode(), 'POST without CSRF token must be rejected with 401');

        $data = json_decode($res->getContent(), true);
        $this->assertSame('CSRF_MISMATCH', $data['error']['code'] ?? null);
    }

    public function testStateChangingRequestWithForgedCsrfTokenIsRejected(): void
    {
        SessionManager::setUser($this->donorId, 'donor');
        // Initialize legitimate token
        CsrfGuard::getToken();

        // Attacker submits forged token
        $payload = [
            'title' => 'Office Chair',
            'category_id' => 1,
            'description' => 'Black ergonomic chair',
            'condition' => 'good',
            'total_quantity' => 1,
            'latitude' => 18.52,
            'longitude' => 73.85,
            'address_text' => '123 Main St',
        ];

        $req = new Request(
            'POST',
            '/api/donations',
            [],
            [
                'content-type' => 'application/json',
                'x-csrf-token' => 'forged_attacker_token_1234567890abcdef',
            ],
            [],
            [],
            json_encode($payload),
            $payload
        );

        $res = $this->dispatch($req);
        $this->assertSame(401, $res->getStatusCode(), 'Forged CSRF token must be rejected with 401');

        $data = json_decode($res->getContent(), true);
        $this->assertSame('CSRF_MISMATCH', $data['error']['code'] ?? null);
    }

    public function testStateChangingRequestWithValidCsrfTokenSucceeds(): void
    {
        SessionManager::setUser($this->donorId, 'donor');
        $validToken = CsrfGuard::getToken();

        $payload = [
            'title' => 'Office Chair',
            'category_id' => $this->catId,
            'description' => 'Black ergonomic chair',
            'condition' => 'good',
            'total_quantity' => 1,
            'latitude' => 18.52,
            'longitude' => 73.85,
            'address_text' => '123 Main St',
        ];

        $req = new Request(
            'POST',
            '/api/donations',
            [],
            [
                'content-type' => 'application/json',
                'x-csrf-token' => $validToken,
            ],
            [],
            [],
            json_encode($payload),
            $payload
        );

        $res = $this->dispatch($req);
        $this->assertSame(201, $res->getStatusCode(), 'Valid CSRF token must allow donation creation');
    }
}
