<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Exceptions\ForbiddenException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;

class VerifiedNgoMiddleware implements MiddlewareInterface
{
    private UserRepository $userRepo;

    public function __construct(?UserRepository $userRepo = null)
    {
        $this->userRepo = $userRepo ?? new UserRepository();
    }

    public function handle(Request $request, callable $next): Response
    {
        $role = $request->getAttribute('auth_role');
        $userId = $request->getAttribute('auth_user_id');

        if ($role === 'admin') {
            return $next($request);
        }

        if ($role !== 'ngo' || $userId === null) {
            throw new ForbiddenException('Verified NGO privileges required', 'NGO_NOT_VERIFIED');
        }

        $ngo = $this->userRepo->findNgoByUserId((int) $userId);
        if ($ngo === null || $ngo['verification_status'] !== 'verified') {
            throw new ForbiddenException(
                'Your NGO account is pending approval or has not been verified',
                'NGO_NOT_VERIFIED'
            );
        }

        $request->setAttribute('auth_ngo_id', (int) $ngo['id']);
        $request->setAttribute('ngo', $ngo);

        return $next($request);
    }
}
