<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\RequirementService;
use App\Support\Paginator;

class RequirementController
{
    private RequirementService $reqService;

    public function __construct(?RequirementService $reqService = null)
    {
        $this->reqService = $reqService ?? new RequirementService();
    }

    /**
     * POST /api/requirements
     */
    public function store(Request $request): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');
        $body   = $request->getBody();

        $requirement = $this->reqService->createRequirement($userId, $body);

        return Response::json([
            'status'      => 'ok',
            'message'     => 'Requirement published successfully',
            'requirement' => $requirement,
        ], 201);
    }

    /**
     * GET /api/requirements
     */
    public function index(Request $request): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');
        $status = $request->getQuery('status');
        $catId  = $request->getQuery('category_id') ? (int) $request->getQuery('category_id') : null;

        $pagination = Paginator::fromRequest($request);
        $total = $this->reqService->countNgoRequirements($userId, $status, $catId);
        $items = $this->reqService->listNgoRequirements(
            $userId,
            $status,
            $catId,
            $pagination['limit'],
            $pagination['offset']
        );

        $meta = Paginator::buildMeta($total, $pagination['page'], $pagination['per_page']);

        return Response::json([
            'status' => 'ok',
            'data'   => $items,
            'meta'   => $meta,
        ]);
    }

    /**
     * GET /api/requirements/{id}
     */
    public function show(Request $request, int $id): Response
    {
        $userId   = (int) $request->getAttribute('auth_user_id');
        $userRole = (string) $request->getAttribute('auth_role');

        $requirement = $this->reqService->getRequirement($userId, $userRole, $id);

        return Response::json([
            'status'      => 'ok',
            'requirement' => $requirement,
        ]);
    }

    /**
     * PATCH /api/requirements/{id}
     */
    public function update(Request $request, int $id): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');
        $body   = $request->getBody();

        $requirement = $this->reqService->updateRequirement($userId, $id, $body);

        return Response::json([
            'status'      => 'ok',
            'message'     => 'Requirement updated successfully',
            'requirement' => $requirement,
        ]);
    }

    /**
     * POST /api/requirements/{id}/close
     */
    public function close(Request $request, int $id): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');

        $requirement = $this->reqService->closeRequirement($userId, $id);

        return Response::json([
            'status'      => 'ok',
            'message'     => 'Requirement closed successfully',
            'requirement' => $requirement,
        ]);
    }

    /**
     * DELETE /api/requirements/{id}
     */
    public function destroy(Request $request, int $id): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');

        $this->reqService->deleteRequirement($userId, $id);

        return Response::json([
            'status'  => 'ok',
            'message' => 'Requirement deleted or closed successfully',
        ]);
    }
}
