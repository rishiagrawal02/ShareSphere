<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\CategoryService;

class AdminCategoryController
{
    private CategoryService $categoryService;

    public function __construct(?CategoryService $categoryService = null)
    {
        $this->categoryService = $categoryService ?? new CategoryService();
    }

    /**
     * GET /api/admin/categories
     */
    public function index(Request $request): Response
    {
        $categories = $this->categoryService->listAdminCategories();

        return Response::json([
            'status' => 'ok',
            'data'   => $categories,
        ]);
    }

    /**
     * POST /api/admin/categories
     */
    public function store(Request $request): Response
    {
        $adminUserId = (int) $request->getAttribute('auth_user_id');
        $body = $request->getBody();

        $category = $this->categoryService->createCategory($adminUserId, $body);

        return Response::json([
            'status'   => 'ok',
            'message'  => 'Category created successfully',
            'category' => $category,
        ], 201);
    }

    /**
     * PATCH /api/admin/categories/{id}
     */
    public function update(Request $request, int $id): Response
    {
        $adminUserId = (int) $request->getAttribute('auth_user_id');
        $body = $request->getBody();

        $category = $this->categoryService->updateCategory($adminUserId, $id, $body);

        return Response::json([
            'status'   => 'ok',
            'message'  => 'Category updated successfully',
            'category' => $category,
        ]);
    }

    /**
     * PUT /api/admin/categories/{id}/compatibility
     */
    public function setCompatibility(Request $request, int $id): Response
    {
        $adminUserId = (int) $request->getAttribute('auth_user_id');
        $body = $request->getBody();

        $compatibilities = is_array($body) && isset($body['compatibility']) ? $body['compatibility'] : (is_array($body) ? $body : []);
        $updatedList = $this->categoryService->setCompatibility($adminUserId, $id, $compatibilities);

        return Response::json([
            'status'        => 'ok',
            'message'       => 'Category compatibility updated successfully',
            'compatibility' => $updatedList,
        ]);
    }
}
