<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\RequestIdMiddleware;
use PHPUnit\Framework\TestCase;

class MiddlewareTest extends TestCase
{
    public function testRequestIdMiddlewareGeneratesAndEchoesHeader(): void
    {
        $middleware = new RequestIdMiddleware();
        $request = new Request('GET', '/api/test');

        $response = $middleware->handle($request, function (Request $req) {
            $this->assertNotEmpty($req->getRequestId());
            return Response::success(['ok' => true]);
        });

        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-Request-Id', $headers);
        $this->assertSame($request->getRequestId(), $headers['X-Request-Id']);
    }
}
