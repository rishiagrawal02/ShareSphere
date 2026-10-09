<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\ReportService;

class DashboardController
{
    private ReportService $service;

    public function __construct(?ReportService $service = null)
    {
        $this->service = $service ?? new ReportService();
    }

    /**
     * GET /api/dashboard
     * Returns role-scoped metrics for donor, ngo, or admin.
     */
    public function index(Request $request): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');
        $role = (string) $request->getAttribute('auth_role');

        $data = $this->service->getDashboard($userId, $role);

        return Response::success($data);
    }
}
