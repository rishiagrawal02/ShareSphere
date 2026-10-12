<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Http\ErrorHandler;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Support\Config;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * T-19.1-14: Error leakage and diagnostic shielding tests.
 * Asserts that internal errors, database exceptions, and unexpected failures
 * never reveal database credentials, raw SQL strings, file system paths, or traces.
 */
class ErrorLeakageTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        Config::load($root);

        $this->router = new Router();
    }

    public function testDatabaseErrorDoesNotLeakQueryOrCredentials(): void
    {
        $pdoException = new PDOException(
            'SQLSTATE[08006] [7] could not connect to server at "127.0.0.1:5433" with user "sharesphere_app" password "change-me": SELECT * FROM private_credentials'
        );

        $request = new Request('GET', '/api/test-leak', [], ['x-request-id' => 'req_test_leak_001']);
        $response = ErrorHandler::handle($pdoException, $request);

        $this->assertSame(500, $response->getStatusCode());
        $content = $response->getContent();

        // Must not expose sensitive words
        $this->assertStringNotContainsString('password', strtolower($content));
        $this->assertStringNotContainsString('change-me', $content);
        $this->assertStringNotContainsString('sharesphere_app', $content);
        $this->assertStringNotContainsString('private_credentials', $content);
        $this->assertStringNotContainsString('SQLSTATE', $content);

        $json = json_decode($content, true);
        $this->assertFalse($json['success']);
        $this->assertSame('INTERNAL_ERROR', $json['error']['code']);
        $this->assertSame('req_test_leak_001', $json['request_id']);
    }

    public function testUnhandledExceptionDoesNotLeakSystemPath(): void
    {
        $exception = new RuntimeException(
            'Crash at C:\\Users\\Rishi\\Downloads\\ShareSphere\\backend\\src\\PrivateInternal.php line 42'
        );

        $request = new Request('GET', '/api/test-leak-path', []);
        $response = ErrorHandler::handle($exception, $request);

        $content = $response->getContent();
        $this->assertStringNotContainsString('PrivateInternal.php', $content);
        $this->assertStringNotContainsString('C:\\Users', $content);
    }
}
