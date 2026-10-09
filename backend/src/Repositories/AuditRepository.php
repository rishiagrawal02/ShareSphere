<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class AuditRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function log(
        ?int $actorUserId,
        string $action,
        string $targetType,
        ?int $targetId = null,
        string $result = 'success',
        ?array $metadata = null,
        ?string $ip = null,
        ?string $requestId = null
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_logs (actor_user_id, action, target_type, target_id, result, metadata, ip, request_id)
            VALUES (:actor, :action, :target_type, :target_id, :result, :metadata, :ip, :request_id)
            RETURNING id
        ");

        $stmt->execute([
            ':actor'       => $actorUserId,
            ':action'      => $action,
            ':target_type' => $targetType,
            ':target_id'   => $targetId,
            ':result'      => $result,
            ':metadata'    => $metadata !== null ? json_encode($metadata) : null,
            ':ip'          => $ip,
            ':request_id'  => $requestId,
        ]);

        return (int) $stmt->fetchColumn();
    }
}
