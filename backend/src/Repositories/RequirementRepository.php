<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class RequirementRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ngo_requirements (
                ngo_id, category_id, title, description,
                quantity_needed, quantity_allocated, quantity_fulfilled,
                urgency, min_condition, location, radius_km, needed_by, status
            ) VALUES (
                :ngo_id, :category_id, :title, :description,
                :quantity_needed, 0, 0,
                :urgency, :min_condition,
                ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography,
                :radius_km, :needed_by, :status
            )
            RETURNING id, ngo_id, category_id, title, description,
                      quantity_needed, quantity_allocated, quantity_fulfilled,
                      urgency, min_condition,
                      ST_Y(location::geometry) as latitude, ST_X(location::geometry) as longitude,
                      radius_km, needed_by, status, created_at, updated_at
        ");

        $stmt->execute([
            ':ngo_id'          => $data['ngo_id'],
            ':category_id'     => $data['category_id'],
            ':title'           => $data['title'],
            ':description'     => $data['description'] ?? '',
            ':quantity_needed' => $data['quantity_needed'],
            ':urgency'         => $data['urgency'] ?? 'medium',
            ':min_condition'   => $data['min_condition'] ?? null,
            ':lng'             => (float) $data['longitude'],
            ':lat'             => (float) $data['latitude'],
            ':radius_km'       => (float) ($data['radius_km'] ?? 25),
            ':needed_by'       => $data['needed_by'] ?? null,
            ':status'          => $data['status'] ?? 'active',
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.id, r.ngo_id, r.category_id, c.name as category_name, r.title, r.description,
                   r.quantity_needed, r.quantity_allocated, r.quantity_fulfilled,
                   r.urgency, r.min_condition,
                   ST_Y(r.location::geometry) as latitude, ST_X(r.location::geometry) as longitude,
                   r.radius_km, r.needed_by, r.status, r.created_at, r.updated_at,
                   n.organization_name, n.user_id as ngo_owner_id
            FROM ngo_requirements r
            JOIN categories c ON r.category_id = c.id
            JOIN ngos n ON r.ngo_id = n.id
            WHERE r.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function listForNgo(
        int $ngoId,
        ?string $status,
        ?int $categoryId,
        int $limit,
        int $offset
    ): array {
        $sql = "
            SELECT r.id, r.ngo_id, r.category_id, c.name as category_name, r.title, r.description,
                   r.quantity_needed, r.quantity_allocated, r.quantity_fulfilled,
                   r.urgency, r.min_condition,
                   ST_Y(r.location::geometry) as latitude, ST_X(r.location::geometry) as longitude,
                   r.radius_km, r.needed_by, r.status, r.created_at, r.updated_at
            FROM ngo_requirements r
            JOIN categories c ON r.category_id = c.id
            WHERE r.ngo_id = :ngo_id
        ";
        $params = [':ngo_id' => $ngoId];

        if (!empty($status)) {
            $sql .= " AND r.status = :status";
            $params[':status'] = $status;
        }

        if ($categoryId !== null && $categoryId > 0) {
            $sql .= " AND r.category_id = :cat_id";
            $params[':cat_id'] = $categoryId;
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

    public function countForNgo(int $ngoId, ?string $status, ?int $categoryId): int
    {
        $sql = "SELECT COUNT(*) FROM ngo_requirements WHERE ngo_id = :ngo_id";
        $params = [':ngo_id' => $ngoId];

        if (!empty($status)) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }

        if ($categoryId !== null && $categoryId > 0) {
            $sql .= " AND category_id = :cat_id";
            $params[':cat_id'] = $categoryId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $fields): void
    {
        $sets = [];
        $params = [':id' => $id];

        if (isset($fields['title'])) {
            $sets[] = "title = :title";
            $params[':title'] = $fields['title'];
        }
        if (isset($fields['description'])) {
            $sets[] = "description = :description";
            $params[':description'] = $fields['description'];
        }
        if (isset($fields['quantity_needed'])) {
            $sets[] = "quantity_needed = :quantity_needed";
            $params[':quantity_needed'] = (int) $fields['quantity_needed'];
        }
        if (isset($fields['urgency'])) {
            $sets[] = "urgency = :urgency";
            $params[':urgency'] = $fields['urgency'];
        }
        if (array_key_exists('min_condition', $fields)) {
            $sets[] = "min_condition = :min_condition";
            $params[':min_condition'] = $fields['min_condition'];
        }
        if (isset($fields['radius_km'])) {
            $sets[] = "radius_km = :radius_km";
            $params[':radius_km'] = (float) $fields['radius_km'];
        }
        if (array_key_exists('needed_by', $fields)) {
            $sets[] = "needed_by = :needed_by";
            $params[':needed_by'] = $fields['needed_by'];
        }
        if (isset($fields['status'])) {
            $sets[] = "status = :status";
            $params[':status'] = $fields['status'];
        }
        if (isset($fields['latitude']) && isset($fields['longitude'])) {
            $sets[] = "location = ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography";
            $params[':lat'] = (float) $fields['latitude'];
            $params[':lng'] = (float) $fields['longitude'];
        }

        if (empty($sets)) {
            return;
        }

        $sql = "UPDATE ngo_requirements SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function hasAllocations(int $requirementId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM allocations WHERE requirement_id = :id");
        $stmt->execute([':id' => $requirementId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM ngo_requirements WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
