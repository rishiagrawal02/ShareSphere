<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class RequestRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO donation_requests (
                donation_id,
                ngo_id,
                requirement_id,
                requested_quantity,
                status,
                notes,
                idempotency_key,
                expires_at
            ) VALUES (
                :donation_id,
                :ngo_id,
                :requirement_id,
                :requested_quantity,
                :status,
                :notes,
                :idempotency_key,
                NOW() + INTERVAL '72 hours'
            ) RETURNING id
        ");

        $stmt->execute([
            ':donation_id'         => $data['donation_id'],
            ':ngo_id'              => $data['ngo_id'],
            ':requirement_id'      => $data['requirement_id'] ?? null,
            ':requested_quantity'  => $data['requested_quantity'],
            ':status'              => $data['status'] ?? 'pending',
            ':notes'               => $data['notes'] ?? null,
            ':idempotency_key'     => $data['idempotency_key'] ?? null,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*,
                   d.donor_id,
                   d.title AS donation_title,
                   d.status AS donation_status,
                   d.available_quantity AS donation_available_quantity,
                   n.organization_name AS ngo_name,
                   n.user_id AS ngo_user_id,
                   req.title AS requirement_title,
                   req.quantity_needed AS requirement_quantity_needed,
                   req.quantity_allocated AS requirement_quantity_allocated,
                   a.id AS allocation_id,
                   a.status AS allocation_status,
                   a.allocated_quantity
            FROM donation_requests r
            JOIN donations d ON d.id = r.donation_id
            JOIN ngos n ON n.id = r.ngo_id
            LEFT JOIN ngo_requirements req ON req.id = r.requirement_id
            LEFT JOIN allocations a ON a.request_id = r.id
            WHERE r.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByNgoAndIdempotencyKey(int $ngoId, string $key): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*,
                   a.id AS allocation_id,
                   a.status AS allocation_status,
                   a.allocated_quantity
            FROM donation_requests r
            LEFT JOIN allocations a ON a.request_id = r.id
            WHERE r.ngo_id = :ngo_id AND r.idempotency_key = :key
        ");
        $stmt->execute([':ngo_id' => $ngoId, ':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findPendingByTuple(int $ngoId, int $donationId, ?int $requirementId): ?array
    {
        $sql = "
            SELECT r.*
            FROM donation_requests r
            WHERE r.ngo_id = :ngo_id
              AND r.donation_id = :donation_id
              AND r.status = 'pending'
        ";

        $params = [
            ':ngo_id'      => $ngoId,
            ':donation_id' => $donationId,
        ];

        if ($requirementId !== null) {
            $sql .= " AND r.requirement_id = :requirement_id";
            $params[':requirement_id'] = $requirementId;
        } else {
            $sql .= " AND r.requirement_id IS NULL";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateStatus(int $id, string $status, ?string $reviewedAt = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE donation_requests
            SET status = :status,
                reviewed_at = COALESCE(:reviewed_at, reviewed_at)
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id'          => $id,
            ':status'      => $status,
            ':reviewed_at' => $reviewedAt,
        ]);
    }

    public function listForNgo(int $ngoId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT r.*,
                   d.title AS donation_title,
                   d.status AS donation_status,
                   d.available_quantity AS donation_available_quantity,
                   n.organization_name AS ngo_name,
                   req.title AS requirement_title,
                   a.id AS allocation_id,
                   a.status AS allocation_status,
                   a.allocated_quantity
            FROM donation_requests r
            JOIN donations d ON d.id = r.donation_id
            JOIN ngos n ON n.id = r.ngo_id
            LEFT JOIN ngo_requirements req ON req.id = r.requirement_id
            LEFT JOIN allocations a ON a.request_id = r.id
            WHERE r.ngo_id = :ngo_id
        ";

        $params = [':ngo_id' => $ngoId];

        if (!empty($filters['status'])) {
            $sql .= " AND r.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['donation_id'])) {
            $sql .= " AND r.donation_id = :donation_id";
            $params[':donation_id'] = (int) $filters['donation_id'];
        }

        $sql .= " ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countForNgo(int $ngoId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM donation_requests r WHERE r.ngo_id = :ngo_id";
        $params = [':ngo_id' => $ngoId];

        if (!empty($filters['status'])) {
            $sql .= " AND r.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['donation_id'])) {
            $sql .= " AND r.donation_id = :donation_id";
            $params[':donation_id'] = (int) $filters['donation_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function listForDonor(int $donorUserId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT r.*,
                   d.title AS donation_title,
                   d.status AS donation_status,
                   d.available_quantity AS donation_available_quantity,
                   n.organization_name AS ngo_name,
                   req.title AS requirement_title,
                   a.id AS allocation_id,
                   a.status AS allocation_status,
                   a.allocated_quantity
            FROM donation_requests r
            JOIN donations d ON d.id = r.donation_id
            JOIN ngos n ON n.id = r.ngo_id
            LEFT JOIN ngo_requirements req ON req.id = r.requirement_id
            LEFT JOIN allocations a ON a.request_id = r.id
            WHERE d.donor_id = :donor_id
        ";

        $params = [':donor_id' => $donorUserId];

        if (!empty($filters['status'])) {
            $sql .= " AND r.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['donation_id'])) {
            $sql .= " AND r.donation_id = :donation_id";
            $params[':donation_id'] = (int) $filters['donation_id'];
        }

        $sql .= " ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countForDonor(int $donorUserId, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM donation_requests r
            JOIN donations d ON d.id = r.donation_id
            WHERE d.donor_id = :donor_id
        ";
        $params = [':donor_id' => $donorUserId];

        if (!empty($filters['status'])) {
            $sql .= " AND r.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['donation_id'])) {
            $sql .= " AND r.donation_id = :donation_id";
            $params[':donation_id'] = (int) $filters['donation_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function listAll(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT r.*,
                   d.title AS donation_title,
                   d.status AS donation_status,
                   d.available_quantity AS donation_available_quantity,
                   n.organization_name AS ngo_name,
                   req.title AS requirement_title,
                   a.id AS allocation_id,
                   a.status AS allocation_status,
                   a.allocated_quantity
            FROM donation_requests r
            JOIN donations d ON d.id = r.donation_id
            JOIN ngos n ON n.id = r.ngo_id
            LEFT JOIN ngo_requirements req ON req.id = r.requirement_id
            LEFT JOIN allocations a ON a.request_id = r.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND r.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['ngo_id'])) {
            $sql .= " AND r.ngo_id = :ngo_id";
            $params[':ngo_id'] = (int) $filters['ngo_id'];
        }

        if (!empty($filters['donation_id'])) {
            $sql .= " AND r.donation_id = :donation_id";
            $params[':donation_id'] = (int) $filters['donation_id'];
        }

        $sql .= " ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM donation_requests r WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND r.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['ngo_id'])) {
            $sql .= " AND r.ngo_id = :ngo_id";
            $params[':ngo_id'] = (int) $filters['ngo_id'];
        }

        if (!empty($filters['donation_id'])) {
            $sql .= " AND r.donation_id = :donation_id";
            $params[':donation_id'] = (int) $filters['donation_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findExpiredPending(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*
            FROM donation_requests r
            WHERE r.status = 'pending'
              AND r.expires_at <= NOW()
            ORDER BY r.id ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
