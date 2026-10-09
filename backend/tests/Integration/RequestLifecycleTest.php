<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\ValidationFailedException;
use App\Repositories\CategoryRepository;
use App\Repositories\DonationRepository;
use App\Repositories\NgoRepository;
use App\Repositories\RequirementRepository;
use App\Repositories\UserRepository;
use App\Services\AllocationService;
use App\Support\Database;
use App\Tests\Support\Invariants;
use PDO;
use PHPUnit\Framework\TestCase;

class RequestLifecycleTest extends TestCase
{
    private PDO $pdo;
    private AllocationService $service;
    private UserRepository $userRepo;
    private NgoRepository $ngoRepo;
    private CategoryRepository $catRepo;
    private DonationRepository $donationRepo;
    private RequirementRepository $reqRepo;

    private int $donorUserId;
    private int $ngoUserId;
    private int $ngoId;
    private int $ngo2UserId;
    private int $ngo2Id;
    private int $pendingNgoUserId;
    private int $categoryId;
    private int $compatCategoryId;

    public static function setUpBeforeClass(): void
    {
        $pdo = Database::getOwnerConnection();
        $pdo->exec("
            DELETE FROM allocations;
            DELETE FROM donation_requests;
            DELETE FROM donations;
            DELETE FROM ngo_requirements;
        ");
    }

    protected function setUp(): void
    {
        $this->pdo = Database::getOwnerConnection();
        $this->service = new AllocationService($this->pdo);
        $this->userRepo = new UserRepository($this->pdo);
        $this->ngoRepo = new NgoRepository($this->pdo);
        $this->catRepo = new CategoryRepository($this->pdo);
        $this->donationRepo = new DonationRepository($this->pdo);
        $this->reqRepo = new RequirementRepository($this->pdo);

        $suffix = bin2hex(random_bytes(4));

        // 1. Categories
        $catA = $this->catRepo->create("Cat A {$suffix}", 'Category A description');
        $this->categoryId = (int) $catA['id'];

        $catB = $this->catRepo->create("Cat B {$suffix}", 'Category B description');
        $this->compatCategoryId = (int) $catB['id'];

        $this->catRepo->setCompatibility($this->categoryId, [
            ['compatible_category_id' => $this->compatCategoryId, 'score_factor' => 60.0],
        ]);

        // 2. Donor User
        $donor = $this->userRepo->create([
            'name'           => 'Test Donor',
            'email'          => "donor_{$suffix}@test.local",
            'password_hash'  => password_hash('DonorSecret123!', PASSWORD_DEFAULT),
            'role'           => 'donor',
            'account_status' => 'active',
        ]);
        $this->donorUserId = (int) $donor['id'];

        // 3. Verified NGO 1
        $ngo1User = $this->userRepo->create([
            'name'           => 'Test NGO 1',
            'email'          => "ngo1_{$suffix}@test.local",
            'password_hash'  => password_hash('NgoSecret123!', PASSWORD_DEFAULT),
            'role'           => 'ngo',
            'account_status' => 'active',
        ]);
        $this->ngoUserId = (int) $ngo1User['id'];
        $ngo1 = $this->ngoRepo->createNgo([
            'user_id'             => $this->ngoUserId,
            'organization_name'   => "Relief Org 1 {$suffix}",
            'registration_number' => "REG-1-{$suffix}",
            'contact_phone'       => '+1111111111',
            'address_text'        => '123 Charity Lane',
            'latitude'            => 12.9716,
            'longitude'           => 77.5946,
        ]);
        $this->ngoId = (int) $ngo1['id'];
        $this->ngoRepo->updateVerification($this->ngoId, 'verified', null, 'Auto verified for test');

        // 4. Verified NGO 2
        $ngo2User = $this->userRepo->create([
            'name'           => 'Test NGO 2',
            'email'          => "ngo2_{$suffix}@test.local",
            'password_hash'  => password_hash('NgoSecret123!', PASSWORD_DEFAULT),
            'role'           => 'ngo',
            'account_status' => 'active',
        ]);
        $this->ngo2UserId = (int) $ngo2User['id'];
        $ngo2 = $this->ngoRepo->createNgo([
            'user_id'             => $this->ngo2UserId,
            'organization_name'   => "Relief Org 2 {$suffix}",
            'registration_number' => "REG-2-{$suffix}",
            'contact_phone'       => '+2222222222',
            'address_text'        => '456 Giving Way',
            'latitude'            => 12.9750,
            'longitude'           => 77.6000,
        ]);
        $this->ngo2Id = (int) $ngo2['id'];
        $this->ngoRepo->updateVerification($this->ngo2Id, 'verified', null, 'Auto verified for test');

        // 5. Pending NGO
        $pendingUser = $this->userRepo->create([
            'name'           => 'Pending NGO User',
            'email'          => "pending_{$suffix}@test.local",
            'password_hash'  => password_hash('PendingSecret123!', PASSWORD_DEFAULT),
            'role'           => 'ngo',
            'account_status' => 'active',
        ]);
        $this->pendingNgoUserId = (int) $pendingUser['id'];
        $pendNgo = $this->ngoRepo->createNgo([
            'user_id'             => $this->pendingNgoUserId,
            'organization_name'   => "Pending Org {$suffix}",
            'registration_number' => "PEND-{$suffix}",
            'contact_phone'       => '+3333333333',
            'address_text'        => '789 Hope St',
            'latitude'            => 12.9800,
            'longitude'           => 77.6100,
        ]);
    }

    protected function tearDown(): void
    {
        Invariants::checkAll($this->pdo);
    }

    private function createDonation(int $totalQty = 20, int $availQty = 20, ?string $status = 'active', ?string $expiresAt = null): int
    {
        $don = $this->donationRepo->create([
            'donor_id'        => $this->donorUserId,
            'category_id'     => $this->categoryId,
            'title'           => 'Test Winter Jackets',
            'description'     => 'High quality jackets',
            'condition'       => 'new',
            'total_quantity'  => $totalQty,
            'latitude'        => 12.9716,
            'longitude'       => 77.5946,
            'address_text'    => 'Secret Donor Home 101',
            'expires_at'      => $expiresAt,
            'status'          => $status,
        ]);
        $dId = (int) $don['id'];

        if ($availQty !== $totalQty || $status !== 'active') {
            $this->pdo->prepare("UPDATE donations SET available_quantity = :a, status = :s WHERE id = :id")->execute([
                ':a'  => $availQty,
                ':s'  => $status,
                ':id' => $dId,
            ]);
        }

        return $dId;
    }

    private function createRequirement(int $ngoId, int $needed = 12, int $allocated = 0, ?int $catId = null): int
    {
        $req = $this->reqRepo->create([
            'ngo_id'          => $ngoId,
            'category_id'     => $catId ?? $this->categoryId,
            'title'           => 'Jackets for Shelter',
            'description'     => 'Needed for winter',
            'quantity_needed' => $needed,
            'urgency'         => 'high',
            'min_condition'   => 'good',
            'radius_km'       => 25.0,
            'latitude'        => 12.9716,
            'longitude'       => 77.5946,
        ]);
        $rId = (int) $req['id'];

        if ($allocated > 0) {
            $this->pdo->prepare("UPDATE ngo_requirements SET quantity_allocated = :a WHERE id = :id")->execute([
                ':a'  => $allocated,
                ':id' => $rId,
            ]);
        }

        return $rId;
    }

    // ─── Milestone 9.1: Request Creation & Atomic Reservation ─────────────────

    /**
     * T-9.1-01 (P): Request 8 of 20 -> available becomes 12, partially_allocated, requirement allocated 8
     */
    public function testRequestPartialAllocationSuccess(): void
    {
        $dId = $this->createDonation(20, 20);
        $rId = $this->createRequirement($this->ngoId, 12, 0);

        $res = $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requirement_id'     => $rId,
            'requested_quantity' => 8,
            'notes'              => 'Urgent need',
        ]);

        $this->assertNotEmpty($res['id']);
        $this->assertSame('pending', $res['status']);
        $this->assertSame(8, (int) $res['requested_quantity']);
        $this->assertSame('reserved', $res['allocation_status']);

        // Check donation state in DB
        $don = $this->donationRepo->findById($dId);
        $this->assertSame(12, (int) $don['available_quantity']);
        $this->assertSame('partially_allocated', $don['status']);

        // Check requirement state in DB
        $req = $this->reqRepo->findById($rId);
        $this->assertSame(8, (int) $req['quantity_allocated']);
        $this->assertSame('active', $req['status']);
    }

    /**
     * T-9.1-02 (P): Second request for 5 from different NGO -> available becomes 7
     */
    public function testSecondRequestDifferentNgo(): void
    {
        $dId = $this->createDonation(20, 20);
        $rId1 = $this->createRequirement($this->ngoId, 12, 0);
        $rId2 = $this->createRequirement($this->ngo2Id, 10, 0);

        $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requirement_id'     => $rId1,
            'requested_quantity' => 8,
        ]);

        $this->service->createRequest($this->ngo2UserId, [
            'donation_id'        => $dId,
            'requirement_id'     => $rId2,
            'requested_quantity' => 5,
        ]);

        $don = $this->donationRepo->findById($dId);
        $this->assertSame(7, (int) $don['available_quantity']);
        $this->assertSame('partially_allocated', $don['status']);
    }

    /**
     * T-9.1-03 (N): Exceeds available quantity -> 409 INSUFFICIENT_QUANTITY
     */
    public function testRequestExceedsAvailableStockThrowsConflict(): void
    {
        $dId = $this->createDonation(5, 5);
        $rId = $this->createRequirement($this->ngoId, 12, 0);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Insufficient stock available');

        $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requirement_id'     => $rId,
            'requested_quantity' => 6,
        ]);
    }

    /**
     * T-9.1-04 (N): Exceeds outstanding need on requirement -> 409 EXCEEDS_REQUIREMENT_NEED
     */
    public function testRequestExceedsOutstandingRequirementNeed(): void
    {
        $dId = $this->createDonation(20, 20);
        $rId = $this->createRequirement($this->ngoId, 2, 0); // outstanding is 2

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('exceeds outstanding requirement need');

        $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requirement_id'     => $rId,
            'requested_quantity' => 3,
        ]);
    }

    /**
     * T-9.1-05 (V): Invalid quantities (0, negative, string, float)
     */
    public function testInvalidRequestedQuantities(): void
    {
        $dId = $this->createDonation(20, 20);

        try {
            $this->service->createRequest($this->ngoUserId, [
                'donation_id'        => $dId,
                'requested_quantity' => 0,
            ]);
            $this->fail('Expected validation failed for 0 qty');
        } catch (ValidationFailedException $e) {
            $this->assertArrayHasKey('requested_quantity', $e->getFields());
        }

        try {
            $this->service->createRequest($this->ngoUserId, [
                'donation_id'        => $dId,
                'requested_quantity' => -5,
            ]);
            $this->fail('Expected validation failed for negative qty');
        } catch (ValidationFailedException $e) {
            $this->assertArrayHasKey('requested_quantity', $e->getFields());
        }

        try {
            $this->service->createRequest($this->ngoUserId, [
                'donation_id'        => $dId,
                'requested_quantity' => 4.5,
            ]);
            $this->fail('Expected validation failed for float qty');
        } catch (ValidationFailedException $e) {
            $this->assertArrayHasKey('requested_quantity', $e->getFields());
        }
    }

    /**
     * T-9.1-06 (S): Pending/Unverified NGO blocked with 403
     */
    public function testPendingNgoCannotCreateRequest(): void
    {
        $dId = $this->createDonation(20, 20);

        $this->expectException(ForbiddenException::class);
        $this->service->createRequest($this->pendingNgoUserId, [
            'donation_id'        => $dId,
            'requested_quantity' => 2,
        ]);
    }

    /**
     * T-9.1-07 (S): Requirement of another NGO throws 403
     */
    public function testRequirementOfAnotherNgoThrowsForbidden(): void
    {
        $dId = $this->createDonation(20, 20);
        $rIdOfNgo2 = $this->createRequirement($this->ngo2Id, 10, 0);

        $this->expectException(ForbiddenException::class);
        $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requirement_id'     => $rIdOfNgo2,
            'requested_quantity' => 2,
        ]);
    }

    /**
     * T-9.1-08 (S): Donor cannot create request
     */
    public function testDonorCannotCreateRequest(): void
    {
        $dId = $this->createDonation(20, 20);

        $this->expectException(ForbiddenException::class);
        $this->service->createRequest($this->donorUserId, [
            'donation_id'        => $dId,
            'requested_quantity' => 2,
        ]);
    }

    /**
     * T-9.1-09 (N): Inactive or expired donation throws Conflict
     */
    public function testClosedOrExpiredDonationThrowsConflict(): void
    {
        $closedDid = $this->createDonation(20, 20, 'closed');
        try {
            $this->service->createRequest($this->ngoUserId, [
                'donation_id'        => $closedDid,
                'requested_quantity' => 2,
            ]);
            $this->fail('Expected conflict for closed donation');
        } catch (ConflictException $e) {
            $this->assertSame('DONATION_NOT_ACTIVE', $e->getErrorCode());
        }

        $expiredDid = $this->createDonation(20, 20, 'active', date('c', time() - 7200));
        try {
            $this->service->createRequest($this->ngoUserId, [
                'donation_id'        => $expiredDid,
                'requested_quantity' => 2,
            ]);
            $this->fail('Expected conflict for expired donation');
        } catch (ConflictException $e) {
            $this->assertSame('DONATION_EXPIRED', $e->getErrorCode());
        }
    }

    /**
     * T-9.1-10 (E): Take exact remainder -> status fully_allocated, available 0
     */
    public function testTakeExactRemainderBecomesFullyAllocated(): void
    {
        $dId = $this->createDonation(7, 7);

        $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requested_quantity' => 7,
        ]);

        $don = $this->donationRepo->findById($dId);
        $this->assertSame(0, (int) $don['available_quantity']);
        $this->assertSame('fully_allocated', $don['status']);
    }

    /**
     * T-9.1-11 (E): Idempotent retry with same Idempotency-Key
     */
    public function testIdempotentRetryReturnsOriginalRequest(): void
    {
        $dId = $this->createDonation(20, 20);
        $key = 'idemp-' . bin2hex(random_bytes(6));

        $req1 = $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requested_quantity' => 5,
        ], $key);

        $req2 = $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requested_quantity' => 5,
        ], $key);

        $this->assertSame($req1['id'], $req2['id']);

        // Assert stock reserved only once (15 left, not 10)
        $don = $this->donationRepo->findById($dId);
        $this->assertSame(15, (int) $don['available_quantity']);
    }

    /**
     * T-9.1-12 (N): Duplicate pending request for same tuple throws 409
     */
    public function testDuplicatePendingRequestThrowsConflict(): void
    {
        $dId = $this->createDonation(20, 20);
        $rId = $this->createRequirement($this->ngoId, 10, 0);

        $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requirement_id'     => $rId,
            'requested_quantity' => 3,
        ]);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('already have a pending request');

        $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requirement_id'     => $rId,
            'requested_quantity' => 2,
        ]);
    }

    /**
     * T-9.1-14 (I) & T-9.1-15 (S): List & Show visibility per role
     */
    public function testRequestVisibilityAndAccessControl(): void
    {
        $dId = $this->createDonation(20, 20);
        $req = $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requested_quantity' => 4,
        ]);

        $reqId = (int) $req['id'];

        // Donor sees request
        $donorList = $this->service->listRequests($this->donorUserId, 'donor');
        $this->assertCount(1, $donorList['items']);
        $this->assertSame($reqId, (int) $donorList['items'][0]['id']);

        // Owning NGO sees request
        $ngoList = $this->service->listRequests($this->ngoUserId, 'ngo');
        $this->assertCount(1, $ngoList['items']);

        // NGO 2 tries to GET request -> 404
        $this->expectException(NotFoundException::class);
        $this->service->getRequest($this->ngo2UserId, 'ngo', $reqId);
    }

    // ─── Milestone 9.2: Accept, Reject, Cancel & Expire ───────────────────────

    /**
     * T-9.2-01 (P): Donor accepts pending request
     */
    public function testDonorAcceptsRequest(): void
    {
        $dId = $this->createDonation(20, 20);
        $req = $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requested_quantity' => 8,
        ]);
        $reqId = (int) $req['id'];

        $res = $this->service->acceptRequest($this->donorUserId, $reqId);
        $this->assertSame('accepted', $res['status']);
        $this->assertSame('confirmed', $res['allocation_status']);
    }

    /**
     * T-9.2-02 (P): Donor rejects pending request -> returns stock
     */
    public function testDonorRejectsRequestReturnsStock(): void
    {
        $dId = $this->createDonation(20, 20);
        $rId = $this->createRequirement($this->ngoId, 12, 0);

        $req = $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requirement_id'     => $rId,
            'requested_quantity' => 8,
        ]);
        $reqId = (int) $req['id'];

        $this->assertSame(12, (int) $this->donationRepo->findById($dId)['available_quantity']);
        $this->assertSame(8, (int) $this->reqRepo->findById($rId)['quantity_allocated']);

        $res = $this->service->rejectRequest($this->donorUserId, $reqId, 'Items no longer available');
        $this->assertSame('rejected', $res['status']);
        $this->assertSame('cancelled', $res['allocation_status']);

        // Check returned stock and status re-derivation
        $don = $this->donationRepo->findById($dId);
        $this->assertSame(20, (int) $don['available_quantity']);
        $this->assertSame('active', $don['status']);

        $requirement = $this->reqRepo->findById($rId);
        $this->assertSame(0, (int) $requirement['quantity_allocated']);
        $this->assertSame('active', $requirement['status']);
    }

    /**
     * T-9.2-03 (S) & T-9.2-04 (S): Unauthorized user cannot accept request
     */
    public function testUnauthorizedUserCannotAcceptRequest(): void
    {
        $dId = $this->createDonation(20, 20);
        $req = $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requested_quantity' => 5,
        ]);
        $reqId = (int) $req['id'];

        // NGO tries to accept -> forbidden
        try {
            $this->service->acceptRequest($this->ngoUserId, $reqId);
            $this->fail('NGO cannot accept request');
        } catch (ForbiddenException $e) {
            $this->assertSame('FORBIDDEN_DONOR', $e->getErrorCode());
        }
    }

    /**
     * T-9.2-05 (N): Accept after reject throws 409 INVALID_TRANSITION
     */
    public function testAcceptAfterRejectThrowsConflict(): void
    {
        $dId = $this->createDonation(20, 20);
        $req = $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requested_quantity' => 5,
        ]);
        $reqId = (int) $req['id'];

        $this->service->rejectRequest($this->donorUserId, $reqId);

        try {
            $this->service->acceptRequest($this->donorUserId, $reqId);
            $this->fail('Expected conflict when accepting rejected request');
        } catch (ConflictException $e) {
            $this->assertSame('INVALID_TRANSITION', $e->getErrorCode());
        }
    }

    /**
     * T-9.2-06 (E): Double accept is idempotent and does not change stock
     */
    public function testDoubleAcceptIsIdempotent(): void
    {
        $dId = $this->createDonation(20, 20);
        $req = $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requested_quantity' => 5,
        ]);
        $reqId = (int) $req['id'];

        $this->service->acceptRequest($this->donorUserId, $reqId);
        $res = $this->service->acceptRequest($this->donorUserId, $reqId);

        $this->assertSame('accepted', $res['status']);
        $this->assertSame(15, (int) $this->donationRepo->findById($dId)['available_quantity']);
    }

    /**
     * T-9.2-07 (P) & T-9.2-08 (P): NGO cancels pending or accepted request -> returns stock
     */
    public function testNgoCancelsRequestReturnsStock(): void
    {
        // 1. Cancel pending
        $dId = $this->createDonation(20, 20);
        $req1 = $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requested_quantity' => 5,
        ]);
        $this->service->cancelRequest($this->ngoUserId, (int) $req1['id'], 'Plans changed');
        $this->assertSame(20, (int) $this->donationRepo->findById($dId)['available_quantity']);

        // 2. Cancel accepted
        $req2 = $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requested_quantity' => 8,
        ]);
        $this->service->acceptRequest($this->donorUserId, (int) $req2['id']);
        $this->service->cancelRequest($this->ngoUserId, (int) $req2['id'], 'No transport available');
        $this->assertSame(20, (int) $this->donationRepo->findById($dId)['available_quantity']);
    }

    /**
     * T-9.2-10 (P) & T-9.2-11 (E): Expiry job returns stock and is idempotent
     */
    public function testExpiryJobReleasesStockAndIsIdempotent(): void
    {
        $dId = $this->createDonation(20, 20);
        $req = $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requested_quantity' => 6,
        ]);
        $reqId = (int) $req['id'];
        $this->assertSame(14, (int) $this->donationRepo->findById($dId)['available_quantity']);

        // Set expires_at to 73 hours ago
        $this->pdo->prepare("
            UPDATE donation_requests
            SET expires_at = NOW() - INTERVAL '73 hours'
            WHERE id = :id
        ")->execute([':id' => $reqId]);

        // Run expiry job
        $expiredCount = $this->service->expirePendingRequests();
        $this->assertGreaterThanOrEqual(1, $expiredCount);

        $reqAfter = $this->service->getRequest($this->donorUserId, 'donor', $reqId);
        $this->assertSame('expired', $reqAfter['status']);
        $this->assertSame('cancelled', $reqAfter['allocation_status']);
        $this->assertSame(20, (int) $this->donationRepo->findById($dId)['available_quantity']);

        // Run again -> 0 expired, stock unchanged
        $expiredAgain = $this->service->expirePendingRequests();
        $this->assertSame(0, $expiredAgain);
        $this->assertSame(20, (int) $this->donationRepo->findById($dId)['available_quantity']);
    }

    /**
     * T-9.2-12 (I): Returned stock is immediately re-requestable by another NGO
     */
    public function testReturnedStockIsReRequestable(): void
    {
        $dId = $this->createDonation(10, 10);
        $req1 = $this->service->createRequest($this->ngoUserId, [
            'donation_id'        => $dId,
            'requested_quantity' => 10,
        ]);

        // Stock is now 0
        $this->assertSame(0, (int) $this->donationRepo->findById($dId)['available_quantity']);

        // NGO 2 cannot request
        try {
            $this->service->createRequest($this->ngo2UserId, [
                'donation_id'        => $dId,
                'requested_quantity' => 5,
            ]);
            $this->fail('Expected out of stock error');
        } catch (ConflictException $e) {
            $this->assertSame('DONATION_NOT_ACTIVE', $e->getErrorCode());
        }

        // Donor rejects request 1
        $this->service->rejectRequest($this->donorUserId, (int) $req1['id']);

        // Stock returned to 10 and active
        $this->assertSame(10, (int) $this->donationRepo->findById($dId)['available_quantity']);

        // NGO 2 can now request successfully
        $req2 = $this->service->createRequest($this->ngo2UserId, [
            'donation_id'        => $dId,
            'requested_quantity' => 5,
        ]);
        $this->assertSame('pending', $req2['status']);
        $this->assertSame(5, (int) $this->donationRepo->findById($dId)['available_quantity']);
    }
}
