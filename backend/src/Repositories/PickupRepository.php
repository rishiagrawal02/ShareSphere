<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class PickupRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO pickups (
                allocation_id,
                proposed_by,
                scheduled_at,
                location_details,
                contact_note,
                state
            ) VALUES (
                :allocation_id,
                :proposed_by,
                :scheduled_at,
                :location_details,
                :contact_note,
                :state
            ) RETURNING id
        ");

        $stmt->execute([
            ':allocation_id'    => $data['allocation_id'],
            ':proposed_by'      => $data['proposed_by'],
            ':scheduled_at'     => $data['scheduled_at'],
            ':location_details' => $data['location_details'] ?? null,
            ':contact_note'     => $data['contact_note'] ?? null,
            ':state'            => $data['state'] ?? 'proposed',
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*,
                   a.request_id,
                   a.donation_id,
                   a.requirement_id,
                   a.allocated_quantity,
                   a.status AS allocation_status,
                   r.ngo_id,
                   r.status AS request_status,
                   d.donor_id,
                   d.title AS donation_title,
                   d.total_quantity,
                   d.available_quantity,
                   d.status AS donation_status,
                   d.address_text,
                   ST_Y(d.location::geometry) AS latitude_exact,
                   ST_X(d.location::geometry) AS longitude_exact,
                   ST_Y(d.location_public::geometry) AS latitude_public,
                   ST_X(d.location_public::geometry) AS longitude_public,
                   n.organization_name,
                   n.user_id AS ngo_user_id,
                   req.quantity_needed AS requirement_quantity_needed,
                   req.quantity_allocated AS requirement_quantity_allocated,
                   req.quantity_fulfilled AS requirement_quantity_fulfilled
            FROM pickups p
            JOIN allocations a ON a.id = p.allocation_id
            JOIN donation_requests r ON r.id = a.request_id
            JOIN donations d ON d.id = a.donation_id
            JOIN ngos n ON n.id = r.ngo_id
            LEFT JOIN ngo_requirements req ON req.id = a.requirement_id
            WHERE p.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findActiveByAllocationId(int $allocationId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*
            FROM pickups p
            WHERE p.allocation_id = :allocation_id
              AND p.state != 'cancelled'
        ");
        $stmt->execute([':allocation_id' => $allocationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateState(int $id, string $state): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE pickups
            SET state = :state,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id'    => $id,
            ':state' => $state,
        ]);
    }

    public function updateSchedule(int $id, string $scheduledAt, ?string $locationDetails, ?string $contactNote, int $proposedBy): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE pickups
            SET scheduled_at = :scheduled_at,
                location_details = :location_details,
                contact_note = :contact_note,
                proposed_by = :proposed_by,
                state = 'proposed',
                otp_hmac = NULL,
                otp_expires_at = NULL,
                otp_attempts = 0,
                otp_locked = FALSE,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id'               => $id,
            ':scheduled_at'     => $scheduledAt,
            ':location_details' => $locationDetails,
            ':contact_note'     => $contactNote,
            ':proposed_by'      => $proposedBy,
        ]);
    }

    public function updateOtp(int $id, string $hmac, string $expiresAt, int $issueCount): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE pickups
            SET otp_hmac = :hmac,
                otp_expires_at = :expires_at,
                otp_attempts = 0,
                otp_locked = FALSE,
                otp_issue_count = :issue_count,
                last_otp_issued_at = NOW(),
                state = 'otp_issued',
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id'          => $id,
            ':hmac'        => $hmac,
            ':expires_at'  => $expiresAt,
            ':issue_count' => $issueCount,
        ]);
    }

    public function incrementOtpAttempts(int $id, bool $lock): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE pickups
            SET otp_attempts = otp_attempts + 1,
                otp_locked = :lock,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id'   => $id,
            ':lock' => $lock ? 1 : 0,
        ]);
    }

    public function markCollected(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE pickups
            SET state = 'collected',
                collected_at = NOW(),
                otp_hmac = NULL,
                otp_expires_at = NULL,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function markCompleted(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE pickups
            SET state = 'completed',
                completed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function listForUser(int $userId, string $role, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT p.*,
                   a.request_id,
                   a.donation_id,
                   a.requirement_id,
                   a.allocated_quantity,
                   a.status AS allocation_status,
                   r.ngo_id,
                   d.donor_id,
                   d.title AS donation_title,
                   d.total_quantity,
                   d.available_quantity,
                   d.status AS donation_status,
                   d.address_text,
                   ST_Y(d.location::geometry) AS latitude_exact,
                   ST_X(d.location::geometry) AS longitude_exact,
                   ST_Y(d.location_public::geometry) AS latitude_public,
                   ST_X(d.location_public::geometry) AS longitude_public,
                   n.organization_name,
                   n.user_id AS ngo_user_id
            FROM pickups p
            JOIN allocations a ON a.id = p.allocation_id
            JOIN donation_requests r ON r.id = a.request_id
            JOIN donations d ON d.id = a.donation_id
            JOIN ngos n ON n.id = r.ngo_id
            WHERE 1=1
        ";

        $params = [];

        if ($role === 'donor') {
            $sql .= " AND d.donor_id = :donor_id";
            $params[':donor_id'] = $userId;
        } elseif ($role === 'ngo') {
            $sql .= " AND n.user_id = :ngo_user_id";
            $params[':ngo_user_id'] = $userId;
        }

        if (!empty($filters['state'])) {
            $sql .= " AND p.state = :state";
            $params[':state'] = $filters['state'];
        }

        if (!empty($filters['from'])) {
            $sql .= " AND p.scheduled_at >= :from_date";
            $params[':from_date'] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $sql .= " AND p.scheduled_at <= :to_date";
            $params[':to_date'] = $filters['to'];
        }

        $sql .= " ORDER BY p.scheduled_at ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countForUser(int $userId, string $role, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM pickups p
            JOIN allocations a ON a.id = p.allocation_id
            JOIN donation_requests r ON r.id = a.request_id
            JOIN donations d ON d.id = a.donation_id
            JOIN ngos n ON n.id = r.ngo_id
            WHERE 1=1
        ";

        $params = [];

        if ($role === 'donor') {
            $sql .= " AND d.donor_id = :donor_id";
            $params[':donor_id'] = $userId;
        } elseif ($role === 'ngo') {
            $sql .= " AND n.user_id = :ngo_user_id";
            $params[':ngo_user_id'] = $userId;
        }

        if (!empty($filters['state'])) {
            $sql .= " AND p.state = :state";
            $params[':state'] = $filters['state'];
        }

        if (!empty($filters['from'])) {
            $sql .= " AND p.scheduled_at >= :from_date";
            $params[':from_date'] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $sql .= " AND p.scheduled_at <= :to_date";
            $params[':to_date'] = $filters['to'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
