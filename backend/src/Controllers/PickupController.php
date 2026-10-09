<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\PickupService;
use App\Support\Paginator;

class PickupController
{
    private PickupService $pickupService;

    public function __construct(?PickupService $pickupService = null)
    {
        $this->pickupService = $pickupService ?? new PickupService();
    }

    /**
     * POST /api/pickups
     */
    public function store(Request $request): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $role = (string) $request->getAttribute('user_role');
        $body = $request->getJsonBody() ?? [];

        $result = $this->pickupService->proposePickup($userId, $role, $body);

        return Response::json(['data' => $result], 201);
    }

    /**
     * GET /api/pickups
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
        if (!empty($params['state'])) {
            $filters['state'] = (string) $params['state'];
        }
        if (!empty($params['from'])) {
            $filters['from'] = (string) $params['from'];
        }
        if (!empty($params['to'])) {
            $filters['to'] = (string) $params['to'];
        }

        $result = $this->pickupService->listPickups($userId, $role, $filters, $pagination['limit'], $pagination['offset']);
        $meta = Paginator::buildMeta($result['total'], $pagination['page'], $pagination['per_page']);

        return Response::json([
            'data' => $result['items'],
            'meta' => $meta,
        ]);
    }

    /**
     * GET /api/pickups/{id}
     */
    public function show(Request $request, array $params): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $role = (string) $request->getAttribute('user_role');
        $pickupId = (int) $params['id'];

        $result = $this->pickupService->getPickup($userId, $role, $pickupId);

        return Response::json(['data' => $result]);
    }

    /**
     * POST /api/pickups/{id}/confirm
     */
    public function confirm(Request $request, array $params): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $role = (string) $request->getAttribute('user_role');
        $pickupId = (int) $params['id'];

        $result = $this->pickupService->confirmPickup($userId, $role, $pickupId);

        return Response::json([
            'data'    => $result,
            'message' => 'Pickup confirmed and scheduled successfully',
        ]);
    }

    /**
     * PATCH /api/pickups/{id}
     */
    public function reschedule(Request $request, array $params): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $role = (string) $request->getAttribute('user_role');
        $pickupId = (int) $params['id'];
        $body = $request->getJsonBody() ?? [];

        $result = $this->pickupService->reschedulePickup($userId, $role, $pickupId, $body);

        return Response::json([
            'data'    => $result,
            'message' => 'Pickup rescheduled',
        ]);
    }

    /**
     * POST /api/pickups/{id}/cancel
     */
    public function cancel(Request $request, array $params): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $role = (string) $request->getAttribute('user_role');
        $pickupId = (int) $params['id'];
        $body = $request->getJsonBody() ?? [];
        $reason = isset($body['reason']) ? trim((string) $body['reason']) : null;

        $result = $this->pickupService->cancelPickup($userId, $role, $pickupId, $reason);

        return Response::json([
            'data'    => $result,
            'message' => 'Pickup cancelled',
        ]);
    }

    /**
     * POST /api/pickups/{id}/otp (NGO only)
     */
    public function issueOtp(Request $request, array $params): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $pickupId = (int) $params['id'];

        $result = $this->pickupService->issueOtp($userId, $pickupId);

        return Response::json([
            'data'    => $result,
            'message' => 'Pickup verification code emailed successfully',
        ]);
    }

    /**
     * POST /api/pickups/{id}/verify-otp (Donor only)
     */
    public function verifyOtp(Request $request, array $params): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $pickupId = (int) $params['id'];
        $body = $request->getJsonBody() ?? [];
        $otp = isset($body['otp']) ? trim((string) $body['otp']) : '';

        $result = $this->pickupService->verifyOtp($userId, $pickupId, $otp);

        return Response::json([
            'data'    => $result,
            'message' => 'Pickup verified successfully',
        ]);
    }

    /**
     * POST /api/pickups/{id}/confirm-receipt (NGO only)
     */
    public function confirmReceipt(Request $request, array $params): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $role = (string) $request->getAttribute('user_role');
        $pickupId = (int) $params['id'];

        $result = $this->pickupService->confirmReceipt($userId, $role, $pickupId);

        return Response::json([
            'data'    => $result,
            'message' => 'Handover confirmed and marked as completed',
        ]);
    }

    /**
     * GET /api/donations/{id}/history
     */
    public function donationHistory(Request $request, array $params): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $role = (string) $request->getAttribute('user_role');
        $donationId = (int) $params['id'];

        $result = $this->pickupService->getDonationHistory($userId, $role, $donationId);

        return Response::json(['data' => $result]);
    }
}
