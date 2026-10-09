<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Exceptions\ForbiddenException;
use App\Http\Request;
use App\Http\Response;

/**
 * Ownership guard — ensures the authenticated user can only touch their own
 * resources unless they hold a privileged role (admin).
 *
 * The resolved route param (default: "id") is compared against auth_user_id.
 * Admins bypass the check unconditionally.
 *
 * Usage:
 *   new OwnershipMiddleware()                   ← uses route param "id"
 *   new OwnershipMiddleware('userId')           ← uses route param "userId"
 *   new OwnershipMiddleware('donorId', 'admin', 'ngo')  ← privileged roles
 */
class OwnershipMiddleware implements MiddlewareInterface
{
    private string $paramName;
    /** @var string[] Roles that bypass the ownership check */
    private array $privilegedRoles;

    public function __construct(string $paramName = 'id', string ...$privilegedRoles)
    {
        $this->paramName      = $paramName;
        $this->privilegedRoles = empty($privilegedRoles) ? ['admin'] : $privilegedRoles;
    }

    public function handle(Request $request, callable $next): Response
    {
        $role   = $request->getAttribute('auth_role');
        $userId = $request->getAttribute('auth_user_id');

        // Privileged roles bypass ownership check
        if (in_array($role, $this->privilegedRoles, true)) {
            return $next($request);
        }

        $resourceOwnerId = $request->getAttribute($this->paramName);

        if ($resourceOwnerId === null) {
            // Param not in URL — cannot verify ownership, deny
            throw new ForbiddenException('Ownership cannot be determined', 'OWNERSHIP_INDETERMINATE');
        }

        if ((int) $resourceOwnerId !== (int) $userId) {
            throw new ForbiddenException('You do not own this resource', 'RESOURCE_FORBIDDEN');
        }

        return $next($request);
    }
}
