<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;
use App\Support\SessionManager;

class AuthController
{
    private AuthService $authService;
    private \App\Services\NgoService $ngoService;

    public function __construct(?AuthService $authService = null, ?\App\Services\NgoService $ngoService = null)
    {
        $this->authService = $authService ?? new AuthService();
        $this->ngoService = $ngoService ?? new \App\Services\NgoService();
    }

    /**
     * POST /api/auth/register
     * Public donor or NGO registration
     */
    public function register(Request $request): Response
    {
        $input = $request->getBody();
        $role = $input['role'] ?? 'donor';

        if ($role === 'ngo') {
            $result = $this->ngoService->registerNgo($input, $request->getFiles());
            return Response::json([
                'status'  => 'ok',
                'message' => 'NGO registration submitted for approval',
                'user'    => $result['user'],
                'ngo'     => $result['ngo'],
            ], 201);
        }

        $user = $this->authService->registerDonor($input);

        return Response::json([
            'status'  => 'ok',
            'message' => 'Registration successful',
            'user'    => $user,
        ], 201);
    }

    /**
     * POST /api/auth/login
     */
    public function login(Request $request): Response
    {
        $input = $request->getBody();
        $email    = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        $user = $this->authService->login($email, $password);

        return Response::json([
            'status'  => 'ok',
            'message' => 'Login successful',
            'user'    => $user,
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): Response
    {
        $this->authService->logout();

        return Response::json([
            'status'  => 'ok',
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * GET /api/auth/me
     * Returns currently authenticated user
     */
    public function me(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();

        if ($user === null) {
            return Response::json([
                'status'        => 'error',
                'error_code'    => 'UNAUTHENTICATED',
                'message'       => 'Not authenticated',
            ], 401);
        }

        return Response::json([
            'status' => 'ok',
            'user'   => $user,
        ]);
    }

    /**
     * POST /api/auth/csrf-token
     * Issue a new CSRF token (or return the current one from session)
     */
    public function csrfToken(Request $request): Response
    {
        SessionManager::start();
        $token = \App\Support\CsrfGuard::getToken();

        return Response::json([
            'status' => 'ok',
            'csrf_token' => $token,
        ]);
    }
}
