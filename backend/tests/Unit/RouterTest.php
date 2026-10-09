<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\Exceptions\BadRequestException;
use App\Http\Exceptions\MethodNotAllowedException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testRouteMatchWithNumericId(): void
    {
        $this->router->get('/api/donations/{id}', function (Request $req, int $id) {
            return Response::success(['id' => $id]);
        });

        $req = new Request('GET', '/api/donations/42');
        $res = $this->router->dispatch($req);

        $this->assertSame(200, $res->getStatusCode());
        $data = json_decode($res->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(42, $data['data']['id']);
    }

    public function testNotFoundExceptionForNonMatchingRoute(): void
    {
        $this->router->get('/api/health', fn() => Response::success(['ok' => true]));

        $this->expectException(NotFoundException::class);
        $req = new Request('GET', '/api/non-existent');
        $this->router->dispatch($req);
    }

    public function testMethodNotAllowedExceptionWithAllowHeader(): void
    {
        $this->router->get('/api/health', fn() => Response::success(['ok' => true]));

        try {
            $req = new Request('POST', '/api/health');
            $this->router->dispatch($req);
            $this->fail('Expected MethodNotAllowedException');
        } catch (MethodNotAllowedException $e) {
            $this->assertSame(405, $e->getStatusCode());
            $this->assertSame('METHOD_NOT_ALLOWED', $e->getErrorCode());
            $headers = $e->getHeaders();
            $this->assertArrayHasKey('Allow', $headers);
            $this->assertSame('GET', $headers['Allow']);
        }
    }

    public function testNonNumericIdThrowsNotFound(): void
    {
        $this->router->get('/api/donations/{id}', function (Request $req, int $id) {
            return Response::success(['id' => $id]);
        });

        $this->expectException(NotFoundException::class);
        $req = new Request('GET', '/api/donations/abc');
        $this->router->dispatch($req);
    }
}
