<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Http\ErrorHandler;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Support\Config;
use PHPUnit\Framework\TestCase;

/**
 * T-19.1-13: Security Headers and response hardening check.
 * Verifies presence of X-Content-Type-Options, Cache-Control, and audits CSP / Frame-Options.
 */
class SecurityHeadersTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        Config::load($root);

        $this->router = new Router();
        $routesFn = require $root . '/src/routes.php';
        $routesFn($this->router);
    }

    private function dispatch(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (\Throwable $e) {
            return ErrorHandler::handle($e, $request);
        }
    }

    public function testJsonResponsesContainNoSniffHeader(): void
    {
        $req = new Request('GET', '/api/health', []);
        $res = $this->dispatch($req);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('nosniff', $res->getHeader('x-content-type-options'));
    }

    public function testJsonResponsesPreventSensitiveCaching(): void
    {
        $req = new Request('GET', '/api/health', []);
        $res = $this->dispatch($req);

        $cacheControl = (string) $res->getHeader('cache-control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
    }

    public function testErrorResponsesContainNoSniffHeader(): void
    {
        $req = new Request('GET', '/api/nonexistent-route-404', []);
        $res = $this->dispatch($req);

        $this->assertSame(404, $res->getStatusCode());
        $this->assertSame('nosniff', $res->getHeader('x-content-type-options'));
    }

    /**
     * Regression test for SEC-01: Ensure security headers (X-Frame-Options,
     * Referrer-Policy, Permissions-Policy, CSP frame-ancestors) are sent.
     */
    public function testDefenseInDepthSecurityHeadersAreEmitted(): void
    {
        $req = new Request('GET', '/api/health', []);
        $res = $this->dispatch($req);

        $this->assertSame('DENY', $res->getHeader('x-frame-options'), 'X-Frame-Options must be DENY');
        $this->assertSame('strict-origin-when-cross-origin', $res->getHeader('referrer-policy'), 'Referrer-Policy must be strict-origin-when-cross-origin');
        $this->assertNotEmpty($res->getHeader('permissions-policy'), 'Permissions-Policy header must be present');
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $res->getHeader('content-security-policy'), 'CSP frame-ancestors must be none');
    }
}
