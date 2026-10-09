<?php

declare(strict_types=1);

namespace App\Support;

interface MediaPolicyInterface
{
    /**
     * Check if user is authorized to access the requested media resource.
     * Returns resource metadata array containing 'storage_name' and 'mime', or null if forbidden/missing.
     *
     * @return array{storage_name: string, mime: string, original_name?: string}|null
     */
    public function authorize(int $userId, ?string $userRole, int $resourceId): ?array;
}
