<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AllocationService;
use App\Support\Paginator;

class RequestController
{
    private AllocationService $allocationService;

    public function __construct(?AllocationService $allocationService = null)
    {
        $this->allocationService = $allocationService ?? new AllocationService();
    }

    /**
     * POST /api/requests
     */
    public function store(Request $request): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $data = $request->getJsonBody() ?? [];
        $idempotencyKey = $request->getHeader('Idempotency-Key');

        $result = $this->allocationService->createRequest($userId, $data, $idempotencyKey);

        return Response::json(['data' => $result], 201);
    }

    /**
     * GET /api/requests
     */
    public function index(Request $request): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $role = (string) $request->getAttribute('user_role');
        $params = $request->getQueryParams();

        $page = isset($params['page']) ? (int) $params['page'] : 1;
        $perPage = isset($params['per_page']) ? (int) $params['per_page'] : 20;
        $pagination = Paginator::sanitize($page, $perPage);

        $filters = [];
        if (!empty($params['status'])) {
            $filters['status'] = (string) $params['status'];
        }
        if (!empty($params['donation_id'])) {
            $filters['donation_id'] = (int) $params['donation_id'];
        }
        if (!empty($params['ngo_id'])) {
            $filters['ngo_id'] = (int) $params['ngo_id'];
        }

        $result = $this->allocationService->listRequests($userId, $role, $filters, $pagination['limit'], $pagination['offset']);
        $meta = Paginator::buildMeta($result['total'], $pagination['page'], $pagination['per_page']);

        return Response::json([
            'data' => $result['items'],
            'meta' => $meta,
        ]);
    }

    /**
     * GET /api/requests/{id}
     */
    public function show(Request $request, array $params): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $role = (string) $request->getAttribute('user_role');
        $requestId = (int) $params['id'];

        $result = $this->allocationService->getRequest($userId, $role, $requestId);

        return Response::json(['data' => $result]);
    }

    /**
     * POST /api/requests/{id}/accept
     */
    public function accept(Request $request, array $params): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $requestId = (int) $params['id'];

        $result = $this->allocationService->acceptRequest($userId, $requestId);

        return Response::json([
            'data'    => $result,
            'message' => 'Donation request accepted successfully',
        ]);
    }

    /**
     * POST /api/requests/{id}/reject
     */
    public function reject(Request $request, array $params): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $requestId = (int) $params['id'];
        $body = $request->getJsonBody() ?? [];
        $reason = isset($body['reason']) ? trim((string) $body['reason']) : null;

        $result = $this->allocationService->rejectRequest($userId, $requestId, $reason);

        return Response::json([
            'data'    => $result,
            'message' => 'Donation request rejected',
        ]);
    }

    /**
     * POST /api/requests/{id}/cancel
     */
    public function cancel(Request $request, array $params): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $requestId = (int) $params['id'];
        $body = $request->getJsonBody() ?? [];
        $reason = isset($body['reason']) ? trim((string) $body['reason']) : null;

        $result = $this->allocationService->cancelRequest($userId, $requestId, $reason);

        return Response::json([
            'data'    => $result,
            'message' => 'Donation request cancelled',
        ]);
    }
}
