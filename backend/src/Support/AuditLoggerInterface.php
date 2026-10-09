<?php

declare(strict_types=1);

namespace App\Support;

interface AuditLoggerInterface
{
    public function log(
        ?int $actorUserId,
        string $action,
        string $targetType,
        ?int $targetId = null,
        string $result = 'success',
        array $metadata = [],
        ?string $ip = null,
        ?string $requestId = null
    ): void;
}
