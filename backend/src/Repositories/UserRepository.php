<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, email, password_hash, role, account_status, phone, created_at, updated_at, last_login_at
            FROM users
            WHERE email = :email
        ");
        $stmt->execute([':email' => trim($email)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, email, role, account_status, phone, created_at, updated_at, last_login_at
            FROM users
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findNgoByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, user_id, organization_name, registration_number, address_text,
                   ST_Y(location::geometry) as latitude, ST_X(location::geometry) as longitude,
                   service_radius_km, verification_status, reviewed_by, reviewed_at, review_note,
                   created_at, updated_at
            FROM ngos
            WHERE user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (name, email, password_hash, role, account_status, phone)
            VALUES (:name, :email, :password_hash, :role, :account_status, :phone)
            RETURNING id, name, email, role, account_status, phone, created_at, updated_at
        ");

        $stmt->execute([
            ':name' => $data['name'],
            ':email' => trim($data['email']),
            ':password_hash' => $data['password_hash'],
            ':role' => $data['role'],
            ':account_status' => $data['account_status'] ?? 'active',
            ':phone' => $data['phone'] ?? null,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE users SET last_login_at = NOW() WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE users SET password_hash = :hash WHERE id = :id
        ");
        $stmt->execute([':hash' => $hash, ':id' => $id]);
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE users SET account_status = :status, updated_at = NOW() WHERE id = :id
        ");
        $stmt->execute([':status' => $status, ':id' => $id]);
    }

    public function listUsers(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT u.id, u.name, u.email, u.role, u.account_status, u.phone,
                   u.created_at, u.updated_at, u.last_login_at,
                   n.id AS ngo_id, n.organization_name, n.verification_status AS ngo_verification_status
            FROM users u
            LEFT JOIN ngos n ON n.user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['role'])) {
            $sql .= " AND u.role = :role";
            $params[':role'] = $filters['role'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND u.account_status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['q'])) {
            $sql .= " AND (u.name ILIKE :q OR u.email ILIKE :q OR n.organization_name ILIKE :q)";
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $sql .= " ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countUsers(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM users u
            LEFT JOIN ngos n ON n.user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['role'])) {
            $sql .= " AND u.role = :role";
            $params[':role'] = $filters['role'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND u.account_status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['q'])) {
            $sql .= " AND (u.name ILIKE :q OR u.email ILIKE :q OR n.organization_name ILIKE :q)";
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function countActiveAdmins(): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE role = 'admin' AND account_status = 'active'
        ");
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}
