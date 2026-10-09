<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Exceptions\ValidationFailedException;
use App\Http\Request;
use App\Http\Response;
use App\Services\UserAdminService;

class AdminUserController
{
    private UserAdminService $service;

    public function __construct(?UserAdminService $service = null)
    {
        $this->service = $service ?? new UserAdminService();
    }

    /**
     * GET /api/admin/users
     */
    public function index(Request $request): Response
    {
        $params = $request->getQueryParams();
        $filters = [];

        if (!empty($params['role'])) {
            $filters['role'] = (string) $params['role'];
        }
        if (!empty($params['status'])) {
            $filters['status'] = (string) $params['status'];
        }
        if (!empty($params['q'])) {
            $filters['q'] = trim((string) $params['q']);
        }

        $page = isset($params['page']) ? (int) $params['page'] : 1;
        $limit = isset($params['limit']) ? (int) $params['limit'] : 20;

        $result = $this->service->listUsers($filters, $page, $limit);

        return Response::success($result['items'], [
            'total' => $result['total'],
            'page'  => $result['page'],
            'limit' => $result['limit'],
            'pages' => $result['pages'],
        ]);
    }

    /**
     * GET /api/admin/users/{id}
     */
    public function show(Request $request): Response
    {
        $userId = (int) $request->getAttribute('id');
        $user = $this->service->getUser($userId);

        return Response::success($user);
    }

    /**
     * PATCH /api/admin/users/{id}/status
     */
    public function updateStatus(Request $request): Response
    {
        $adminUserId = (int) $request->getAttribute('auth_user_id');
        $targetUserId = (int) $request->getAttribute('id');
        $body = $request->getJsonBody();

        $status = isset($body['status']) ? (string) $body['status'] : '';
        $reason = isset($body['reason']) ? (string) $body['reason'] : null;
        $cascade = !empty($body['cascade']);

        if ($status === '') {
            throw new ValidationFailedException(['status' => 'Status field is required']);
        }

        $updated = $this->service->updateStatus($adminUserId, $targetUserId, $status, $reason, $cascade);

        return Response::success($updated);
    }
}
