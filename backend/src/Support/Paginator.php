<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Request;

class Paginator
{
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 50;

    public static function fromRequest(Request $request): array
    {
        $page = (int) $request->getQuery('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $perPage = (int) $request->getQuery('per_page', self::DEFAULT_PER_PAGE);
        if ($perPage < 1) {
            $perPage = self::DEFAULT_PER_PAGE;
        } elseif ($perPage > self::MAX_PER_PAGE) {
            $perPage = self::MAX_PER_PAGE;
        }

        $offset = ($page - 1) * $perPage;

        return [
            'page'     => $page,
            'per_page' => $perPage,
            'limit'    => $perPage,
            'offset'   => $offset,
        ];
    }

    public static function buildMeta(int $total, int $page, int $perPage): array
    {
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'current_page'  => $page,
            'per_page'      => $perPage,
            'total_records' => $total,
            'total_pages'   => $totalPages,
            'has_next'      => $page < $totalPages,
            'has_prev'      => $page > 1,
        ];
    }
}
