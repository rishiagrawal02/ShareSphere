<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

class MediaPolicyNgoDocument implements MediaPolicyInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function authorize(int $userId, ?string $userRole, int $resourceId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.storage_name, d.mime, d.doc_type, n.user_id AS ngo_owner_id
            FROM ngo_documents d
            JOIN ngos n ON d.ngo_id = n.id
            WHERE d.id = :id
        ");
        $stmt->execute([':id' => $resourceId]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($doc === false) {
            return null;
        }

        // Allow Admin or Owning NGO User
        if ($userRole === 'admin' || (int) $doc['ngo_owner_id'] === $userId) {
            return [
                'storage_name' => $doc['storage_name'],
                'mime'         => $doc['mime'],
                'kind'         => 'documents',
                'disposition'  => 'attachment',
            ];
        }

        return null;
    }
}
