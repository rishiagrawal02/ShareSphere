<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\Exceptions\ForbiddenException;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\OwnershipMiddleware;
use App\Middleware\RoleMiddleware;
use PHPUnit\Framework\TestCase;

class RoleAndOwnershipMiddlewareTest extends TestCase
{
    public function testRoleMiddlewareAllowsPermittedRole(): void
    {
        $middleware = new RoleMiddleware('admin', 'ngo');
        $req = new Request('GET', '/api/test');
        $req->setAttribute('auth_role', 'admin');

        $res = $middleware->handle($req, function (Request $r) {
            return Response::success(['authorized' => true]);
        });

        $this->assertSame(200, $res->getStatusCode());
    }

    public function testRoleMiddlewareBlocksForbiddenRole(): void
    {
        $middleware = new RoleMiddleware('admin');
        $req = new Request('GET', '/api/test');
        $req->setAttribute('auth_role', 'donor');

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('You do not have permission to perform this action');

        $middleware->handle($req, fn($r) => Response::success());
    }

    public function testOwnershipMiddlewareAllowsOwner(): void
    {
        $middleware = new OwnershipMiddleware('id');
        $req = new Request('GET', '/api/users/5');
        $req->setAttribute('auth_user_id', 5);
        $req->setAttribute('auth_role', 'donor');
        $req->setAttribute('id', 5);

        $res = $middleware->handle($req, fn($r) => Response::success(['ok' => true]));
        $this->assertSame(200, $res->getStatusCode());
    }

    public function testOwnershipMiddlewareAllowsAdminBypass(): void
    {
        $middleware = new OwnershipMiddleware('id');
        $req = new Request('GET', '/api/users/5');
        $req->setAttribute('auth_user_id', 1);
        $req->setAttribute('auth_role', 'admin');
        $req->setAttribute('id', 5);

        $res = $middleware->handle($req, fn($r) => Response::success(['ok' => true]));
        $this->assertSame(200, $res->getStatusCode());
    }

    public function testOwnershipMiddlewareBlocksDifferentUser(): void
    {
        $middleware = new OwnershipMiddleware('id');
        $req = new Request('GET', '/api/users/5');
        $req->setAttribute('auth_user_id', 99);
        $req->setAttribute('auth_role', 'donor');
        $req->setAttribute('id', 5);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('You do not own this resource');

        $middleware->handle($req, fn($r) => Response::success());
    }
}
