<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\ValidationFailedException;
use App\Repositories\AuditRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\DonationRepository;
use App\Repositories\UserRepository;
use App\Support\Database;
use App\Support\Validator;
use PDO;
use Throwable;

class DonationService
{
    private const ALLOWED_CONDITIONS = ['new', 'like_new', 'good', 'fair'];
    private const ALLOWED_INITIAL_STATUSES = ['draft', 'active'];

    private PDO $pdo;
    private DonationRepository $donationRepo;
    private CategoryRepository $categoryRepo;
    private UserRepository $userRepo;
    private FileStorage $fileStorage;
    private AuditRepository $auditRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?DonationRepository $donationRepo = null,
        ?CategoryRepository $categoryRepo = null,
        ?UserRepository $userRepo = null,
        ?FileStorage $fileStorage = null,
        ?AuditRepository $auditRepo = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->donationRepo = $donationRepo ?? new DonationRepository($this->pdo);
        $this->categoryRepo = $categoryRepo ?? new CategoryRepository($this->pdo);
        $this->userRepo = $userRepo ?? new UserRepository($this->pdo);
        $this->fileStorage = $fileStorage ?? new FileStorage();
        $this->auditRepo = $auditRepo ?? new AuditRepository($this->pdo);
    }

    public function createDonation(int $donorId, string $role, array $input): array
    {
        if ($role !== 'donor') {
            throw new ForbiddenException('Only donor accounts can publish donations', 'FORBIDDEN_ROLE');
        }

        $validated = Validator::validate($input, [
            'title'          => 'required|string|min:3|max:120',
            'category_id'    => 'required|integer',
            'description'    => 'string|max:2000',
            'condition'      => 'required|string',
            'total_quantity' => 'required|integer|min:1|max:10000',
            'latitude'       => 'required|numeric',
            'longitude'      => 'required|numeric',
            'address_text'   => 'required|string|min:5',
            'pickup_notes'   => 'string|max:1000',
        ]);

        if (!in_array($validated['condition'], self::ALLOWED_CONDITIONS, true)) {
            throw new ValidationFailedException(['condition' => 'Condition must be one of: ' . implode(', ', self::ALLOWED_CONDITIONS)]);
        }

        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new ValidationFailedException(['location' => 'Latitude must be between -90 and 90, Longitude between -180 and 180']);
        }

        // Validate active category
        $catId = (int) $validated['category_id'];
        $category = $this->categoryRepo->findById($catId);
        if ($category === null || !(bool) $category['is_active']) {
            throw new ValidationFailedException(['category_id' => 'Selected category does not exist or is inactive']);
        }

        // Validate expires_at
        $expiresAt = null;
        if (!empty($input['expires_at'])) {
            $ts = strtotime((string) $input['expires_at']);
            if ($ts === false || $ts <= time()) {
                throw new ValidationFailedException(['expires_at' => 'Expiration date must be a valid future timestamp']);
            }
            $expiresAt = date('Y-m-d H:i:sP', $ts);
        }

        $status = $input['status'] ?? 'active';
        if (!in_array($status, self::ALLOWED_INITIAL_STATUSES, true)) {
            throw new ValidationFailedException(['status' => 'Initial status must be either active or draft']);
        }

        $donation = $this->donationRepo->create([
            'donor_id'       => $donorId,
            'category_id'    => $catId,
            'title'          => $validated['title'],
            'description'    => $validated['description'] ?? '',
            'condition'      => $validated['condition'],
            'total_quantity' => $validated['total_quantity'],
            'status'         => $status,
            'address_text'   => $validated['address_text'],
            'latitude'       => $lat,
            'longitude'      => $lng,
            'pickup_notes'   => $validated['pickup_notes'] ?? null,
            'expires_at'     => $expiresAt,
        ]);

        $this->auditRepo->log(
            $donorId,
            'donation.created',
            'donations',
            (int) $donation['id'],
            'success',
            ['title' => $donation['title'], 'total_quantity' => $donation['total_quantity']]
        );

        $donation['images'] = [];
        return $this->formatForOwner($donation);
    }

    public function getDonation(int $id, ?int $userId, ?string $role): array
    {
        $donation = $this->donationRepo->findById($id);
        if ($donation === null) {
            throw new NotFoundException('Donation not found');
        }

        $images = $this->donationRepo->listImages($id);
        $formattedImages = [];
        foreach ($images as $img) {
            $formattedImages[] = [
                'id'         => (int) $img['id'],
                'url'        => "/api/media/donation-images/{$img['id']}",
                'sort_order' => (int) $img['sort_order'],
            ];
        }
        $donation['images'] = $formattedImages;

        // 1. Owner or Admin -> Full details with exact coordinates and address
        if ($userId !== null && ($role === 'admin' || (int) $donation['donor_id'] === $userId)) {
            return $this->formatForOwner($donation);
        }

        // 2. Verified NGO -> Public view without exact coordinates or address
        if ($userId !== null && $role === 'ngo') {
            $ngo = $this->userRepo->findNgoByUserId($userId);
            if ($ngo !== null && $ngo['verification_status'] === 'verified') {
                if (in_array($donation['status'], ['active', 'partially_allocated', 'fully_allocated'], true)) {
                    return $this->formatForPublic($donation);
                }
            }
        }

        // Otherwise hide to prevent existence leakage
        throw new NotFoundException('Donation not found');
    }

    public function listOwnerDonations(int $donorId, ?string $status, ?int $categoryId, int $limit, int $offset): array
    {
        $donations = $this->donationRepo->listForOwner($donorId, $status, $categoryId, $limit, $offset);
        foreach ($donations as &$d) {
            $images = $this->donationRepo->listImages((int) $d['id']);
            $d['images'] = array_map(fn($img) => [
                'id'         => (int) $img['id'],
                'url'        => "/api/media/donation-images/{$img['id']}",
                'sort_order' => (int) $img['sort_order'],
            ], $images);
            $d = $this->formatForOwner($d);
        }
        return $donations;
    }

    public function listNgoDonations(array $filters, int $limit, int $offset): array
    {
        $donations = $this->donationRepo->listForNgo($filters, $limit, $offset);
        foreach ($donations as &$d) {
            $images = $this->donationRepo->listImages((int) $d['id']);
            $d['images'] = array_map(fn($img) => [
                'id'         => (int) $img['id'],
                'url'        => "/api/media/donation-images/{$img['id']}",
                'sort_order' => (int) $img['sort_order'],
            ], $images);
            $d = $this->formatForPublic($d);
        }
        return $donations;
    }

    public function updateDonation(int $donorId, int $id, array $input): array
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM donations WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $id]);
            $donation = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($donation === false || (int) $donation['donor_id'] !== $donorId) {
                $this->pdo->rollBack();
                throw new NotFoundException('Donation not found');
            }

            $fieldsToUpdate = [];

            if (isset($input['title'])) {
                $title = trim((string) $input['title']);
                if (strlen($title) < 3 || strlen($title) > 120) {
                    throw new ValidationFailedException(['title' => 'Title must be between 3 and 120 characters']);
                }
                $fieldsToUpdate['title'] = $title;
            }

            if (isset($input['description'])) {
                $fieldsToUpdate['description'] = trim((string) $input['description']);
            }

            if (isset($input['condition'])) {
                if (!in_array($input['condition'], self::ALLOWED_CONDITIONS, true)) {
                    throw new ValidationFailedException(['condition' => 'Invalid condition']);
                }
                $fieldsToUpdate['condition'] = $input['condition'];
            }

            if (isset($input['pickup_notes'])) {
                $fieldsToUpdate['pickup_notes'] = trim((string) $input['pickup_notes']);
            }

            if (isset($input['address_text'])) {
                $fieldsToUpdate['address_text'] = trim((string) $input['address_text']);
            }

            if (isset($input['latitude']) && isset($input['longitude'])) {
                $fieldsToUpdate['latitude'] = (float) $input['latitude'];
                $fieldsToUpdate['longitude'] = (float) $input['longitude'];
            }

            // Quantity adjustments
            if (isset($input['total_quantity'])) {
                $newTotal = (int) $input['total_quantity'];
                if ($newTotal < 1 || $newTotal > 10000) {
                    throw new ValidationFailedException(['total_quantity' => 'Total quantity must be between 1 and 10,000']);
                }

                $oldTotal = (int) $donation['total_quantity'];
                $oldAvailable = (int) $donation['available_quantity'];
                $allocatedAmount = $oldTotal - $oldAvailable;

                if ($newTotal < $allocatedAmount) {
                    throw new ConflictException(
                        "Cannot reduce total quantity below currently allocated amount ({$allocatedAmount})",
                        'QUANTITY_BELOW_ALLOCATED'
                    );
                }

                $delta = $newTotal - $oldTotal;
                $newAvailable = $oldAvailable + $delta;

                $fieldsToUpdate['total_quantity'] = $newTotal;
                $fieldsToUpdate['available_quantity'] = $newAvailable;
            }

            if (!empty($fieldsToUpdate)) {
                $this->donationRepo->update($id, $fieldsToUpdate);
                $this->auditRepo->log($donorId, 'donation.updated', 'donations', $id, 'success', $fieldsToUpdate);
            }

            $this->pdo->commit();
            return $this->getDonation($id, $donorId, 'donor');
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function closeDonation(int $donorId, int $id): array
    {
        $donation = $this->donationRepo->findById($id);
        if ($donation === null || (int) $donation['donor_id'] !== $donorId) {
            throw new NotFoundException('Donation not found');
        }

        if ($this->donationRepo->hasOpenAllocations($id)) {
            throw new ConflictException('Cannot close donation with active open allocations', 'OPEN_ALLOCATIONS_EXIST');
        }

        $this->donationRepo->update($id, ['status' => 'closed', 'available_quantity' => 0]);
        $this->auditRepo->log($donorId, 'donation.closed', 'donations', $id, 'success');

        return $this->getDonation($id, $donorId, 'donor');
    }

    public function uploadImages(int $donorId, int $donationId, array $files): array
    {
        $donation = $this->donationRepo->findById($donationId);
        if ($donation === null || (int) $donation['donor_id'] !== $donorId) {
            throw new NotFoundException('Donation not found');
        }

        $normFiles = $this->normalizeFilesArray($files['images'] ?? $files);
        if (empty($normFiles)) {
            throw new ValidationFailedException(['images' => 'No image files provided']);
        }

        $currentCount = $this->donationRepo->countImages($donationId);
        if ($currentCount + count($normFiles) > 5) {
            throw new ValidationFailedException(['images' => 'Maximum limit of 5 images per donation exceeded']);
        }

        // Validate all images first
        $validated = [];
        foreach ($normFiles as $f) {
            $validated[] = FileValidator::validateImage($f);
        }

        // Store and record in DB
        $storedNames = [];
        $addedImages = [];

        $this->pdo->beginTransaction();

        try {
            foreach ($validated as $idx => $v) {
                $stored = $this->fileStorage->store($v['tmp_path'], 'images', $v['extension'], $v['mime']);
                $storedNames[] = $stored['storage_name'];

                $added = $this->donationRepo->addImage(
                    $donationId,
                    $stored['storage_name'],
                    $stored['mime'],
                    $stored['size_bytes'],
                    $stored['sha256'],
                    $currentCount + $idx
                );
                $addedImages[] = [
                    'id'         => (int) $added['id'],
                    'url'        => "/api/media/donation-images/{$added['id']}",
                    'sort_order' => (int) $added['sort_order'],
                ];
            }

            $this->pdo->commit();
            return $addedImages;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->fileStorage->deleteMany($storedNames, 'images');
            throw $e;
        }
    }

    public function deleteImage(int $donorId, int $donationId, int $imageId): void
    {
        $donation = $this->donationRepo->findById($donationId);
        if ($donation === null || (int) $donation['donor_id'] !== $donorId) {
            throw new NotFoundException('Donation not found');
        }

        $image = $this->donationRepo->findImageById($imageId);
        if ($image === null || (int) $image['donation_id'] !== $donationId) {
            throw new NotFoundException('Image not found');
        }

        $this->donationRepo->deleteImage($imageId);
        $this->fileStorage->delete($image['storage_name'], 'images');
    }

    private function formatForOwner(array $d): array
    {
        return [
            'id'                 => (int) $d['id'],
            'donor_id'           => (int) ($d['donor_id'] ?? 0),
            'category_id'        => (int) $d['category_id'],
            'category_name'      => $d['category_name'] ?? null,
            'title'              => $d['title'],
            'description'        => $d['description'] ?? '',
            'condition'          => $d['condition'],
            'total_quantity'     => (int) $d['total_quantity'],
            'available_quantity' => (int) $d['available_quantity'],
            'status'             => $d['status'],
            'address_text'       => $d['address_text'],
            'latitude'           => (float) $d['latitude'],
            'longitude'          => (float) $d['longitude'],
            'latitude_public'    => (float) $d['latitude_public'],
            'longitude_public'   => (float) $d['longitude_public'],
            'pickup_notes'       => $d['pickup_notes'] ?? null,
            'expires_at'         => $d['expires_at'] ?? null,
            'created_at'         => $d['created_at'],
            'updated_at'         => $d['updated_at'] ?? null,
            'images'             => $d['images'] ?? [],
        ];
    }

    private function formatForPublic(array $d): array
    {
        return [
            'id'                 => (int) $d['id'],
            'category_id'        => (int) $d['category_id'],
            'category_name'      => $d['category_name'] ?? null,
            'title'              => $d['title'],
            'description'        => $d['description'] ?? '',
            'condition'          => $d['condition'],
            'total_quantity'     => (int) $d['total_quantity'],
            'available_quantity' => (int) $d['available_quantity'],
            'status'             => $d['status'],
            'latitude_public'    => (float) $d['latitude_public'],
            'longitude_public'   => (float) $d['longitude_public'],
            'distance_km'        => isset($d['distance_km']) ? round((float)$d['distance_km'], 2) : null,
            'expires_at'         => $d['expires_at'] ?? null,
            'created_at'         => $d['created_at'],
            'images'             => $d['images'] ?? [],
        ];
    }

    private function normalizeFilesArray(?array $files): array
    {
        if ($files === null || empty($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if (empty($files['name'][$i])) {
                continue;
            }
            $normalized[] = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i] ?? 0,
                'size'     => $files['size'][$i] ?? 0,
            ];
        }

        return $normalized;
    }
}
