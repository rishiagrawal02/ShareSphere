<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class AllocationRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO allocations (
                request_id,
                donation_id,
                requirement_id,
                allocated_quantity,
                status
            ) VALUES (
                :request_id,
                :donation_id,
                :requirement_id,
                :allocated_quantity,
                :status
            ) RETURNING id
        ");

        $stmt->execute([
            ':request_id'         => $data['request_id'],
            ':donation_id'        => $data['donation_id'],
            ':requirement_id'     => $data['requirement_id'] ?? null,
            ':allocated_quantity' => $data['allocated_quantity'],
            ':status'             => $data['status'] ?? 'reserved',
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*,
                   r.ngo_id,
                   r.status AS request_status,
                   d.donor_id
            FROM allocations a
            JOIN donation_requests r ON r.id = a.request_id
            JOIN donations d ON d.id = a.donation_id
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByRequestId(int $requestId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*,
                   r.ngo_id,
                   r.status AS request_status,
                   d.donor_id
            FROM allocations a
            JOIN donation_requests r ON r.id = a.request_id
            JOIN donations d ON d.id = a.donation_id
            WHERE a.request_id = :request_id
        ");
        $stmt->execute([':request_id' => $requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE allocations
            SET status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id'     => $id,
            ':status' => $status,
        ]);
    }

    public function updateStatusByRequestId(int $requestId, string $status): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE allocations
            SET status = :status,
                updated_at = NOW()
            WHERE request_id = :request_id
        ");

        return $stmt->execute([
            ':request_id' => $requestId,
            ':status'     => $status,
        ]);
    }
}
