<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class CategoryRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function listActive(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, name, description
            FROM categories
            WHERE is_active = TRUE
            ORDER BY name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, name, description, is_active
            FROM categories
            ORDER BY id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, description, is_active
            FROM categories
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, description, is_active
            FROM categories
            WHERE LOWER(name) = LOWER(:name)
        ");
        $stmt->execute([':name' => trim($name)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function create(string $name, ?string $description = null, bool $isActive = true): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO categories (name, description, is_active)
            VALUES (:name, :desc, :is_active)
            RETURNING id, name, description, is_active
        ");
        $stmt->execute([
            ':name'      => trim($name),
            ':desc'      => $description,
            ':is_active' => $isActive ? 1 : 0,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update(int $id, array $fields): void
    {
        $sets = [];
        $params = [':id' => $id];

        if (isset($fields['name'])) {
            $sets[] = "name = :name";
            $params[':name'] = trim($fields['name']);
        }
        if (array_key_exists('description', $fields)) {
            $sets[] = "description = :desc";
            $params[':desc'] = $fields['description'];
        }
        if (isset($fields['is_active'])) {
            $sets[] = "is_active = :is_active";
            $params[':is_active'] = $fields['is_active'] ? 1 : 0;
        }

        if (empty($sets)) {
            return;
        }

        $sql = "UPDATE categories SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function countActiveUsage(int $categoryId): array
    {
        $stmtDonations = $this->pdo->prepare("
            SELECT COUNT(*) FROM donations
            WHERE category_id = :cat_id AND status IN ('active', 'partially_allocated', 'fully_allocated')
        ");
        $stmtDonations->execute([':cat_id' => $categoryId]);
        $donationsCount = (int) $stmtDonations->fetchColumn();

        $stmtReqs = $this->pdo->prepare("
            SELECT COUNT(*) FROM ngo_requirements
            WHERE category_id = :cat_id AND status IN ('active', 'partially_fulfilled')
        ");
        $stmtReqs->execute([':cat_id' => $categoryId]);
        $reqsCount = (int) $stmtReqs->fetchColumn();

        return [
            'active_donations'    => $donationsCount,
            'active_requirements' => $reqsCount,
            'total_active'        => $donationsCount + $reqsCount,
        ];
    }

    public function getCompatibility(int $categoryId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT cc.compatible_category_id, c.name as compatible_category_name, cc.score_factor
            FROM category_compatibility cc
            JOIN categories c ON cc.compatible_category_id = c.id
            WHERE cc.category_id = :cat_id
            ORDER BY cc.score_factor DESC, c.name ASC
        ");
        $stmt->execute([':cat_id' => $categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setCompatibility(int $categoryId, array $compatibilities): void
    {
        $this->pdo->beginTransaction();

        try {
            // Delete existing forward and reverse mapping for this category
            $del = $this->pdo->prepare("DELETE FROM category_compatibility WHERE category_id = :id OR compatible_category_id = :id");
            $del->execute([':id' => $categoryId]);

            $insert = $this->pdo->prepare("
                INSERT INTO category_compatibility (category_id, compatible_category_id, score_factor)
                VALUES (:cat_id, :comp_id, :score)
                ON CONFLICT (category_id, compatible_category_id) DO UPDATE SET score_factor = EXCLUDED.score_factor
            ");

            foreach ($compatibilities as $item) {
                $compId = (int) $item['compatible_category_id'];
                $score  = (float) $item['score_factor'];

                if ($compId === $categoryId) {
                    continue; // No self-compatibility
                }

                // Symmetrical insertion
                $insert->execute([':cat_id' => $categoryId, ':comp_id' => $compId, ':score' => $score]);
                $insert->execute([':cat_id' => $compId, ':comp_id' => $categoryId, ':score' => $score]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
