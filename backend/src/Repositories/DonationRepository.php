<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use App\Support\LocationPrivacy;
use PDO;

class DonationRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function create(array $data): array
    {
        $lat = (float) $data['latitude'];
        $lng = (float) $data['longitude'];
        $snapped = LocationPrivacy::snap($lat, $lng);

        $stmt = $this->pdo->prepare("
            INSERT INTO donations (
                donor_id, category_id, title, description, condition,
                total_quantity, available_quantity, status, address_text,
                location, location_public, pickup_notes, expires_at
            ) VALUES (
                :donor_id, :category_id, :title, :description, :condition,
                :total_quantity, :available_quantity, :status, :address_text,
                ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography,
                ST_SetSRID(ST_MakePoint(:snapped_lng, :snapped_lat), 4326)::geography,
                :pickup_notes, :expires_at
            )
            RETURNING id, donor_id, category_id, title, description, condition,
                      total_quantity, available_quantity, status, address_text,
                      ST_Y(location::geometry) as latitude, ST_X(location::geometry) as longitude,
                      ST_Y(location_public::geometry) as latitude_public, ST_X(location_public::geometry) as longitude_public,
                      pickup_notes, expires_at, created_at, updated_at
        ");

        $stmt->execute([
            ':donor_id'           => $data['donor_id'],
            ':category_id'        => $data['category_id'],
            ':title'              => $data['title'],
            ':description'        => $data['description'],
            ':condition'          => $data['condition'],
            ':total_quantity'     => $data['total_quantity'],
            ':available_quantity' => $data['total_quantity'],
            ':status'             => $data['status'] ?? 'active',
            ':address_text'       => $data['address_text'],
            ':lng'                => $lng,
            ':lat'                => $lat,
            ':snapped_lng'        => $snapped['longitude_public'],
            ':snapped_lat'        => $snapped['latitude_public'],
            ':pickup_notes'       => $data['pickup_notes'] ?? null,
            ':expires_at'         => $data['expires_at'] ?? null,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.donor_id, d.category_id, c.name as category_name, d.title, d.description, d.condition,
                   d.total_quantity, d.available_quantity, d.status, d.address_text,
                   ST_Y(d.location::geometry) as latitude, ST_X(d.location::geometry) as longitude,
                   ST_Y(d.location_public::geometry) as latitude_public, ST_X(d.location_public::geometry) as longitude_public,
                   d.pickup_notes, d.expires_at, d.created_at, d.updated_at,
                   u.name as donor_name, u.phone as donor_phone
            FROM donations d
            JOIN categories c ON d.category_id = c.id
            JOIN users u ON d.donor_id = u.id
            WHERE d.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function listForOwner(
        int $donorId,
        ?string $status,
        ?int $categoryId,
        int $limit,
        int $offset
    ): array {
        $sql = "
            SELECT d.id, d.donor_id, d.category_id, c.name as category_name, d.title, d.description, d.condition,
                   d.total_quantity, d.available_quantity, d.status, d.address_text,
                   ST_Y(d.location::geometry) as latitude, ST_X(d.location::geometry) as longitude,
                   ST_Y(d.location_public::geometry) as latitude_public, ST_X(d.location_public::geometry) as longitude_public,
                   d.pickup_notes, d.expires_at, d.created_at, d.updated_at
            FROM donations d
            JOIN categories c ON d.category_id = c.id
            WHERE d.donor_id = :donor_id
        ";
        $params = [':donor_id' => $donorId];

        if (!empty($status)) {
            $sql .= " AND d.status = :status";
            $params[':status'] = $status;
        }

        if ($categoryId !== null && $categoryId > 0) {
            $sql .= " AND d.category_id = :cat_id";
            $params[':cat_id'] = $categoryId;
        }

        $sql .= " ORDER BY d.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countForOwner(int $donorId, ?string $status, ?int $categoryId): int
    {
        $sql = "SELECT COUNT(*) FROM donations WHERE donor_id = :donor_id";
        $params = [':donor_id' => $donorId];

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

    public function listForNgo(array $filters, int $limit, int $offset): array
    {
        $hasCoords = isset($filters['latitude']) && isset($filters['longitude']);
        $distSelect = "";
        $distWhere = "";

        if ($hasCoords) {
            $lat = (float) $filters['latitude'];
            $lng = (float) $filters['longitude'];
            $distSelect = ", ST_Distance(d.location_public, ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)::geography) / 1000.0 AS distance_km";
            if (!empty($filters['radius_km'])) {
                $radiusMeters = (float) $filters['radius_km'] * 1000;
                $distWhere = " AND ST_DWithin(d.location_public, ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)::geography, {$radiusMeters})";
            }
        }

        $sql = "
            SELECT d.id, d.category_id, c.name as category_name, d.title, d.description, d.condition,
                   d.total_quantity, d.available_quantity, d.status,
                   ST_Y(d.location_public::geometry) as latitude_public,
                   ST_X(d.location_public::geometry) as longitude_public,
                   d.expires_at, d.created_at, d.updated_at
                   {$distSelect}
            FROM donations d
            JOIN categories c ON d.category_id = c.id
            WHERE d.status IN ('active', 'partially_allocated')
            {$distWhere}
        ";

        $params = [];

        if (!empty($filters['category_id'])) {
            $sql .= " AND d.category_id = :cat_id";
            $params[':cat_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['condition'])) {
            $sql .= " AND d.condition = :condition";
            $params[':condition'] = $filters['condition'];
        }

        if (!empty($filters['q'])) {
            $sql .= " AND (d.title ILIKE :q OR d.description ILIKE :q)";
            $params[':q'] = "%{$filters['q']}%";
        }

        // Sorting
        $sort = $filters['sort'] ?? ($hasCoords ? 'distance' : 'newest');
        if ($sort === 'distance' && $hasCoords) {
            $sql .= " ORDER BY distance_km ASC";
        } elseif ($sort === 'quantity') {
            $sql .= " ORDER BY d.available_quantity DESC";
        } else {
            $sql .= " ORDER BY d.created_at DESC";
        }

        $sql .= " LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countForNgo(array $filters): int
    {
        $hasCoords = isset($filters['latitude']) && isset($filters['longitude']);
        $distWhere = "";

        if ($hasCoords && !empty($filters['radius_km'])) {
            $lat = (float) $filters['latitude'];
            $lng = (float) $filters['longitude'];
            $radiusMeters = (float) $filters['radius_km'] * 1000;
            $distWhere = " AND ST_DWithin(d.location_public, ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)::geography, {$radiusMeters})";
        }

        $sql = "
            SELECT COUNT(*)
            FROM donations d
            WHERE d.status IN ('active', 'partially_allocated')
            {$distWhere}
        ";
        $params = [];

        if (!empty($filters['category_id'])) {
            $sql .= " AND d.category_id = :cat_id";
            $params[':cat_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['condition'])) {
            $sql .= " AND d.condition = :condition";
            $params[':condition'] = $filters['condition'];
        }

        if (!empty($filters['q'])) {
            $sql .= " AND (d.title ILIKE :q OR d.description ILIKE :q)";
            $params[':q'] = "%{$filters['q']}%";
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
        if (isset($fields['condition'])) {
            $sets[] = "condition = :condition";
            $params[':condition'] = $fields['condition'];
        }
        if (isset($fields['total_quantity'])) {
            $sets[] = "total_quantity = :total_quantity";
            $params[':total_quantity'] = (int) $fields['total_quantity'];
        }
        if (isset($fields['available_quantity'])) {
            $sets[] = "available_quantity = :available_quantity";
            $params[':available_quantity'] = (int) $fields['available_quantity'];
        }
        if (isset($fields['status'])) {
            $sets[] = "status = :status";
            $params[':status'] = $fields['status'];
        }
        if (isset($fields['pickup_notes'])) {
            $sets[] = "pickup_notes = :pickup_notes";
            $params[':pickup_notes'] = $fields['pickup_notes'];
        }
        if (array_key_exists('expires_at', $fields)) {
            $sets[] = "expires_at = :expires_at";
            $params[':expires_at'] = $fields['expires_at'];
        }
        if (isset($fields['address_text'])) {
            $sets[] = "address_text = :address_text";
            $params[':address_text'] = $fields['address_text'];
        }
        if (isset($fields['latitude']) && isset($fields['longitude'])) {
            $lat = (float) $fields['latitude'];
            $lng = (float) $fields['longitude'];
            $snapped = LocationPrivacy::snap($lat, $lng);

            $sets[] = "location = ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography";
            $sets[] = "location_public = ST_SetSRID(ST_MakePoint(:snapped_lng, :snapped_lat), 4326)::geography";
            $params[':lat'] = $lat;
            $params[':lng'] = $lng;
            $params[':snapped_lat'] = $snapped['latitude_public'];
            $params[':snapped_lng'] = $snapped['longitude_public'];
        }

        if (empty($sets)) {
            return;
        }

        $sql = "UPDATE donations SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function hasOpenAllocations(int $donationId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM allocations
            WHERE donation_id = :id AND status IN ('reserved', 'confirmed', 'collected')
        ");
        $stmt->execute([':id' => $donationId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function getAllocatedQuantity(int $donationId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(allocated_quantity), 0) FROM allocations
            WHERE donation_id = :id AND status IN ('reserved', 'confirmed', 'collected', 'completed')
        ");
        $stmt->execute([':id' => $donationId]);
        return (int) $stmt->fetchColumn();
    }

    public function addImage(
        int $donationId,
        string $storageName,
        string $mime,
        int $sizeBytes,
        string $sha256,
        int $sortOrder = 0
    ): array {
        $stmt = $this->pdo->prepare("
            INSERT INTO donation_images (donation_id, storage_name, original_mime, size_bytes, sha256, sort_order)
            VALUES (:donation_id, :storage_name, :mime, :size_bytes, :sha256, :sort_order)
            RETURNING id, donation_id, storage_name, original_mime, size_bytes, sha256, sort_order, created_at
        ");
        $stmt->execute([
            ':donation_id'   => $donationId,
            ':storage_name'  => $storageName,
            ':mime'          => $mime,
            ':size_bytes'    => $sizeBytes,
            ':sha256'        => $sha256,
            ':sort_order'    => $sortOrder,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function listImages(int $donationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, donation_id, storage_name, original_mime, size_bytes, sha256, sort_order, created_at
            FROM donation_images
            WHERE donation_id = :donation_id
            ORDER BY sort_order ASC, created_at ASC
        ");
        $stmt->execute([':donation_id' => $donationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findImageById(int $imageId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, donation_id, storage_name, original_mime, size_bytes, sha256, sort_order, created_at
            FROM donation_images
            WHERE id = :id
        ");
        $stmt->execute([':id' => $imageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function deleteImage(int $imageId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM donation_images WHERE id = :id");
        $stmt->execute([':id' => $imageId]);
        return $stmt->rowCount() > 0;
    }

    public function countImages(int $donationId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM donation_images WHERE donation_id = :donation_id");
        $stmt->execute([':donation_id' => $donationId]);
        return (int) $stmt->fetchColumn();
    }
}
