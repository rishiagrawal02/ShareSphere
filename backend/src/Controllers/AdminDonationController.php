<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\ValidationFailedException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\AuditRepository;
use App\Repositories\DonationRepository;
use App\Services\Notifier;
use App\Support\Database;
use PDO;

class AdminDonationController
{
    private PDO $pdo;
    private DonationRepository $donationRepo;
    private Notifier $notifier;
    private AuditRepository $auditRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?DonationRepository $donationRepo = null,
        ?Notifier $notifier = null,
        ?AuditRepository $auditRepo = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->donationRepo = $donationRepo ?? new DonationRepository($this->pdo);
        $this->notifier = $notifier ?? new Notifier($this->pdo);
        $this->auditRepo = $auditRepo ?? new AuditRepository($this->pdo);
    }

    /**
     * POST /api/admin/donations/{id}/moderate
     */
    public function moderate(Request $request, int $id): Response
    {
        $adminUserId = (int) $request->getAttribute('auth_user_id');
        $body        = $request->getBody();

        $action = (string) ($body['action'] ?? '');
        $reason = trim((string) ($body['reason'] ?? ''));

        if (!in_array($action, ['remove', 'restore'], true)) {
            throw new ValidationFailedException(['action' => 'Action must be remove or restore']);
        }

        if (empty($reason)) {
            throw new ValidationFailedException(['reason' => 'A moderation reason is mandatory']);
        }

        $donation = $this->donationRepo->findById($id);
        if ($donation === null) {
            throw new NotFoundException('Donation not found');
        }

        $donorId = (int) $donation['donor_id'];
        $title   = $donation['title'];

        if ($action === 'remove') {
            if ($this->donationRepo->hasOpenAllocations($id) && empty($body['force'])) {
                throw new ConflictException('Donation has active allocations. Set force=true to remove and cancel allocations.', 'HAS_OPEN_ALLOCATIONS');
            }

            $this->donationRepo->update($id, ['status' => 'removed', 'available_quantity' => 0]);

            $this->auditRepo->log(
                $adminUserId,
                'donation.moderated.remove',
                'donations',
                $id,
                'success',
                ['reason' => $reason]
            );

            $this->notifier->notify(
                $donorId,
                'donation.moderated.removed',
                'Donation Listing Removed by Moderator',
                "Your listing '{$title}' was removed by an administrator. Reason: {$reason}",
                'donations',
                $id
            );
        } else {
            // Restore to active
            $this->donationRepo->update($id, [
                'status'             => 'active',
                'available_quantity' => (int) $donation['total_quantity'],
            ]);

            $this->auditRepo->log(
                $adminUserId,
                'donation.moderated.restore',
                'donations',
                $id,
                'success',
                ['reason' => $reason]
            );

            $this->notifier->notify(
                $donorId,
                'donation.moderated.restored',
                'Donation Listing Restored',
                "Your listing '{$title}' has been restored to active status.",
                'donations',
                $id
            );
        }

        $updated = $this->donationRepo->findById($id);

        return Response::json([
            'status'   => 'ok',
            'message'  => "Donation {$action}d successfully",
            'donation' => $updated,
        ]);
    }
}
