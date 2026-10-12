<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Http\ErrorHandler;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repositories\UserRepository;
use App\Support\Config;
use App\Support\CsrfGuard;
use App\Support\Database;
use App\Support\SessionManager;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * T-19.1-15: Privileged administrative action authorization test.
 * Asserts that all administrative endpoints strictly enforce the admin role gate,
 * rejecting anonymous, donor, and NGO roles.
 */
class PrivilegedActionAuthzTest extends TestCase
{
    private PDO $pdo;
    private Router $router;
    private int $donorId;
    private int $ngoUserId;
    private int $adminId;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $root = dirname(__DIR__, 2);
        Config::load($root);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec('TRUNCATE TABLE users, ngos CASCADE');

        $this->router = new Router();
        $routesFn = require $root . '/src/routes.php';
        $routesFn($this->router);

        $userRepo = new UserRepository($this->pdo);
        $donor = $userRepo->create(['name' => 'Donor User', 'email' => 'donor@test.org', 'password_hash' => 'h', 'role' => 'donor']);
        $this->donorId = (int) $donor['id'];

        $ngo = $userRepo->create(['name' => 'NGO User', 'email' => 'ngo@test.org', 'password_hash' => 'h', 'role' => 'ngo']);
        $this->ngoUserId = (int) $ngo['id'];

        $admin = $userRepo->create(['name' => 'Admin User', 'email' => 'admin@test.org', 'password_hash' => 'h', 'role' => 'admin']);
        $this->adminId = (int) $admin['id'];
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

    public function testAdminEndpointsRejectAnonymous(): void
    {
        $endpoints = [
            ['GET', '/api/admin/ngos'],
            ['GET', '/api/admin/reports/summary'],
            ['GET', '/api/admin/categories'],
        ];

        foreach ($endpoints as [$method, $path]) {
            $req = new Request($method, $path, []);
            $res = $this->dispatch($req);
            $this->assertSame(401, $res->getStatusCode(), "Anonymous request to {$path} must be rejected with 401");
        }
    }

    public function testAdminEndpointsRejectDonor(): void
    {
        SessionManager::setUser($this->donorId, 'donor');
        $csrf = CsrfGuard::getToken();

        $endpoints = [
            ['GET', '/api/admin/ngos'],
            ['GET', '/api/admin/reports/summary'],
            ['POST', '/api/admin/categories'],
        ];

        foreach ($endpoints as [$method, $path]) {
            $req = new Request($method, $path, [], ['x-csrf-token' => $csrf]);
            $res = $this->dispatch($req);
            $this->assertSame(403, $res->getStatusCode(), "Donor request to {$path} must be rejected with 403");
        }
    }

    public function testAdminEndpointsRejectNgo(): void
    {
        SessionManager::setUser($this->ngoUserId, 'ngo');
        $csrf = CsrfGuard::getToken();

        $endpoints = [
            ['GET', '/api/admin/ngos'],
            ['GET', '/api/admin/reports/summary'],
            ['POST', '/api/admin/categories'],
        ];

        foreach ($endpoints as [$method, $path]) {
            $req = new Request($method, $path, [], ['x-csrf-token' => $csrf]);
            $res = $this->dispatch($req);
            $this->assertSame(403, $res->getStatusCode(), "NGO request to {$path} must be rejected with 403");
        }
    }

    public function testAdminEndpointsPermitAdmin(): void
    {
        SessionManager::setUser($this->adminId, 'admin');

        $req = new Request('GET', '/api/admin/reports/summary', []);
        $res = $this->dispatch($req);

        $this->assertSame(200, $res->getStatusCode(), "Admin must have access to admin reports");
        $body = json_decode($res->getContent(), true);
        $this->assertTrue($body['success'] ?? false);
    }
}
