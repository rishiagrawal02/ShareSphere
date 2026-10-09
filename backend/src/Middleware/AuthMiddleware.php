<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Exceptions\UnauthorizedException;
use App\Http\Request;
use App\Http\Response;
use App\Support\SessionManager;

/**
 * Ensures a valid, non-expired session exists before passing to the next handler.
 * On failure throws UnauthorizedException (→ 401 JSON response).
 */
class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $userId = SessionManager::getUserId();

        if ($userId === null) {
            throw new UnauthorizedException('Authentication required', 'UNAUTHENTICATED');
        }

        // Attach user context to request for downstream handlers
        $request->setAttribute('auth_user_id', $userId);
        $request->setAttribute('auth_role', SessionManager::getUserRole());

        return $next($request);
    }
}
