<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class NgoRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function createNgo(array $data): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ngos (user_id, organization_name, registration_number, address_text, location, service_radius_km, verification_status)
            VALUES (
                :user_id,
                :org_name,
                :reg_num,
                :address,
                ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography,
                :radius,
                :status
            )
            RETURNING id, user_id, organization_name, registration_number, address_text,
                      ST_Y(location::geometry) as latitude, ST_X(location::geometry) as longitude,
                      service_radius_km, verification_status, created_at
        ");

        $stmt->execute([
            ':user_id'  => $data['user_id'],
            ':org_name' => $data['organization_name'],
            ':reg_num'  => $data['registration_number'],
            ':address'  => $data['address_text'],
            ':lng'      => (float) $data['longitude'],
            ':lat'      => (float) $data['latitude'],
            ':radius'   => (float) ($data['service_radius_km'] ?? 25),
            ':status'   => $data['verification_status'] ?? 'pending',
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addDocument(
        int $ngoId,
        string $storageName,
        string $mime,
        int $sizeBytes,
        string $sha256,
        string $docType = 'registration_proof'
    ): array {
        $stmt = $this->pdo->prepare("
            INSERT INTO ngo_documents (ngo_id, storage_name, mime, size_bytes, sha256, doc_type)
            VALUES (:ngo_id, :storage_name, :mime, :size_bytes, :sha256, :doc_type)
            RETURNING id, ngo_id, storage_name, mime, size_bytes, sha256, doc_type, created_at
        ");

        $stmt->execute([
            ':ngo_id'       => $ngoId,
            ':storage_name' => $storageName,
            ':mime'         => $mime,
            ':size_bytes'   => $sizeBytes,
            ':sha256'       => $sha256,
            ':doc_type'     => $docType,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function listDocuments(int $ngoId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, ngo_id, storage_name, mime, size_bytes, sha256, doc_type, created_at
            FROM ngo_documents
            WHERE ngo_id = :ngo_id
            ORDER BY created_at ASC
        ");
        $stmt->execute([':ngo_id' => $ngoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findDocumentById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, ngo_id, storage_name, mime, size_bytes, sha256, doc_type, created_at
            FROM ngo_documents
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function deleteDocument(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM ngo_documents WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function countDocuments(int $ngoId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM ngo_documents WHERE ngo_id = :ngo_id");
        $stmt->execute([':ngo_id' => $ngoId]);
        return (int) $stmt->fetchColumn();
    }

    public function findNgoById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT n.id, n.user_id, n.organization_name, n.registration_number, n.address_text,
                   ST_Y(n.location::geometry) as latitude, ST_X(n.location::geometry) as longitude,
                   n.service_radius_km, n.verification_status, n.reviewed_by, n.reviewed_at, n.review_note,
                   n.created_at, n.updated_at, u.name as contact_name, u.email as contact_email, u.phone as contact_phone
            FROM ngos n
            JOIN users u ON n.user_id = u.id
            WHERE n.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findNgoByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT n.id, n.user_id, n.organization_name, n.registration_number, n.address_text,
                   ST_Y(n.location::geometry) as latitude, ST_X(n.location::geometry) as longitude,
                   n.service_radius_km, n.verification_status, n.reviewed_by, n.reviewed_at, n.review_note,
                   n.created_at, n.updated_at, u.name as contact_name, u.email as contact_email, u.phone as contact_phone
            FROM ngos n
            JOIN users u ON n.user_id = u.id
            WHERE n.user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function updateNgo(int $ngoId, array $fields): void
    {
        $sets = [];
        $params = [':id' => $ngoId];

        if (isset($fields['organization_name'])) {
            $sets[] = "organization_name = :org_name";
            $params[':org_name'] = $fields['organization_name'];
        }
        if (isset($fields['registration_number'])) {
            $sets[] = "registration_number = :reg_num";
            $params[':reg_num'] = $fields['registration_number'];
        }
        if (isset($fields['address_text'])) {
            $sets[] = "address_text = :address";
            $params[':address'] = $fields['address_text'];
        }
        if (isset($fields['latitude']) && isset($fields['longitude'])) {
            $sets[] = "location = ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography";
            $params[':lng'] = (float) $fields['longitude'];
            $params[':lat'] = (float) $fields['latitude'];
        }
        if (isset($fields['service_radius_km'])) {
            $sets[] = "service_radius_km = :radius";
            $params[':radius'] = (float) $fields['service_radius_km'];
        }
        if (isset($fields['verification_status'])) {
            $sets[] = "verification_status = :status";
            $params[':status'] = $fields['verification_status'];
        }

        if (empty($sets)) {
            return;
        }

        $sql = "UPDATE ngos SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function listForAdmin(?string $status, ?string $q, int $limit, int $offset): array
    {
        $sql = "
            SELECT n.id, n.user_id, n.organization_name, n.registration_number, n.address_text,
                   ST_Y(n.location::geometry) as latitude, ST_X(n.location::geometry) as longitude,
                   n.service_radius_km, n.verification_status, n.reviewed_by, n.reviewed_at, n.review_note,
                   n.created_at, u.name as contact_name, u.email as contact_email, u.phone as contact_phone
            FROM ngos n
            JOIN users u ON n.user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($status)) {
            $sql .= " AND n.verification_status = :status";
            $params[':status'] = $status;
        }

        if (!empty($q)) {
            $sql .= " AND (n.organization_name ILIKE :q OR n.registration_number ILIKE :q OR u.email ILIKE :q)";
            $params[':q'] = "%{$q}%";
        }

        $sql .= " ORDER BY n.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countForAdmin(?string $status, ?string $q): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM ngos n
            JOIN users u ON n.user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($status)) {
            $sql .= " AND n.verification_status = :status";
            $params[':status'] = $status;
        }

        if (!empty($q)) {
            $sql .= " AND (n.organization_name ILIKE :q OR n.registration_number ILIKE :q OR u.email ILIKE :q)";
            $params[':q'] = "%{$q}%";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function updateVerification(
        int $ngoId,
        string $status,
        ?int $reviewedBy,
        ?string $reviewNote
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE ngos
            SET verification_status = :status,
                reviewed_by = :reviewed_by,
                reviewed_at = NOW(),
                review_note = :note
            WHERE id = :id
        ");
        $stmt->execute([
            ':status'      => $status,
            ':reviewed_by' => $reviewedBy,
            ':note'        => $reviewNote,
            ':id'          => $ngoId,
        ]);
    }
}
