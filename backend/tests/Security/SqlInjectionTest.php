<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Http\ErrorHandler;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repositories\CategoryRepository;
use App\Repositories\DonationRepository;
use App\Repositories\ReportRepository;
use App\Repositories\UserRepository;
use App\Support\Config;
use App\Support\Database;
use App\Support\SessionManager;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * T-19.1-01: SQL Injection corpus testing across auth, filters, parameters, and reporting.
 * Verifies all inputs are safely parameterised and resist injection attempts.
 */
class SqlInjectionTest extends TestCase
{
    private PDO $pdo;
    private Router $router;
    private UserRepository $userRepo;
    private DonationRepository $donationRepo;
    private ReportRepository $reportRepo;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $root = dirname(__DIR__, 2);
        Config::load($root);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec('TRUNCATE TABLE users, ngos, categories, donations, donation_images CASCADE');

        $this->userRepo = new UserRepository($this->pdo);
        $this->donationRepo = new DonationRepository($this->pdo);
        $this->reportRepo = new ReportRepository($this->pdo);

        $this->router = new Router();
        $routesFn = require $root . '/src/routes.php';
        $routesFn($this->router);

        // Seed legitimate user
        $this->userRepo->create([
            'name' => 'Alice Legitimate',
            'email' => 'alice@example.com',
            'password_hash' => password_hash('SecretPass123!', PASSWORD_DEFAULT),
            'role' => 'donor',
        ]);

        // Seed Category
        $catRepo = new CategoryRepository($this->pdo);
        $cat = $catRepo->create('Electronics', 'Gadgets and cords', true);

        // Seed legitimate donation
        $this->pdo->prepare("
            INSERT INTO donations (donor_id, category_id, title, description, condition, total_quantity, available_quantity, status, address_text, location, location_public)
            VALUES ((SELECT id FROM users WHERE email = 'alice@example.com'), :cat_id, 'Laptop', 'Good laptop', 'good', 1, 1, 'active', 'Address',
                    ST_SetSRID(ST_MakePoint(73.85, 18.52), 4326)::geography,
                    ST_SetSRID(ST_MakePoint(73.85, 18.52), 4326)::geography)
        ")->execute([':cat_id' => $cat['id']]);
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

    /**
     * Auth login with classic SQL injection tautologies.
     */
    public function testLoginRejectsSqlInjectionPayloads(): void
    {
        $payloads = [
            "' OR '1'='1",
            "' OR '1'='1' --",
            "admin'--",
            "' UNION SELECT 1, 'admin', 'pass', 'admin'--",
            "\" OR \"\"=\"",
            "1' or 1=1#",
        ];

        foreach ($payloads as $maliciousEmail) {
            $req = new Request(
                'POST',
                '/api/auth/login',
                [],
                ['content-type' => 'application/json'],
                [],
                [],
                json_encode(['email' => $maliciousEmail, 'password' => 'arbitrary_pass']),
                ['email' => $maliciousEmail, 'password' => 'arbitrary_pass']
            );

            $res = $this->dispatch($req);
            $this->assertNotSame(200, $res->getStatusCode(), "Login must not succeed with SQL injection payload: {$maliciousEmail}");
            $this->assertSame(401, $res->getStatusCode(), "Login must return 401 unauthenticated");
            
            $body = json_decode($res->getContent(), true);
            $this->assertFalse($body['success'] ?? false);
        }
    }

    /**
     * Public search query with SQL injection payloads.
     */
    public function testPublicDonationSearchHandlesSqlInjection(): void
    {
        $user = $this->userRepo->findByEmail('alice@example.com');
        SessionManager::setUser((int) $user['id'], 'donor');

        $injectionFilters = [
            "1' OR '1'='1",
            "1; DROP TABLE users;--",
            "' UNION SELECT id, name, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL FROM users--",
            "active' AND (SELECT 1 FROM pg_sleep(0.5))='1",
        ];

        foreach ($injectionFilters as $payload) {
            $req = new Request('GET', '/api/donations', ['status' => $payload, 'q' => $payload]);
            $res = $this->dispatch($req);

            $this->assertTrue(
                in_array($res->getStatusCode(), [200, 400, 422], true),
                "Donation search should handle payload gracefully without 500 error: {$payload} (got {$res->getStatusCode()})"
            );

            // Verify database was not tampered with
            $count = (int) $this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $this->assertGreaterThan(0, $count, "Users table must remain intact");
        }
    }

    /**
     * Reporting endpoint handles SQL injection in metric/interval/date filters.
     */
    public function testReportRepositoryTrendsResistsSqlInjection(): void
    {
        $maliciousInputs = [
            "completions' OR '1'='1",
            "'; DROP TABLE donations; --",
            "day' UNION SELECT 1, 1--",
        ];

        foreach ($maliciousInputs as $badInput) {
            // Must not execute SQL injection or crash
            $result = $this->reportRepo->getTrends('donations', $badInput, '2026-01-01', '2026-12-31');
            $this->assertIsArray($result);
        }

        // Verify donations table still exists
        $donationCount = (int) $this->pdo->query("SELECT COUNT(*) FROM donations")->fetchColumn();
        $this->assertSame(1, $donationCount, "Donations table must remain intact");
    }
}
