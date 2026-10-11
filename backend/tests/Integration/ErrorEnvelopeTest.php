<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Http\ErrorHandler;
use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\NotFoundException;
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
use RuntimeException;

/**
 * ErrorEnvelopeTest (Phase 17)
 *
 * Validates that every HTTP error code produced by the application conforms to
 * the standard error envelope:
 *
 *   {
 *     "success": false,
 *     "error": { "code": "<CODE>", "message": "<string>" },
 *     "request_id": "<string>"
 *   }
 *
 * This contract is relied on by the frontend errorMessages.js mapper.
 */
class ErrorEnvelopeTest extends TestCase
{
    private PDO $pdo;
    private Router $router;
    private AuthService $authService;
    private UserRepository $userRepo;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $rootPath = dirname(__DIR__, 2);
        Config::load($rootPath);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec('TRUNCATE TABLE users CASCADE');

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

    // -------------------------------------------------------------------------
    // Helper: Assert standard error envelope shape
    // -------------------------------------------------------------------------

    private function assertEnvelope(Response $response): array
    {
        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body, 'Response body must be valid JSON array');
        $this->assertArrayHasKey('success', $body, 'Envelope must have "success" key');
        $this->assertFalse($body['success'], '"success" must be false for error responses');
        $this->assertArrayHasKey('error', $body, 'Envelope must have "error" key');
        $this->assertIsArray($body['error'], '"error" must be an array');
        $this->assertArrayHasKey('code', $body['error'], '"error.code" must be present');
        $this->assertArrayHasKey('message', $body['error'], '"error.message" must be present');
        $this->assertNotEmpty($body['error']['code'], '"error.code" must not be empty');
        $this->assertNotEmpty($body['error']['message'], '"error.message" must not be empty');
        $this->assertArrayHasKey('request_id', $body, 'Envelope must have top-level "request_id"');
        $this->assertNotEmpty($body['request_id'], '"request_id" must not be empty');

        return $body;
    }

    // -------------------------------------------------------------------------
    // ErrorHandler unit-level tests
    // -------------------------------------------------------------------------

    public function testErrorHandlerFormattsUnauthorizedException(): void
    {
        $e = new UnauthorizedException('UNAUTHENTICATED');
        $response = ErrorHandler::handle($e);

        $this->assertSame(401, $response->getStatusCode());
        $body = $this->assertEnvelope($response);
        $this->assertSame('UNAUTHENTICATED', $body['error']['code']);
    }

    public function testErrorHandlerFormattsForbiddenException(): void
    {
        $e = new ForbiddenException('FORBIDDEN');
        $response = ErrorHandler::handle($e);

        $this->assertSame(403, $response->getStatusCode());
        $body = $this->assertEnvelope($response);
        $this->assertSame('FORBIDDEN', $body['error']['code']);
    }

    public function testErrorHandlerFormattsNotFoundException(): void
    {
        $e = new NotFoundException('NOT_FOUND');
        $response = ErrorHandler::handle($e);

        $this->assertSame(404, $response->getStatusCode());
        $body = $this->assertEnvelope($response);
        $this->assertSame('NOT_FOUND', $body['error']['code']);
    }

    public function testErrorHandlerFormattsValidationFailedExceptionWithFields(): void
    {
        $e = new ValidationFailedException(['email' => 'Invalid email format.']);
        $response = ErrorHandler::handle($e);

        $this->assertSame(422, $response->getStatusCode());
        $body = $this->assertEnvelope($response);
        $this->assertSame('VALIDATION_FAILED', $body['error']['code']);
        $this->assertArrayHasKey('fields', $body['error'], 'Validation errors must include "fields"');
        $this->assertArrayHasKey('email', $body['error']['fields']);
    }

    public function testErrorHandlerFormattsConflictException(): void
    {
        $e = new ConflictException('CONFLICT');
        $response = ErrorHandler::handle($e);

        $this->assertSame(409, $response->getStatusCode());
        $body = $this->assertEnvelope($response);
        $this->assertSame('CONFLICT', $body['error']['code']);
    }

    public function testErrorHandlerMasksInternalErrorDetails(): void
    {
        $e = new RuntimeException('Database connection failed: pg_connect() ...');
        $response = ErrorHandler::handle($e);

        $this->assertSame(500, $response->getStatusCode());
        $body = $this->assertEnvelope($response);
        // Internal errors must NOT expose raw exception message to client
        $this->assertSame('INTERNAL_ERROR', $body['error']['code']);
        $this->assertStringNotContainsString(
            'Database connection failed',
            $body['error']['message'],
            'Internal error details must not leak to client'
        );
    }

    public function testRequestIdMatchesRegularExpression(): void
    {
        $e = new NotFoundException('NOT_FOUND');
        $response = ErrorHandler::handle($e);

        $body = json_decode($response->getContent(), true);
        $this->assertMatchesRegularExpression('/^req_[a-f0-9]+$/', $body['request_id']);
    }

    // -------------------------------------------------------------------------
    // Response::error static method shape tests
    // -------------------------------------------------------------------------

    public function testResponseErrorShapeWithoutFields(): void
    {
        $response = Response::error('RATE_LIMITED', 'Too many requests.', [], 429, 'req_abc123');
        $this->assertSame(429, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);

        $this->assertFalse($body['success']);
        $this->assertSame('RATE_LIMITED', $body['error']['code']);
        $this->assertSame('req_abc123', $body['request_id']);
        $this->assertArrayNotHasKey('fields', $body['error'], '"fields" must be omitted when empty');
    }

    public function testResponseErrorShapeWithFields(): void
    {
        $fields = ['name' => 'Name is required.', 'email' => 'Email is invalid.'];
        $response = Response::error('VALIDATION_FAILED', 'Validation error.', $fields, 422, 'req_def456');
        $body = json_decode($response->getContent(), true);

        $this->assertFalse($body['success']);
        $this->assertSame('VALIDATION_FAILED', $body['error']['code']);
        $this->assertSame($fields, $body['error']['fields']);
    }

    public function testResponseErrorOmitsRequestIdWhenNull(): void
    {
        $response = Response::error('INTERNAL_ERROR', 'Something failed.', [], 500, null);
        $body = json_decode($response->getContent(), true);

        $this->assertArrayNotHasKey('request_id', $body, '"request_id" must not be present when null');
    }

    public function testResponseContentTypeIsJson(): void
    {
        $response = Response::error('NOT_FOUND', 'Not found.', [], 404, 'req_ct123');
        $contentType = $response->getHeader('Content-Type');
        $this->assertStringContainsString('application/json', $contentType);
    }

    // -------------------------------------------------------------------------
    // Router-level: Unauthenticated access returns 401 envelope
    // -------------------------------------------------------------------------

    public function testRouterReturnsEnvelopedErrorOn401(): void
    {
        try {
            $req = new Request('GET', '/api/auth/me');
            $this->router->dispatch($req);
            $this->fail('Expected UnauthorizedException');
        } catch (UnauthorizedException $e) {
            $response = ErrorHandler::handle($e);
            $this->assertSame(401, $response->getStatusCode());
            $this->assertEnvelope($response);
        }
    }

    public function testRouterReturnsEnvelopedErrorOnCsrfFailure(): void
    {
        $bodyData = ['email' => 'x@example.com', 'password' => 'pass'];
        $req = new Request(
            'POST',
            '/api/auth/login',
            [],
            ['content-type' => 'application/json'],
            [],
            [],
            json_encode($bodyData),
            $bodyData
        );

        try {
            $this->router->dispatch($req);
            $this->fail('Expected CSRF UnauthorizedException');
        } catch (UnauthorizedException $e) {
            $response = ErrorHandler::handle($e);
            $this->assertSame(401, $response->getStatusCode());
            $body = $this->assertEnvelope($response);
            $this->assertContains(
                $body['error']['code'],
                ['CSRF_FAILED', 'CSRF_MISMATCH', 'UNAUTHENTICATED'],
                'CSRF failure must produce CSRF_FAILED, CSRF_MISMATCH or UNAUTHENTICATED code'
            );
        }
    }

    public function testRegistrationDuplicateEmailReturns409Envelope(): void
    {
        $userData = [
            'name' => 'Duplicate User',
            'email' => 'dup@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'accept_terms' => true,
        ];

        $this->authService->registerDonor($userData);

        try {
            $this->authService->registerDonor($userData);
            $this->fail('Expected ConflictException on duplicate email');
        } catch (ConflictException $e) {
            $response = ErrorHandler::handle($e);
            $this->assertSame(409, $response->getStatusCode());
            $this->assertEnvelope($response);
        }
    }

    public function testValidationFailureReturns422Envelope(): void
    {
        $userData = [
            'name' => 'Bad User',
            'email' => 'bad@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'MismatchPass999!',
            'accept_terms' => true,
        ];

        try {
            $this->authService->registerDonor($userData);
            $this->fail('Expected ValidationFailedException on password mismatch');
        } catch (ValidationFailedException $e) {
            $response = ErrorHandler::handle($e);
            $this->assertSame(422, $response->getStatusCode());
            $body = $this->assertEnvelope($response);
            $this->assertSame('VALIDATION_FAILED', $body['error']['code']);
        }
    }
}
