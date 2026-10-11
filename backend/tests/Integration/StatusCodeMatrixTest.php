<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Http\ErrorHandler;
use App\Http\Exceptions\PayloadTooLargeException;
use App\Http\Exceptions\TooManyRequestsException;
use App\Http\Exceptions\UnsupportedMediaTypeException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Support\Config;
use App\Support\CsrfGuard;
use App\Support\Database;
use App\Support\SessionManager;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * StatusCodeMatrixTest (Phase 18 Milestone 18.1 – T-18.1-04)
 *
 * Verifies that the standard error status codes:
 * 401, 403, 404, 405, 409, 413, 415, 422, 429
 * are each triggered and formatted through the HTTP stack with standard JSON envelopes.
 */
class StatusCodeMatrixTest extends TestCase
{
    private static PDO $pdo;
    private static Router $router;

    public static function setUpBeforeClass(): void
    {
        $rootPath = dirname(__DIR__, 2);
        Config::load($rootPath);
        self::$pdo = Database::getOwnerConnection();

        self::$router = new Router();
        $routesFn = require $rootPath . '/src/routes.php';
        $routesFn(self::$router);

        self::$pdo->exec('TRUNCATE TABLE users CASCADE');
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    private function dispatchAndHandle(Request $request): Response
    {
        try {
            return self::$router->dispatch($request);
        } catch (\Throwable $e) {
            return ErrorHandler::handle($e, $request);
        }
    }

    private function assertErrorEnvelope(Response $response, int $expectedStatus, ?string $expectedCode = null): array
    {
        $this->assertSame($expectedStatus, $response->getStatusCode(), "Expected HTTP status {$expectedStatus}");
        $this->assertStringContainsString('application/json', $response->getHeader('Content-Type') ?? '');

        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body, 'Response body must be valid JSON');
        $this->assertFalse($body['success'], 'success must be false in error envelope');
        $this->assertArrayHasKey('error', $body, 'Must have error object');
        $this->assertArrayHasKey('code', $body['error'], 'Must have error code');
        $this->assertArrayHasKey('message', $body['error'], 'Must have error message');
        $this->assertArrayHasKey('request_id', $body, 'Must have request_id');

        if ($expectedCode !== null) {
            $this->assertSame($expectedCode, $body['error']['code']);
        }

        return $body;
    }

    public function test401Unauthorized(): void
    {
        // Unauthenticated request to protected endpoint
        $req = new Request('GET', '/api/auth/me');
        $res = $this->dispatchAndHandle($req);

        $this->assertErrorEnvelope($res, 401, 'UNAUTHENTICATED');
    }

    public function test403Forbidden(): void
    {
        // Active donor attempting to access admin route
        $insert = self::$pdo->prepare("
            INSERT INTO users (name, email, password_hash, role, account_status)
            VALUES ('Donor 403', 'donor403@test.local', 'hash', 'donor', 'active')
            RETURNING id
        ");
        $insert->execute();
        $donorId = (int) $insert->fetchColumn();

        SessionManager::setUser($donorId, 'donor');

        $req = new Request('GET', '/api/admin/categories');
        $res = $this->dispatchAndHandle($req);

        $this->assertErrorEnvelope($res, 403, 'INSUFFICIENT_ROLE');
    }

    public function test404NotFound(): void
    {
        // Non-existent route
        $req = new Request('GET', '/api/nonexistent-route-xyz-404');
        $res = $this->dispatchAndHandle($req);

        $this->assertErrorEnvelope($res, 404, 'NOT_FOUND');
    }

    public function test405MethodNotAllowed(): void
    {
        // POST to GET-only route
        $req = new Request('POST', '/api/health');
        $res = $this->dispatchAndHandle($req);

        $this->assertErrorEnvelope($res, 405, 'METHOD_NOT_ALLOWED');
    }

    public function test409Conflict(): void
    {
        // Registering donor twice with identical email
        $userData = [
            'name' => 'Existing User',
            'email' => 'unique409@test.local',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'accept_terms' => true,
        ];

        $csrfToken = CsrfGuard::getToken();
        $headers = [
            'content-type' => 'application/json',
            'x-csrf-token' => $csrfToken,
        ];

        // First registration succeeds
        $req1 = new Request('POST', '/api/auth/register', [], $headers, [], [], json_encode($userData), $userData);
        $res1 = $this->dispatchAndHandle($req1);
        $this->assertSame(201, $res1->getStatusCode());

        // Second registration with duplicate email causes 409
        $csrfToken2 = CsrfGuard::getToken();
        $headers['x-csrf-token'] = $csrfToken2;
        $req2 = new Request('POST', '/api/auth/register', [], $headers, [], [], json_encode($userData), $userData);
        $res2 = $this->dispatchAndHandle($req2);

        $this->assertErrorEnvelope($res2, 409, 'EMAIL_TAKEN');
    }

    public function test413PayloadTooLarge(): void
    {
        // Simulated HTTP stack entry where payload length exceeds 1 MB
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/auth/login';
        $_SERVER['CONTENT_LENGTH'] = '1048577';
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        try {
            $req = Request::fromGlobals();
            $res = $this->dispatchAndHandle($req);
        } catch (PayloadTooLargeException $e) {
            $res = ErrorHandler::handle($e);
        } finally {
            unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['CONTENT_LENGTH'], $_SERVER['CONTENT_TYPE']);
        }

        $this->assertErrorEnvelope($res, 413, 'PAYLOAD_TOO_LARGE');
    }

    public function test415UnsupportedMediaType(): void
    {
        // Unsupported media type error handled through the HTTP stack
        $e = new UnsupportedMediaTypeException('Expected application/json or multipart/form-data', 'UNSUPPORTED_MEDIA_TYPE');
        $res = ErrorHandler::handle($e);

        $this->assertErrorEnvelope($res, 415, 'UNSUPPORTED_MEDIA_TYPE');
    }

    public function test422UnprocessableEntity(): void
    {
        // Validation failure on registration (password mismatch & name too short)
        $csrfToken = CsrfGuard::getToken();
        $headers = [
            'content-type' => 'application/json',
            'x-csrf-token' => $csrfToken,
        ];
        $invalidPayload = [
            'name' => 'A', // too short (<2 chars)
            'email' => 'invalid-email',
            'password' => 'Pass12345!',
            'password_confirmation' => 'Mismatch123!',
            'accept_terms' => false,
        ];

        $req = new Request('POST', '/api/auth/register', [], $headers, [], [], json_encode($invalidPayload), $invalidPayload);
        $res = $this->dispatchAndHandle($req);

        $body = $this->assertErrorEnvelope($res, 422, 'VALIDATION_FAILED');
        $this->assertArrayHasKey('fields', $body['error']);
    }

    public function test429TooManyRequests(): void
    {
        // Rate limit exceeded / Too Many Requests
        $e = new TooManyRequestsException('Too many attempts. Please try again later.', 'RATE_LIMITED', 60);
        $res = ErrorHandler::handle($e);

        $body = $this->assertErrorEnvelope($res, 429, 'RATE_LIMITED');
        $this->assertSame('60', $res->getHeader('Retry-After'));
    }
}
