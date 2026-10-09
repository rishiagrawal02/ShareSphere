<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\CategoryService;

class CategoryController
{
    private CategoryService $categoryService;

    public function __construct(?CategoryService $categoryService = null)
    {
        $this->categoryService = $categoryService ?? new CategoryService();
    }

    /**
     * GET /api/categories (Public active category list)
     */
    public function index(Request $request): Response
    {
        $categories = $this->categoryService->listPublicCategories();

        return Response::json([
            'status' => 'ok',
            'data'   => $categories,
        ]);
    }
}
