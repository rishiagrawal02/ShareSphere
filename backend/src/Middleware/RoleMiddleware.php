<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Exceptions\ForbiddenException;
use App\Http\Request;
use App\Http\Response;

/**
 * RBAC role-guard middleware.
 *
 * Usage in route definition:
 *   new RoleMiddleware('admin')
 *   new RoleMiddleware('admin', 'ngo')      ← any of the listed roles
 *
 * Must run AFTER AuthMiddleware (depends on auth_role attribute).
 */
class RoleMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private array $allowedRoles;

    public function __construct(string ...$roles)
    {
        if (empty($roles)) {
            throw new \InvalidArgumentException('RoleMiddleware requires at least one allowed role');
        }
        $this->allowedRoles = $roles;
    }

    public function handle(Request $request, callable $next): Response
    {
        $role = $request->getAttribute('auth_role');

        if ($role === null || !in_array($role, $this->allowedRoles, true)) {
            throw new ForbiddenException(
                'You do not have permission to perform this action',
                'INSUFFICIENT_ROLE'
            );
        }

        return $next($request);
    }
}
