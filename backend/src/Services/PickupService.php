<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\ValidationFailedException;
use App\Repositories\AllocationRepository;
use App\Repositories\AuditRepository;
use App\Repositories\DonationRepository;
use App\Repositories\NgoRepository;
use App\Repositories\PickupRepository;
use App\Repositories\RequirementRepository;
use App\Support\Database;
use App\Support\DonationStatus;
use App\Support\PickupResource;
use App\Support\PickupStateMachine;
use PDO;
use Throwable;

class PickupService
{
    private PDO $pdo;
    private PickupRepository $pickupRepo;
    private AllocationRepository $allocationRepo;
    private DonationRepository $donationRepo;
    private RequirementRepository $reqRepo;
    private NgoRepository $ngoRepo;
    private OtpService $otpService;
    private Notifier $notifier;
    private AuditRepository $auditRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?PickupRepository $pickupRepo = null,
        ?AllocationRepository $allocationRepo = null,
        ?DonationRepository $donationRepo = null,
        ?RequirementRepository $reqRepo = null,
        ?NgoRepository $ngoRepo = null,
        ?OtpService $otpService = null,
        ?Notifier $notifier = null,
        ?AuditRepository $auditRepo = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->pickupRepo = $pickupRepo ?? new PickupRepository($this->pdo);
        $this->allocationRepo = $allocationRepo ?? new AllocationRepository($this->pdo);
        $this->donationRepo = $donationRepo ?? new DonationRepository($this->pdo);
        $this->reqRepo = $reqRepo ?? new RequirementRepository($this->pdo);
        $this->ngoRepo = $ngoRepo ?? new NgoRepository($this->pdo);
        $this->otpService = $otpService ?? new OtpService($this->pdo);
        $this->notifier = $notifier ?? new Notifier($this->pdo);
        $this->auditRepo = $auditRepo ?? new AuditRepository($this->pdo);
    }

    /**
     * M10.1: Propose a pickup for an accepted/confirmed allocation.
     */
    public function proposePickup(int $userId, string $role, array $data): array
    {
        $allocationId = isset($data['allocation_id']) ? (int) $data['allocation_id'] : 0;
        if ($allocationId <= 0) {
            throw new ValidationFailedException(['allocation_id' => 'Valid allocation_id is required']);
        }

        $scheduledAtStr = isset($data['scheduled_at']) ? trim((string) $data['scheduled_at']) : '';
        $scheduledTs = strtotime($scheduledAtStr);
        $now = time();

        if ($scheduledTs === false || $scheduledTs < ($now + 3600) || $scheduledTs > ($now + (60 * 86400))) {
            throw new ValidationFailedException([
                'scheduled_at' => 'Scheduled time must be a valid future ISO date between 1 hour and 60 days from now',
            ]);
        }

        $locationDetails = isset($data['location_details']) ? trim((string) $data['location_details']) : null;
        if ($locationDetails !== null && mb_strlen($locationDetails) > 500) {
            throw new ValidationFailedException(['location_details' => 'Location details cannot exceed 500 characters']);
        }

        $contactNote = isset($data['contact_note']) ? trim((string) $data['contact_note']) : null;

        $this->pdo->beginTransaction();
        try {
            $alloc = $this->allocationRepo->findById($allocationId);
            if (!$alloc) {
                throw new NotFoundException('Allocation not found', 'ALLOCATION_NOT_FOUND');
            }

            if ($alloc['status'] !== 'confirmed') {
                throw new ConflictException("Cannot schedule pickup for allocation in '{$alloc['status']}' state", 'ALLOCATION_NOT_CONFIRMED');
            }

            // Check caller authorization
            $isDonor = ((int) $alloc['donor_id'] === $userId);
            $ngo = $this->ngoRepo->findNgoByUserId($userId);
            $isNgo = ($ngo && (int) $alloc['ngo_id'] === (int) $ngo['id']);

            if (!$isDonor && !$isNgo && $role !== 'admin') {
                throw new ForbiddenException('You are not a participant in this donation allocation', 'FORBIDDEN');
            }

            // Check if there is already an active pickup for this allocation
            $activePickup = $this->pickupRepo->findActiveByAllocationId($allocationId);
            if ($activePickup) {
                throw new ConflictException('An active pickup already exists for this allocation', 'ACTIVE_PICKUP_EXISTS');
            }

            $pickupId = $this->pickupRepo->create([
                'allocation_id'    => $allocationId,
                'proposed_by'      => $userId,
                'scheduled_at'     => date('Y-m-d H:i:s', $scheduledTs),
                'location_details' => $locationDetails,
                'contact_note'     => $contactNote,
                'state'            => 'proposed',
            ]);

            // Notify the other party
            $otherUserId = $isDonor ? (int) $alloc['ngo_id'] : (int) $alloc['donor_id'];
            if ($isNgo) {
                // If NGO proposed, other user is donor
                $donorId = (int) $alloc['donor_id'];
                $this->notifier->notify(
                    $donorId,
                    Notifier::TYPE_PICKUP_PROPOSED,
                    'Pickup Time Proposed',
                    "The NGO has proposed a pickup time for your donation. Please confirm or reschedule.",
                    'pickup',
                    $pickupId
                );
            } else {
                // If donor proposed, other user is NGO
                $ngoUser = $this->ngoRepo->findNgoById((int) $alloc['ngo_id']);
                if ($ngoUser) {
                    $this->notifier->notify(
                        (int) $ngoUser['user_id'],
                        Notifier::TYPE_PICKUP_PROPOSED,
                        'Pickup Time Proposed',
                        "The donor has proposed a pickup time. Please confirm or reschedule.",
                        'pickup',
                        $pickupId
                    );
                }
            }

            $this->auditRepo->log($userId, 'pickup.proposed', 'pickup', $pickupId, 'success', [
                'scheduled_at' => date('Y-m-d H:i:s', $scheduledTs),
            ]);

            $this->pdo->commit();

            $created = $this->pickupRepo->findById($pickupId);
            return PickupResource::format($created, $role);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * M10.1: Confirm pickup by the other participant.
     */
    public function confirmPickup(int $userId, string $role, int $pickupId): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM pickups WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $pickupId]);
            $pickup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pickup) {
                throw new NotFoundException('Pickup not found', 'PICKUP_NOT_FOUND');
            }

            $fullPickup = $this->pickupRepo->findById($pickupId);
            $donorUserId = (int) $fullPickup['donor_id'];
            $ngoUserId = (int) $fullPickup['ngo_user_id'];

            // Authorization: must be a participant
            if ($userId !== $donorUserId && $userId !== $ngoUserId && $role !== 'admin') {
                throw new ForbiddenException('Not a participant in this pickup', 'FORBIDDEN');
            }

            // Proposer cannot confirm own proposal (prevents agreeing with oneself)
            if ((int) $pickup['proposed_by'] === $userId && $role !== 'admin') {
                throw new ConflictException('You cannot confirm your own pickup proposal. The other party must confirm.', 'CANNOT_SELF_CONFIRM');
            }

            PickupStateMachine::assertCanTransition($pickup['state'], PickupStateMachine::STATE_SCHEDULED);

            $this->pickupRepo->updateState($pickupId, PickupStateMachine::STATE_SCHEDULED);

            // Notify the proposer
            $this->notifier->notify(
                (int) $pickup['proposed_by'],
                Notifier::TYPE_PICKUP_SCHEDULED,
                'Pickup Confirmed',
                "Your proposed pickup time has been confirmed and scheduled.",
                'pickup',
                $pickupId
            );

            $this->auditRepo->log($userId, 'pickup.confirmed', 'pickup', $pickupId, 'success');

            $this->pdo->commit();

            $updated = $this->pickupRepo->findById($pickupId);
            return PickupResource::format($updated, $role);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * M10.1: Reschedule pickup (resets to proposed, invalidates existing OTP).
     */
    public function reschedulePickup(int $userId, string $role, int $pickupId, array $data): array
    {
        $scheduledAtStr = isset($data['scheduled_at']) ? trim((string) $data['scheduled_at']) : '';
        $scheduledTs = strtotime($scheduledAtStr);
        $now = time();

        if ($scheduledTs === false || $scheduledTs < ($now + 3600) || $scheduledTs > ($now + (60 * 86400))) {
            throw new ValidationFailedException([
                'scheduled_at' => 'Scheduled time must be a valid future ISO date between 1 hour and 60 days from now',
            ]);
        }

        $locationDetails = isset($data['location_details']) ? trim((string) $data['location_details']) : null;
        if ($locationDetails !== null && mb_strlen($locationDetails) > 500) {
            throw new ValidationFailedException(['location_details' => 'Location details cannot exceed 500 characters']);
        }

        $contactNote = isset($data['contact_note']) ? trim((string) $data['contact_note']) : null;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM pickups WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $pickupId]);
            $pickup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pickup) {
                throw new NotFoundException('Pickup not found', 'PICKUP_NOT_FOUND');
            }

            $fullPickup = $this->pickupRepo->findById($pickupId);
            $donorUserId = (int) $fullPickup['donor_id'];
            $ngoUserId = (int) $fullPickup['ngo_user_id'];

            if ($userId !== $donorUserId && $userId !== $ngoUserId && $role !== 'admin') {
                throw new ForbiddenException('Not a participant in this pickup', 'FORBIDDEN');
            }

            if (in_array($pickup['state'], ['collected', 'completed', 'cancelled'], true)) {
                throw new ConflictException("Cannot reschedule pickup in '{$pickup['state']}' state", 'INVALID_PICKUP_STATE');
            }

            $this->pickupRepo->updateSchedule(
                $pickupId,
                date('Y-m-d H:i:s', $scheduledTs),
                $locationDetails ?? $pickup['location_details'],
                $contactNote ?? $pickup['contact_note'],
                $userId
            );

            // Notify the other party
            $otherUserId = ($userId === $donorUserId) ? $ngoUserId : $donorUserId;
            $this->notifier->notify(
                $otherUserId,
                Notifier::TYPE_PICKUP_PROPOSED,
                'Pickup Rescheduled',
                "A new pickup time has been proposed. Please confirm or propose an alternate time.",
                'pickup',
                $pickupId
            );

            $this->auditRepo->log($userId, 'pickup.rescheduled', 'pickup', $pickupId, 'success', [
                'new_scheduled_at' => date('Y-m-d H:i:s', $scheduledTs),
            ]);

            $this->pdo->commit();

            $updated = $this->pickupRepo->findById($pickupId);
            return PickupResource::format($updated, $role);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * M10.1: Cancel an active pickup (pre-collected).
     */
    public function cancelPickup(int $userId, string $role, int $pickupId, ?string $reason = null): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM pickups WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $pickupId]);
            $pickup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pickup) {
                throw new NotFoundException('Pickup not found', 'PICKUP_NOT_FOUND');
            }

            $fullPickup = $this->pickupRepo->findById($pickupId);
            $donorUserId = (int) $fullPickup['donor_id'];
            $ngoUserId = (int) $fullPickup['ngo_user_id'];

            if ($userId !== $donorUserId && $userId !== $ngoUserId && $role !== 'admin') {
                throw new ForbiddenException('Not a participant in this pickup', 'FORBIDDEN');
            }

            if (in_array($pickup['state'], ['collected', 'completed'], true)) {
                throw new ConflictException("Cannot cancel pickup in '{$pickup['state']}' state", 'INVALID_PICKUP_STATE');
            }

            PickupStateMachine::assertCanTransition($pickup['state'], PickupStateMachine::STATE_CANCELLED);

            $this->pickupRepo->updateState($pickupId, PickupStateMachine::STATE_CANCELLED);

            // Notify other party
            $otherUserId = ($userId === $donorUserId) ? $ngoUserId : $donorUserId;
            $this->notifier->notify(
                $otherUserId,
                Notifier::TYPE_DONATION_CANCELLED,
                'Pickup Cancelled',
                "The scheduled pickup was cancelled." . ($reason ? " Reason: {$reason}" : ''),
                'pickup',
                $pickupId
            );

            $this->auditRepo->log($userId, 'pickup.cancelled', 'pickup', $pickupId, 'success', [
                'reason' => $reason,
            ]);

            $this->pdo->commit();

            $updated = $this->pickupRepo->findById($pickupId);
            return PickupResource::format($updated, $role);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * M10.2: Issue OTP for pickup (NGO action).
     */
    public function issueOtp(int $userId, int $pickupId, ?int $nowTimestamp = null): array
    {
        return $this->otpService->issue($pickupId, $userId, $nowTimestamp);
    }

    /**
     * M10.2: Verify OTP for pickup (Donor action).
     */
    public function verifyOtp(int $userId, int $pickupId, string $otp, ?int $nowTimestamp = null): array
    {
        return $this->otpService->verify($pickupId, $userId, $otp, $nowTimestamp);
    }

    /**
     * M10.3: NGO confirms receipt of items -> pickup completed, requirement progress updated.
     */
    public function confirmReceipt(int $userId, string $role, int $pickupId): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM pickups WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $pickupId]);
            $pickup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pickup) {
                throw new NotFoundException('Pickup not found', 'PICKUP_NOT_FOUND');
            }

            $fullPickup = $this->pickupRepo->findById($pickupId);
            $donorUserId = (int) $fullPickup['donor_id'];
            $ngoUserId = (int) $fullPickup['ngo_user_id'];

            if ($userId !== $ngoUserId && $role !== 'admin') {
                throw new ForbiddenException('Only the receiving NGO can confirm receipt of items', 'FORBIDDEN_NGO');
            }

            if ($pickup['state'] !== 'collected') {
                throw new ConflictException("Cannot confirm receipt when pickup is in '{$pickup['state']}' state", 'INVALID_PICKUP_STATE');
            }

            $allocationId = (int) $pickup['allocation_id'];
            $donationId = (int) $fullPickup['donation_id'];
            $requirementId = !empty($fullPickup['requirement_id']) ? (int) $fullPickup['requirement_id'] : null;
            $qty = (int) $fullPickup['allocated_quantity'];

            // 1. Mark pickup completed
            $this->pickupRepo->markCompleted($pickupId);

            // 2. Mark allocation completed
            $this->allocationRepo->updateStatus($allocationId, 'completed');

            // 3. Update requirement quantity_fulfilled (if requirement linked)
            if ($requirementId !== null) {
                $stmtReq = $this->pdo->prepare("
                    SELECT id, quantity_needed, quantity_allocated, quantity_fulfilled
                    FROM ngo_requirements
                    WHERE id = :id
                    FOR UPDATE
                ");
                $stmtReq->execute([':id' => $requirementId]);
                $req = $stmtReq->fetch(PDO::FETCH_ASSOC);

                if ($req) {
                    $newFulfilled = (int) $req['quantity_fulfilled'] + $qty;
                    $newReqStatus = ($newFulfilled >= (int) $req['quantity_needed']) ? 'fulfilled' : 'partially_fulfilled';

                    $stmtUpdateReq = $this->pdo->prepare("
                        UPDATE ngo_requirements
                        SET quantity_fulfilled = quantity_fulfilled + :qty,
                            status = :status,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmtUpdateReq->execute([
                        ':qty'    => $qty,
                        ':status' => $newReqStatus,
                        ':id'     => $requirementId,
                    ]);
                }
            }

            // 4. Update donation status: if available_quantity == 0 and all allocations are completed/cancelled
            $stmtDon = $this->pdo->prepare("
                SELECT id, total_quantity, available_quantity, status
                FROM donations
                WHERE id = :id
                FOR UPDATE
            ");
            $stmtDon->execute([':id' => $donationId]);
            $donation = $stmtDon->fetch(PDO::FETCH_ASSOC);

            if ($donation) {
                $avail = (int) $donation['available_quantity'];
                // Check if any open allocations remain
                $stmtOpenAlloc = $this->pdo->prepare("
                    SELECT COUNT(*) FROM allocations
                    WHERE donation_id = :d_id AND status IN ('reserved', 'confirmed', 'collected')
                ");
                $stmtOpenAlloc->execute([':d_id' => $donationId]);
                $openAllocCount = (int) $stmtOpenAlloc->fetchColumn();

                if ($avail === 0 && $openAllocCount === 0) {
                    $this->pdo->prepare("UPDATE donations SET status = 'completed', updated_at = NOW() WHERE id = :id")
                        ->execute([':id' => $donationId]);
                }
            }

            // Notify both parties
            $this->notifier->notify(
                $donorUserId,
                Notifier::TYPE_HANDOVER_COMPLETED,
                'Handover Completed',
                "The NGO has confirmed receipt of {$qty} unit(s) of '{$fullPickup['donation_title']}'. Thank you for your generosity!",
                'pickup',
                $pickupId
            );

            $this->notifier->notify(
                $ngoUserId,
                Notifier::TYPE_HANDOVER_COMPLETED,
                'Handover Completed',
                "You have confirmed receipt of '{$fullPickup['donation_title']}'. Handover is complete.",
                'pickup',
                $pickupId
            );

            // Audit log
            $this->auditRepo->log($userId, 'handover.completed', 'pickup', $pickupId, 'success', [
                'donation_id'        => $donationId,
                'requirement_id'     => $requirementId,
                'fulfilled_quantity' => $qty,
            ]);

            $this->pdo->commit();

            $updated = $this->pickupRepo->findById($pickupId);
            return PickupResource::format($updated, $role);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Retrieve single pickup with privacy formatting.
     */
    public function getPickup(int $userId, string $role, int $pickupId): array
    {
        $pickup = $this->pickupRepo->findById($pickupId);
        if (!$pickup) {
            throw new NotFoundException('Pickup not found', 'PICKUP_NOT_FOUND');
        }

        $donorUserId = (int) $pickup['donor_id'];
        $ngoUserId = (int) $pickup['ngo_user_id'];

        if ($userId !== $donorUserId && $userId !== $ngoUserId && $role !== 'admin') {
            throw new NotFoundException('Pickup not found', 'PICKUP_NOT_FOUND');
        }

        return PickupResource::format($pickup, $role);
    }

    /**
     * List pickups for current user.
     */
    public function listPickups(int $userId, string $role, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $items = $this->pickupRepo->listForUser($userId, $role, $filters, $limit, $offset);
        $total = $this->pickupRepo->countForUser($userId, $role, $filters);

        return [
            'items' => PickupResource::formatCollection($items, $role),
            'total' => $total,
        ];
    }

    /**
     * M10.3: Retrieve donation audit & status history for the owner.
     */
    public function getDonationHistory(int $userId, string $role, int $donationId): array
    {
        $donation = $this->donationRepo->findById($donationId);
        if (!$donation) {
            throw new NotFoundException('Donation not found', 'DONATION_NOT_FOUND');
        }

        if ($role !== 'admin' && (int) $donation['donor_id'] !== $userId) {
            throw new NotFoundException('Donation not found', 'DONATION_NOT_FOUND');
        }

        $stmt = $this->pdo->prepare("
            SELECT al.id, al.actor_user_id, al.action, al.target_type, al.target_id,
                   al.result, al.metadata, al.created_at, u.name AS actor_name, u.role AS actor_role
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.actor_user_id
            WHERE (al.target_type = 'donation' AND al.target_id = :d_id)
               OR (al.target_type = 'donation_request' AND al.target_id IN (
                   SELECT id FROM donation_requests WHERE donation_id = :d_id
               ))
               OR (al.target_type = 'pickup' AND al.target_id IN (
                   SELECT p.id FROM pickups p
                   JOIN allocations a ON a.id = p.allocation_id
                   WHERE a.donation_id = :d_id
               ))
            ORDER BY al.created_at ASC
        ");
        $stmt->execute([':d_id' => $donationId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $history = [];
        foreach ($rows as $row) {
            $meta = $row['metadata'] ? json_decode($row['metadata'], true) : [];
            // Guarantee NO plaintext OTP code in history output
            if (isset($meta['otp'])) {
                unset($meta['otp']);
            }
            $history[] = [
                'id'         => (int) $row['id'],
                'action'     => $row['action'],
                'actor'      => [
                    'id'   => $row['actor_user_id'] ? (int) $row['actor_user_id'] : null,
                    'name' => $row['actor_name'] ?? 'System',
                    'role' => $row['actor_role'] ?? 'system',
                ],
                'target'     => [
                    'type' => $row['target_type'],
                    'id'   => (int) $row['target_id'],
                ],
                'result'     => $row['result'],
                'metadata'   => $meta,
                'created_at' => $row['created_at'],
            ];
        }

        return $history;
    }
}
