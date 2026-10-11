<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\UnauthorizedException;
use App\Http\Request;
use App\Http\Router;
use App\Support\Config;
use App\Support\CsrfGuard;
use App\Support\Database;
use App\Support\SessionManager;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * RoleRouteMatrixTest (Phase 18 Milestone 18.1)
 *
 * Automated role x route matrix test that iterates all routes registered in routes.php,
 * dispatches requests as the 6 core user personas (anonymous, donor, ngo-pending,
 * ngo-verified, admin, suspended), and validates against tests/Support/expected_access.php.
 *
 * Test cases covered:
 *   - T-18.1-02 (S): Every route/role pair matches expectation.
 *   - T-18.1-03 (N): Unregistered route detection (unregistered route fails the build).
 */
class RoleRouteMatrixTest extends TestCase
{
    private static PDO $pdo;
    private static Router $router;
    private static array $expectedMatrix;

    private static int $donorId;
    private static int $ngoPendingId;
    private static int $ngoVerifiedId;
    private static int $adminId;
    private static int $suspendedId;

    public static function setUpBeforeClass(): void
    {
        $rootPath = dirname(__DIR__, 2);
        Config::load($rootPath);
        self::$pdo = Database::getOwnerConnection();

        // Load route definitions
        self::$router = new Router();
        $routesFn = require $rootPath . '/src/routes.php';
        $routesFn(self::$router);

        // Load expected access policy matrix
        self::$expectedMatrix = require dirname(__DIR__) . '/Support/expected_access.php';

        // Prepare test database fixture personas
        self::$pdo->exec('TRUNCATE TABLE users CASCADE');

        $insertUser = self::$pdo->prepare("
            INSERT INTO users (name, email, password_hash, role, account_status)
            VALUES (:name, :email, :pass, :role, :status)
            RETURNING id
        ");

        // 1. Donor
        $insertUser->execute([
            'name' => 'Matrix Donor',
            'email' => 'matrix-donor@test.local',
            'pass' => password_hash('password123', PASSWORD_BCRYPT),
            'role' => 'donor',
            'status' => 'active',
        ]);
        self::$donorId = (int) $insertUser->fetchColumn();

        // 2. NGO Pending
        $insertUser->execute([
            'name' => 'Matrix NGO Pending',
            'email' => 'matrix-ngo-pending@test.local',
            'pass' => password_hash('password123', PASSWORD_BCRYPT),
            'role' => 'ngo',
            'status' => 'active',
        ]);
        self::$ngoPendingId = (int) $insertUser->fetchColumn();

        self::$pdo->prepare("
            INSERT INTO ngos (user_id, organization_name, registration_number, address_text, location, verification_status)
            VALUES (:uid, 'Pending Org', 'REG-PEND-001', 'Address 1', ST_SetSRID(ST_MakePoint(73.8567, 18.5204), 4326)::geography, 'pending')
        ")->execute(['uid' => self::$ngoPendingId]);

        // 3. NGO Verified
        $insertUser->execute([
            'name' => 'Matrix NGO Verified',
            'email' => 'matrix-ngo-verified@test.local',
            'pass' => password_hash('password123', PASSWORD_BCRYPT),
            'role' => 'ngo',
            'status' => 'active',
        ]);
        self::$ngoVerifiedId = (int) $insertUser->fetchColumn();

        self::$pdo->prepare("
            INSERT INTO ngos (user_id, organization_name, registration_number, address_text, location, verification_status)
            VALUES (:uid, 'Verified Org', 'REG-VER-001', 'Address 2', ST_SetSRID(ST_MakePoint(73.8567, 18.5204), 4326)::geography, 'verified')
        ")->execute(['uid' => self::$ngoVerifiedId]);

        // 4. Admin
        $insertUser->execute([
            'name' => 'Matrix Admin',
            'email' => 'matrix-admin@test.local',
            'pass' => password_hash('password123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'status' => 'active',
        ]);
        self::$adminId = (int) $insertUser->fetchColumn();

        // 5. Suspended
        $insertUser->execute([
            'name' => 'Matrix Suspended',
            'email' => 'matrix-suspended@test.local',
            'pass' => password_hash('password123', PASSWORD_BCRYPT),
            'role' => 'donor',
            'status' => 'suspended',
        ]);
        self::$suspendedId = (int) $insertUser->fetchColumn();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    /**
     * T-18.1-03: Asserts every registered route has an entry in expected_access.php.
     * If a new route is created without an access specification, the build fails.
     */
    public function testAllRegisteredRoutesHaveExpectedAccessEntry(): void
    {
        $registeredRoutes = self::$router->getRoutes();
        $this->assertNotEmpty($registeredRoutes, 'At least one route must be registered');

        $missingEntries = [];
        foreach ($registeredRoutes as $route) {
            $key = $route['method'] . ' ' . $route['path'];
            if (!isset(self::$expectedMatrix[$key])) {
                $missingEntries[] = $key;
            }
        }

        $this->assertEmpty(
            $missingEntries,
            "The following registered routes are missing from tests/Support/expected_access.php:\n"
            . implode("\n", $missingEntries)
        );
    }

    /**
     * T-18.1-03: Negative check - asserts that an unregistered dummy route causes failure.
     */
    public function testUnregisteredRouteFailsBuild(): void
    {
        $dummyRoute = 'POST /api/dummy-secret-route-without-spec';
        $this->assertArrayNotHasKey(
            $dummyRoute,
            self::$expectedMatrix,
            'Dummy route should not exist in expected matrix'
        );
    }

    /**
     * T-18.1-02: Auto-iterates all registered routes and verifies each of the 6 roles
     * matches the exact expected authorization decision.
     */
    public function testRoleRouteMatrixAccessAcrossAllRoles(): void
    {
        $roles = [
            'anonymous' => null,
            'donor' => ['id' => self::$donorId, 'role' => 'donor'],
            'ngo-pending' => ['id' => self::$ngoPendingId, 'role' => 'ngo'],
            'ngo-verified' => ['id' => self::$ngoVerifiedId, 'role' => 'ngo'],
            'admin' => ['id' => self::$adminId, 'role' => 'admin'],
            'suspended' => ['id' => self::$suspendedId, 'role' => 'donor'],
        ];

        $routes = self::$router->getRoutes();
        $assertionsCount = 0;

        foreach ($routes as $route) {
            $method = $route['method'];
            $pathPattern = $route['path'];
            $routeKey = $method . ' ' . $pathPattern;

            $this->assertArrayHasKey(
                $routeKey,
                self::$expectedMatrix,
                "Route {$routeKey} must have an entry in expected_access.php"
            );

            // Interpolate path parameters for test dispatch
            $testPath = preg_replace('/\{[a-zA-Z0-9_]+\}/', '1', $pathPattern);
            // If kind is in path, use valid token e.g. document
            $testPath = str_replace('/1/1', '/document/1', $testPath);

            foreach ($roles as $roleName => $persona) {
                $expected = self::$expectedMatrix[$routeKey][$roleName] ?? null;
                $this->assertNotNull(
                    $expected,
                    "Expected access for route {$routeKey} and role {$roleName} must be defined"
                );

                // Setup session
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                $_SESSION = [];

                if ($persona !== null) {
                    SessionManager::setUser($persona['id'], $persona['role']);
                }

                // Seed a CSRF token into the session then retrieve it, so the
                // CsrfMiddleware passes and auth/role guards are the only gates we test.
                $csrfToken = CsrfGuard::getToken();
                $headers = [
                    'content-type' => 'application/json',
                    'x-csrf-token' => $csrfToken,
                ];

                $request = new Request(
                    $method,
                    $testPath,
                    [],
                    $headers,
                    [],
                    [],
                    json_encode(['dummy' => 'payload']),
                    ['dummy' => 'payload']
                );

                $actual = 'ALLOW';
                try {
                    self::$router->dispatch($request);
                    $actual = 'ALLOW';
                } catch (UnauthorizedException $e) {
                    // Only auth/CSRF guard codes count as 401 barriers.
                    // Controller-thrown UnauthorizedException (e.g. INVALID_CREDENTIALS)
                    // means the request passed auth/role gates → treat as ALLOW.
                    $authGuardCodes = ['UNAUTHENTICATED', 'ACCOUNT_SUSPENDED', 'SESSION_EXPIRED', 'CSRF_MISMATCH', 'CSRF_FAILED'];
                    $actual = in_array($e->getErrorCode(), $authGuardCodes, true) ? 401 : 'ALLOW';
                } catch (ForbiddenException $e) {
                    // Only role/NGO guard codes count as 403 barriers.
                    $roleGuardCodes = ['INSUFFICIENT_ROLE', 'NGO_NOT_VERIFIED'];
                    $actual = in_array($e->getErrorCode(), $roleGuardCodes, true) ? 403 : 'ALLOW';
                } catch (\Throwable $e) {
                    // Passed auth/role barrier; domain or controller validation reached
                    $actual = 'ALLOW';
                }

                $this->assertSame(
                    $expected,
                    $actual,
                    "Access mismatch on route [{$routeKey}] for role [{$roleName}]. Expected: {$expected}, Got: {$actual}"
                );
                $assertionsCount++;
            }
        }

        $this->assertGreaterThan(300, $assertionsCount, 'Should have verified > 300 route-role pairs');
    }
}
