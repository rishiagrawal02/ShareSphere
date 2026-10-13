<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;
use App\Support\SessionManager;

class AuthController
{
    private ?AuthService $authService = null;
    private ?\App\Services\NgoService $ngoService = null;

    public function __construct(?AuthService $authService = null, ?\App\Services\NgoService $ngoService = null)
    {
        $this->authService = $authService;
        $this->ngoService = $ngoService;
    }

    private function getAuthService(): AuthService
    {
        if ($this->authService === null) {
            $this->authService = new AuthService();
        }
        return $this->authService;
    }

    private function getNgoService(): \App\Services\NgoService
    {
        if ($this->ngoService === null) {
            $this->ngoService = new \App\Services\NgoService();
        }
        return $this->ngoService;
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
            $result = $this->getNgoService()->registerNgo($input, $request->getFiles());
            return Response::json([
                'status'  => 'ok',
                'message' => 'NGO registration submitted for approval',
                'user'    => $result['user'],
                'ngo'     => $result['ngo'],
            ], 201);
        }

        $user = $this->getAuthService()->registerDonor($input);

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

        $user = $this->getAuthService()->login($email, $password);

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
        $this->getAuthService()->logout();

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
        $user = $this->getAuthService()->getCurrentUser();

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
