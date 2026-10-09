<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\UserRepository;
use PDO;

class MediaPolicyDonationImage implements MediaPolicyInterface
{
    private PDO $pdo;
    private UserRepository $userRepo;

    public function __construct(?PDO $pdo = null, ?UserRepository $userRepo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->userRepo = $userRepo ?? new UserRepository($this->pdo);
    }

    public function authorize(int $userId, ?string $userRole, int $resourceId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.id, i.donation_id, i.storage_name, i.original_mime as mime,
                   d.donor_id, d.status as donation_status
            FROM donation_images i
            JOIN donations d ON i.donation_id = d.id
            WHERE i.id = :id
        ");
        $stmt->execute([':id' => $resourceId]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($image === false) {
            return null;
        }

        // 1. Admin access
        if ($userRole === 'admin') {
            return [
                'storage_name' => $image['storage_name'],
                'mime'         => $image['mime'],
                'kind'         => 'images',
                'disposition'  => 'inline',
            ];
        }

        // 2. Owning donor access
        if ((int) $image['donor_id'] === $userId) {
            return [
                'storage_name' => $image['storage_name'],
                'mime'         => $image['mime'],
                'kind'         => 'images',
                'disposition'  => 'inline',
            ];
        }

        // 3. Verified NGO access when donation is active / allocated
        if ($userRole === 'ngo') {
            $ngo = $this->userRepo->findNgoByUserId($userId);
            if ($ngo !== null && $ngo['verification_status'] === 'verified') {
                if (in_array($image['donation_status'], ['active', 'partially_allocated', 'fully_allocated'], true)) {
                    return [
                        'storage_name' => $image['storage_name'],
                        'mime'         => $image['mime'],
                        'kind'         => 'images',
                        'disposition'  => 'inline',
                    ];
                }
            }
        }

        return null;
    }
}
