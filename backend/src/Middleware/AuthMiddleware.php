<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Exceptions\UnauthorizedException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;
use App\Support\SessionManager;

/**
 * Ensures a valid, non-expired session exists and user is active in DB.
 * On failure throws UnauthorizedException (→ 401 JSON response).
 */
class AuthMiddleware implements MiddlewareInterface
{
    private ?UserRepository $userRepo;

    public function __construct(?UserRepository $userRepo = null)
    {
        $this->userRepo = $userRepo;
    }

    public function handle(Request $request, callable $next): Response
    {
        $userId = SessionManager::getUserId();

        if ($userId === null) {
            throw new UnauthorizedException('Authentication required', 'UNAUTHENTICATED');
        }

        $repo = $this->userRepo ?? new UserRepository();
        $user = $repo->findById($userId);

        if ($user === null || ($user['account_status'] ?? 'active') !== 'active') {
            SessionManager::destroy();
            throw new UnauthorizedException('Account is suspended or does not exist', 'ACCOUNT_SUSPENDED');
        }

        // Attach verified user context to request for downstream handlers
        $request->setAttribute('auth_user_id', (int) $user['id']);
        $request->setAttribute('auth_role', (string) $user['role']);
        $request->setAttribute('auth_user', $user);

        return $next($request);
    }
}

