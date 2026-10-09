<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\ValidationFailedException;
use App\Repositories\AuditRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\NgoRepository;
use App\Repositories\RequestRepository;
use App\Repositories\AllocationRepository;
use App\Support\Database;
use App\Support\DonationStatus;
use PDO;
use PDOException;
use Throwable;

class AllocationService
{
    private PDO $pdo;
    private RequestRepository $requestRepo;
    private AllocationRepository $allocationRepo;
    private NgoRepository $ngoRepo;
    private CategoryRepository $categoryRepo;
    private Notifier $notifier;
    private AuditRepository $auditRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?RequestRepository $requestRepo = null,
        ?AllocationRepository $allocationRepo = null,
        ?NgoRepository $ngoRepo = null,
        ?CategoryRepository $categoryRepo = null,
        ?Notifier $notifier = null,
        ?AuditRepository $auditRepo = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->requestRepo = $requestRepo ?? new RequestRepository($this->pdo);
        $this->allocationRepo = $allocationRepo ?? new AllocationRepository($this->pdo);
        $this->ngoRepo = $ngoRepo ?? new NgoRepository($this->pdo);
        $this->categoryRepo = $categoryRepo ?? new CategoryRepository($this->pdo);
        $this->notifier = $notifier ?? new Notifier($this->pdo);
        $this->auditRepo = $auditRepo ?? new AuditRepository($this->pdo);
    }

    /**
     * M9.1: Create a donation request with atomic reservation under row locks.
     * Retries once on deadlock (SQLSTATE 40P01).
     */
    public function createRequest(int $userId, array $data, ?string $idempotencyKey = null): array
    {
        $maxAttempts = 2;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->executeCreateRequest($userId, $data, $idempotencyKey);
            } catch (PDOException $e) {
                if ($e->getCode() === '40P01' && $attempt < $maxAttempts) {
                    usleep(50000); // 50ms jitter
                    continue;
                }
                throw $e;
            }
        }

        throw new ConflictException('Transaction failed due to concurrency conflict', 'CONCURRENCY_CONFLICT');
    }

    private function executeCreateRequest(int $userId, array $data, ?string $idempotencyKey = null): array
    {
        // 1. Verify NGO caller
        $ngo = $this->ngoRepo->findNgoByUserId($userId);
        if (!$ngo) {
            throw new ForbiddenException('Only registered NGOs can request donations', 'NGO_REQUIRED');
        }

        if ($ngo['verification_status'] !== 'verified' || $ngo['user_status'] !== 'active') {
            throw new ForbiddenException('Only verified and active NGOs can request donations', 'NGO_NOT_VERIFIED');
        }

        $ngoId = (int) $ngo['id'];

        // 2. Validate input quantity
        if (!isset($data['requested_quantity']) || !is_numeric($data['requested_quantity'])) {
            throw new ValidationFailedException(['requested_quantity' => 'Requested quantity is required and must be an integer']);
        }

        $qty = (int) $data['requested_quantity'];
        if ($qty <= 0 || (float) $data['requested_quantity'] != $qty) {
            throw new ValidationFailedException(['requested_quantity' => 'Requested quantity must be a positive integer']);
        }

        $donationId = isset($data['donation_id']) ? (int) $data['donation_id'] : 0;
        if ($donationId <= 0) {
            throw new ValidationFailedException(['donation_id' => 'Valid donation_id is required']);
        }

        $requirementId = isset($data['requirement_id']) && $data['requirement_id'] !== '' ? (int) $data['requirement_id'] : null;
        $notes = isset($data['notes']) ? trim((string) $data['notes']) : null;

        // 3. Idempotency Check
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = $this->requestRepo->findByNgoAndIdempotencyKey($ngoId, $idempotencyKey);
            if ($existing) {
                return $this->requestRepo->findById((int) $existing['id']);
            }
        }

        // 4. Check duplicate pending request for (ngo_id, donation_id, requirement_id)
        $duplicate = $this->requestRepo->findPendingByTuple($ngoId, $donationId, $requirementId);
        if ($duplicate) {
            throw new ConflictException('You already have a pending request for this donation and requirement', 'DUPLICATE_PENDING_REQUEST');
        }

        // 5. Atomic Transaction with strict lock order: donation first, then requirement
        $this->pdo->beginTransaction();
        try {
            // Lock donation
            $stmtDonation = $this->pdo->prepare("
                SELECT id, donor_id, title, category_id, status, total_quantity, available_quantity, expires_at
                FROM donations
                WHERE id = :id
                FOR UPDATE
            ");
            $stmtDonation->execute([':id' => $donationId]);
            $donation = $stmtDonation->fetch(PDO::FETCH_ASSOC);

            if (!$donation) {
                throw new NotFoundException('Donation not found', 'DONATION_NOT_FOUND');
            }

            // Status check: must be active or partially_allocated
            if (!in_array($donation['status'], [DonationStatus::ACTIVE, DonationStatus::PARTIALLY_ALLOCATED], true)) {
                throw new ConflictException('Donation is not available for requests in status ' . $donation['status'], 'DONATION_NOT_ACTIVE');
            }

            // Expiry check
            if ($donation['expires_at'] !== null && strtotime($donation['expires_at']) <= time()) {
                throw new ConflictException('Donation has expired', 'DONATION_EXPIRED');
            }

            $currentAvailable = (int) $donation['available_quantity'];
            if ($qty > $currentAvailable) {
                throw new ConflictException(
                    "Insufficient stock available ({$currentAvailable} available, {$qty} requested)",
                    'INSUFFICIENT_QUANTITY',
                    ['available' => $currentAvailable]
                );
            }

            // Lock requirement (if specified)
            $requirement = null;
            if ($requirementId !== null) {
                $stmtReq = $this->pdo->prepare("
                    SELECT id, ngo_id, title, category_id, status, quantity_needed, quantity_allocated
                    FROM ngo_requirements
                    WHERE id = :id
                    FOR UPDATE
                ");
                $stmtReq->execute([':id' => $requirementId]);
                $requirement = $stmtReq->fetch(PDO::FETCH_ASSOC);

                if (!$requirement) {
                    throw new NotFoundException('Requirement not found', 'REQUIREMENT_NOT_FOUND');
                }

                if ((int) $requirement['ngo_id'] !== $ngoId) {
                    throw new ForbiddenException('Requirement belongs to another NGO', 'FORBIDDEN_REQUIREMENT');
                }

                if ($requirement['status'] !== 'active') {
                    throw new ConflictException('Requirement is not active', 'REQUIREMENT_NOT_ACTIVE');
                }

                $outstanding = (int) $requirement['quantity_needed'] - (int) $requirement['quantity_allocated'];
                if ($qty > $outstanding) {
                    throw new ConflictException(
                        "Requested quantity ({$qty}) exceeds outstanding requirement need ({$outstanding})",
                        'EXCEEDS_REQUIREMENT_NEED',
                        ['outstanding' => $outstanding]
                    );
                }

                // Category compatibility check
                $isSame = (int) $donation['category_id'] === (int) $requirement['category_id'];
                if (!$isSame) {
                    $isCompat = $this->categoryRepo->isCompatible((int) $donation['category_id'], (int) $requirement['category_id']);
                    if (!$isCompat) {
                        throw new ConflictException('Donation category is incompatible with requirement category', 'INCOMPATIBLE_CATEGORY');
                    }
                }
            }

            // Decrement donation available_quantity with guard
            $newAvailable = $currentAvailable - $qty;
            $newDonationStatus = DonationStatus::derive((int) $donation['total_quantity'], $newAvailable, true);

            $stmtUpdateDonation = $this->pdo->prepare("
                UPDATE donations
                SET available_quantity = available_quantity - :qty,
                    status = :new_status,
                    updated_at = NOW()
                WHERE id = :id AND available_quantity >= :qty
            ");
            $stmtUpdateDonation->execute([
                ':qty'        => $qty,
                ':new_status' => $newDonationStatus,
                ':id'         => $donationId,
            ]);

            if ($stmtUpdateDonation->rowCount() !== 1) {
                throw new ConflictException('Donation stock changed during reservation', 'INSUFFICIENT_QUANTITY');
            }

            // Increment requirement quantity_allocated (if requirement specified)
            if ($requirement !== null) {
                $newAllocated = (int) $requirement['quantity_allocated'] + $qty;
                $newReqStatus = ($newAllocated >= (int) $requirement['quantity_needed']) ? 'fulfilled' : 'active';

                $stmtUpdateReq = $this->pdo->prepare("
                    UPDATE ngo_requirements
                    SET quantity_allocated = quantity_allocated + :qty,
                        status = :new_status,
                        updated_at = NOW()
                    WHERE id = :id AND quantity_allocated + :qty <= quantity_needed
                ");
                $stmtUpdateReq->execute([
                    ':qty'        => $qty,
                    ':new_status' => $newReqStatus,
                    ':id'         => $requirementId,
                ]);

                if ($stmtUpdateReq->rowCount() !== 1) {
                    throw new ConflictException('Requirement allocated quantity exceeded during reservation', 'REQUIREMENT_LIMIT_EXCEEDED');
                }
            }

            // Insert donation_requests row
            $requestId = $this->requestRepo->create([
                'donation_id'        => $donationId,
                'ngo_id'             => $ngoId,
                'requirement_id'     => $requirementId,
                'requested_quantity' => $qty,
                'status'             => 'pending',
                'notes'              => $notes,
                'idempotency_key'    => $idempotencyKey,
            ]);

            // Insert allocations row
            $this->allocationRepo->create([
                'request_id'         => $requestId,
                'donation_id'        => $donationId,
                'requirement_id'     => $requirementId,
                'allocated_quantity' => $qty,
                'status'             => 'reserved',
            ]);

            // Notify donor (request.created) in same transaction
            $donorId = (int) $donation['donor_id'];
            $ngoName = (string) $ngo['organization_name'];
            $this->notifier->notify(
                $donorId,
                Notifier::TYPE_REQUEST_CREATED,
                'New Donation Request',
                "NGO '{$ngoName}' has requested {$qty} unit(s) of '{$donation['title']}'.",
                'request',
                $requestId,
                true,
                'New Donation Request on ShareSphere'
            );

            // Audit log
            $this->auditRepo->log(
                $userId,
                'request.created',
                'donation_request',
                $requestId,
                'success',
                [
                    'donation_id'        => $donationId,
                    'ngo_id'             => $ngoId,
                    'requirement_id'     => $requirementId,
                    'requested_quantity' => $qty,
                ]
            );

            $this->pdo->commit();

            return $this->requestRepo->findById($requestId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * M9.2: Donor accepts a pending request.
     */
    public function acceptRequest(int $donorUserId, int $requestId): array
    {
        $this->pdo->beginTransaction();
        try {
            // Lock request
            $stmtReq = $this->pdo->prepare("
                SELECT r.*, d.donor_id, d.title AS donation_title, n.user_id AS ngo_user_id
                FROM donation_requests r
                JOIN donations d ON d.id = r.donation_id
                JOIN ngos n ON n.id = r.ngo_id
                WHERE r.id = :id
                FOR UPDATE
            ");
            $stmtReq->execute([':id' => $requestId]);
            $req = $stmtReq->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                throw new NotFoundException('Donation request not found', 'REQUEST_NOT_FOUND');
            }

            if ((int) $req['donor_id'] !== $donorUserId) {
                throw new ForbiddenException('Only the donor owning this donation can accept the request', 'FORBIDDEN_DONOR');
            }

            if ($req['status'] === 'accepted') {
                $this->pdo->commit();
                return $this->requestRepo->findById($requestId);
            }

            if ($req['status'] !== 'pending') {
                throw new ConflictException("Cannot accept request in '{$req['status']}' state", 'INVALID_TRANSITION');
            }

            // Lock allocation
            $stmtAlloc = $this->pdo->prepare("
                SELECT * FROM allocations WHERE request_id = :req_id FOR UPDATE
            ");
            $stmtAlloc->execute([':req_id' => $requestId]);
            $alloc = $stmtAlloc->fetch(PDO::FETCH_ASSOC);

            if (!$alloc) {
                throw new NotFoundException('Linked allocation not found', 'ALLOCATION_NOT_FOUND');
            }

            // Update request status to 'accepted'
            $this->requestRepo->updateStatus($requestId, 'accepted', date('Y-m-d H:i:s'));

            // Update allocation status to 'confirmed'
            $this->allocationRepo->updateStatus((int) $alloc['id'], 'confirmed');

            // Notify NGO
            $ngoUserId = (int) $req['ngo_user_id'];
            $this->notifier->notify(
                $ngoUserId,
                Notifier::TYPE_REQUEST_ACCEPTED,
                'Request Accepted',
                "Your request for {$req['requested_quantity']} unit(s) of '{$req['donation_title']}' has been accepted.",
                'request',
                $requestId,
                true,
                'Donation Request Accepted'
            );

            // Audit
            $this->auditRepo->log(
                $donorUserId,
                'request.accepted',
                'donation_request',
                $requestId,
                'success',
                ['allocation_id' => $alloc['id']]
            );

            $this->pdo->commit();

            return $this->requestRepo->findById($requestId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * M9.2: Donor rejects a pending request (returns stock).
     */
    public function rejectRequest(int $donorUserId, int $requestId, ?string $reason = null): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmtReq = $this->pdo->prepare("
                SELECT r.*, d.donor_id, d.title AS donation_title, n.user_id AS ngo_user_id
                FROM donation_requests r
                JOIN donations d ON d.id = r.donation_id
                JOIN ngos n ON n.id = r.ngo_id
                WHERE r.id = :id
                FOR UPDATE
            ");
            $stmtReq->execute([':id' => $requestId]);
            $req = $stmtReq->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                throw new NotFoundException('Donation request not found', 'REQUEST_NOT_FOUND');
            }

            if ((int) $req['donor_id'] !== $donorUserId) {
                throw new ForbiddenException('Only the donor owning this donation can reject the request', 'FORBIDDEN_DONOR');
            }

            if ($req['status'] !== 'pending') {
                throw new ConflictException("Cannot reject request in '{$req['status']}' state", 'INVALID_TRANSITION');
            }

            $this->releaseReservationUnderLock($req, 'rejected', 'cancelled', 'request.rejected', $donorUserId, $reason);

            // Notify NGO
            $ngoUserId = (int) $req['ngo_user_id'];
            $reasonText = $reason ? " Reason: {$reason}" : '';
            $this->notifier->notify(
                $ngoUserId,
                Notifier::TYPE_REQUEST_REJECTED,
                'Request Rejected',
                "Your request for '{$req['donation_title']}' was rejected.{$reasonText}",
                'request',
                $requestId,
                true,
                'Donation Request Rejected'
            );

            $this->pdo->commit();

            return $this->requestRepo->findById($requestId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * M9.2: NGO cancels a pending or accepted request (returns stock).
     */
    public function cancelRequest(int $ngoUserId, int $requestId, ?string $reason = null): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmtReq = $this->pdo->prepare("
                SELECT r.*, d.donor_id, d.title AS donation_title, n.user_id AS ngo_user_id
                FROM donation_requests r
                JOIN donations d ON d.id = r.donation_id
                JOIN ngos n ON n.id = r.ngo_id
                WHERE r.id = :id
                FOR UPDATE
            ");
            $stmtReq->execute([':id' => $requestId]);
            $req = $stmtReq->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                throw new NotFoundException('Donation request not found', 'REQUEST_NOT_FOUND');
            }

            if ((int) $req['ngo_user_id'] !== $ngoUserId) {
                throw new ForbiddenException('Only the requesting NGO can cancel this request', 'FORBIDDEN_NGO');
            }

            if (!in_array($req['status'], ['pending', 'accepted'], true)) {
                throw new ConflictException("Cannot cancel request in '{$req['status']}' state", 'INVALID_TRANSITION');
            }

            // Check if allocation is already collected/completed
            $stmtAlloc = $this->pdo->prepare("
                SELECT * FROM allocations WHERE request_id = :req_id FOR UPDATE
            ");
            $stmtAlloc->execute([':req_id' => $requestId]);
            $alloc = $stmtAlloc->fetch(PDO::FETCH_ASSOC);

            if ($alloc && in_array($alloc['status'], ['collected', 'completed'], true)) {
                throw new ConflictException("Cannot cancel request: allocation is already {$alloc['status']}", 'INVALID_TRANSITION');
            }

            $this->releaseReservationUnderLock($req, 'cancelled', 'cancelled', 'request.cancelled', $ngoUserId, $reason);

            // If it was accepted, notify donor
            if ($req['status'] === 'accepted') {
                $donorId = (int) $req['donor_id'];
                $this->notifier->notify(
                    $donorId,
                    Notifier::TYPE_DONATION_CANCELLED,
                    'Request Cancelled by NGO',
                    "The NGO has cancelled their request for '{$req['donation_title']}'.",
                    'request',
                    $requestId,
                    true,
                    'Donation Request Cancelled'
                );
            }

            $this->pdo->commit();

            return $this->requestRepo->findById($requestId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * M9.2: Background job to expire pending requests older than 72h.
     */
    public function expirePendingRequests(): int
    {
        $expiredList = $this->requestRepo->findExpiredPending();
        $count = 0;

        foreach ($expiredList as $item) {
            $requestId = (int) $item['id'];
            $this->pdo->beginTransaction();
            try {
                $stmtReq = $this->pdo->prepare("
                    SELECT r.*, d.donor_id, d.title AS donation_title, n.user_id AS ngo_user_id
                    FROM donation_requests r
                    JOIN donations d ON d.id = r.donation_id
                    JOIN ngos n ON n.id = r.ngo_id
                    WHERE r.id = :id AND r.status = 'pending' AND r.expires_at <= NOW()
                    FOR UPDATE
                ");
                $stmtReq->execute([':id' => $requestId]);
                $req = $stmtReq->fetch(PDO::FETCH_ASSOC);

                if ($req) {
                    $this->releaseReservationUnderLock($req, 'expired', 'cancelled', 'request.expired', null, 'Auto-expired after 72h');

                    // Notify both parties
                    $this->notifier->notify(
                        (int) $req['donor_id'],
                        Notifier::TYPE_DONATION_CANCELLED,
                        'Donation Request Expired',
                        "A pending request for '{$req['donation_title']}' has expired without a response.",
                        'request',
                        $requestId
                    );
                    $this->notifier->notify(
                        (int) $req['ngo_user_id'],
                        Notifier::TYPE_REQUEST_REJECTED,
                        'Donation Request Expired',
                        "Your request for '{$req['donation_title']}' has expired without a response from the donor.",
                        'request',
                        $requestId
                    );

                    $count++;
                }

                $this->pdo->commit();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
            }
        }

        return $count;
    }

    /**
     * Single authority for returning reserved stock and updating lifecycle states.
     * MUST be called within an active transaction where row locks are held.
     */
    private function releaseReservationUnderLock(
        array $req,
        string $newRequestStatus,
        string $newAllocationStatus,
        string $auditAction,
        ?int $actorUserId = null,
        ?string $reason = null
    ): void {
        $requestId = (int) $req['id'];
        $donationId = (int) $req['donation_id'];
        $requirementId = !empty($req['requirement_id']) ? (int) $req['requirement_id'] : null;
        $qty = (int) $req['requested_quantity'];

        // 1. Lock and update donation (return available_quantity)
        $stmtDonation = $this->pdo->prepare("
            SELECT id, total_quantity, available_quantity, status
            FROM donations
            WHERE id = :id
            FOR UPDATE
        ");
        $stmtDonation->execute([':id' => $donationId]);
        $donation = $stmtDonation->fetch(PDO::FETCH_ASSOC);

        if ($donation) {
            $newAvailable = (int) $donation['available_quantity'] + $qty;
            if ($newAvailable > (int) $donation['total_quantity']) {
                $newAvailable = (int) $donation['total_quantity'];
            }

            $hasOtherOpenAllocations = $newAvailable < (int) $donation['total_quantity'];
            // If current status was closed or removed, preserve that status; otherwise derive
            if (!in_array($donation['status'], [DonationStatus::CLOSED, DonationStatus::REMOVED, DonationStatus::DRAFT], true)) {
                $newDonationStatus = DonationStatus::derive((int) $donation['total_quantity'], $newAvailable, $hasOtherOpenAllocations);
            } else {
                $newDonationStatus = $donation['status'];
            }

            $stmtUpdateDonation = $this->pdo->prepare("
                UPDATE donations
                SET available_quantity = :available,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmtUpdateDonation->execute([
                ':available' => $newAvailable,
                ':status'    => $newDonationStatus,
                ':id'        => $donationId,
            ]);
        }

        // 2. Lock and update requirement (decrement quantity_allocated)
        if ($requirementId !== null) {
            $stmtReq = $this->pdo->prepare("
                SELECT id, quantity_needed, quantity_allocated, status
                FROM ngo_requirements
                WHERE id = :id
                FOR UPDATE
            ");
            $stmtReq->execute([':id' => $requirementId]);
            $requirement = $stmtReq->fetch(PDO::FETCH_ASSOC);

            if ($requirement) {
                $newAllocated = (int) $requirement['quantity_allocated'] - $qty;
                if ($newAllocated < 0) {
                    $newAllocated = 0;
                }

                // If requirement was closed, keep closed; otherwise active
                $newReqStatus = ($requirement['status'] === 'closed') ? 'closed' : 'active';

                $stmtUpdateReq = $this->pdo->prepare("
                    UPDATE ngo_requirements
                    SET quantity_allocated = :allocated,
                        status = :status,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmtUpdateReq->execute([
                    ':allocated' => $newAllocated,
                    ':status'    => $newReqStatus,
                    ':id'        => $requirementId,
                ]);
            }
        }

        // 3. Update request status
        $this->requestRepo->updateStatus($requestId, $newRequestStatus, date('Y-m-d H:i:s'));

        // 4. Update allocation status
        $this->allocationRepo->updateStatusByRequestId($requestId, $newAllocationStatus);

        // 5. Audit
        $this->auditRepo->log(
            $actorUserId,
            $auditAction,
            'donation_request',
            $requestId,
            'success',
            [
                'returned_quantity' => $qty,
                'reason'            => $reason,
            ]
        );
    }

    /**
     * Retrieve single request with access check.
     */
    public function getRequest(int $userId, string $role, int $requestId): array
    {
        $request = $this->requestRepo->findById($requestId);
        if (!$request) {
            throw new NotFoundException('Request not found', 'REQUEST_NOT_FOUND');
        }

        if ($role === 'admin') {
            return $request;
        }

        if ($role === 'donor') {
            if ((int) $request['donor_id'] !== $userId) {
                throw new NotFoundException('Request not found', 'REQUEST_NOT_FOUND');
            }
            return $request;
        }

        if ($role === 'ngo') {
            $ngo = $this->ngoRepo->findNgoByUserId($userId);
            if (!$ngo || (int) $request['ngo_id'] !== (int) $ngo['id']) {
                throw new NotFoundException('Request not found', 'REQUEST_NOT_FOUND');
            }
            return $request;
        }

        throw new ForbiddenException('Unauthorized access to request', 'FORBIDDEN');
    }

    /**
     * List requests for current user according to their role.
     */
    public function listRequests(int $userId, string $role, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        if ($role === 'admin') {
            $items = $this->requestRepo->listAll($filters, $limit, $offset);
            $total = $this->requestRepo->countAll($filters);
        } elseif ($role === 'donor') {
            $items = $this->requestRepo->listForDonor($userId, $filters, $limit, $offset);
            $total = $this->requestRepo->countForDonor($userId, $filters);
        } elseif ($role === 'ngo') {
            $ngo = $this->ngoRepo->findNgoByUserId($userId);
            if (!$ngo) {
                return ['items' => [], 'total' => 0];
            }
            $items = $this->requestRepo->listForNgo((int) $ngo['id'], $filters, $limit, $offset);
            $total = $this->requestRepo->countForNgo((int) $ngo['id'], $filters);
        } else {
            throw new ForbiddenException('Invalid role', 'FORBIDDEN');
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}
