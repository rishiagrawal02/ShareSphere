<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\TooManyRequestsException;
use App\Http\Exceptions\ValidationFailedException;
use App\Repositories\AllocationRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\DonationRepository;
use App\Repositories\NgoRepository;
use App\Repositories\PickupRepository;
use App\Repositories\RequirementRepository;
use App\Repositories\UserRepository;
use App\Services\AllocationService;
use App\Services\OtpService;
use App\Services\PickupService;
use App\Support\Config;
use App\Support\Database;
use App\Support\PickupStateMachine;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Phase 10 – Pickup, OTP & Completion integration tests.
 *
 * T-10.1-xx  Pickup scheduling lifecycle (M10.1)
 * T-10.2-xx  OTP issuance & verification (M10.2)
 * T-10.3-xx  Receipt confirmation & accounting (M10.3)
 */
class PickupLifecycleTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private NgoRepository $ngoRepo;
    private CategoryRepository $catRepo;
    private DonationRepository $donationRepo;
    private RequirementRepository $reqRepo;
    private AllocationRepository $allocationRepo;
    private PickupRepository $pickupRepo;
    private AllocationService $allocationSvc;
    private PickupService $pickupSvc;
    private OtpService $otpSvc;

    /* ── fixture IDs ─────────────────────────────────────────────── */
    private int $donorId;
    private int $ngoUserId;
    private int $ngo2UserId;
    private int $ngoId;
    private int $ngo2Id;
    private int $categoryId;

    // ── shared allocation ID built once per suite ─────────────────
    /** Fully-confirmed allocation ready for pickup scheduling. */
    private int $confirmedAllocationId;
    /** donation_id for the above allocation */
    private int $donationId;
    /** requirement_id for the above allocation */
    private int $requirementId;

    // ─────────────────────────────────────────────────────────────
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $rootPath = dirname(__DIR__, 2);
        Config::load($rootPath);

        $this->pdo = Database::getOwnerConnection();
        // Wipe state between tests to keep isolation
        $this->pdo->exec("
            TRUNCATE TABLE pickups, allocations, donation_requests,
                           donations, ngo_requirements, ngos,
                           categories, category_compatibility,
                           users, notifications, audit_logs,
                           email_outbox
            CASCADE
        ");

        $this->userRepo      = new UserRepository($this->pdo);
        $this->ngoRepo       = new NgoRepository($this->pdo);
        $this->catRepo       = new CategoryRepository($this->pdo);
        $this->donationRepo  = new DonationRepository($this->pdo);
        $this->reqRepo       = new RequirementRepository($this->pdo);
        $this->allocationRepo = new AllocationRepository($this->pdo);
        $this->pickupRepo    = new PickupRepository($this->pdo);
        $this->allocationSvc = new AllocationService($this->pdo);
        $this->pickupSvc     = new PickupService($this->pdo);
        $this->otpSvc        = new OtpService($this->pdo, null, null, null, null, null, 'test-hmac-key');

        $this->buildFixtures();
    }

    // ─── Fixture helpers ──────────────────────────────────────────

    private function buildFixtures(): void
    {
        $sfx = bin2hex(random_bytes(3));

        // Users
        $donor = $this->userRepo->create([
            'name'          => "Donor {$sfx}",
            'email'         => "donor_{$sfx}@test.local",
            'password_hash' => password_hash('Secure!123', PASSWORD_DEFAULT),
            'role'          => 'donor',
            'status'        => 'active',
        ]);
        $this->donorId = (int) $donor['id'];

        $ngoU = $this->userRepo->create([
            'name'          => "NGO User {$sfx}",
            'email'         => "ngo_{$sfx}@test.local",
            'password_hash' => password_hash('Secure!123', PASSWORD_DEFAULT),
            'role'          => 'ngo',
            'status'        => 'active',
        ]);
        $this->ngoUserId = (int) $ngoU['id'];

        $ngo2U = $this->userRepo->create([
            'name'          => "NGO2 User {$sfx}",
            'email'         => "ngo2_{$sfx}@test.local",
            'password_hash' => password_hash('Secure!123', PASSWORD_DEFAULT),
            'role'          => 'ngo',
            'status'        => 'active',
        ]);
        $this->ngo2UserId = (int) $ngo2U['id'];

        // NGOs
        $ngo = $this->ngoRepo->createNgo([
            'user_id'             => $this->ngoUserId,
            'organization_name'   => "NGO Org {$sfx}",
            'registration_number' => "REG-NGO-{$sfx}",
            'contact_phone'       => '+1111111111',
            'description'         => 'test',
            'address_text'        => '123 Street',
            'latitude'            => 40.7128,
            'longitude'           => -74.0060,
        ]);
        $this->ngoId = (int) $ngo['id'];
        $this->ngoRepo->updateVerification($this->ngoId, 'verified', null, 'Test verified');

        $ngo2 = $this->ngoRepo->createNgo([
            'user_id'             => $this->ngo2UserId,
            'organization_name'   => "NGO2 Org {$sfx}",
            'registration_number' => "REG-NGO2-{$sfx}",
            'contact_phone'       => '+2222222222',
            'description'         => 'test2',
            'address_text'        => '456 Ave',
            'latitude'            => 40.7580,
            'longitude'           => -73.9855,
        ]);
        $this->ngo2Id = (int) $ngo2['id'];
        $this->ngoRepo->updateVerification($this->ngo2Id, 'verified', null, 'Test verified');

        // Category
        $cat = $this->catRepo->create("Electronics {$sfx}", 'Electronic items');
        $this->categoryId = (int) $cat['id'];

        // Donation (exact coords intentionally different from public)
        $don = $this->donationRepo->create([
            'donor_id'    => $this->donorId,
            'category_id' => $this->categoryId,
            'title'       => "Laptop {$sfx}",
            'description' => 'Working laptop',
            'total_quantity' => 5,
            'condition'   => 'good',
            'latitude'    => 40.7128,
            'longitude'   => -74.0060,
            'address_text' => '1600 Broadway, New York',
        ]);
        $this->donationId = (int) $don['id'];

        // Requirement
        $req = $this->reqRepo->create([
            'ngo_id'          => $this->ngoId,
            'category_id'     => $this->categoryId,
            'title'           => "Need laptops {$sfx}",
            'description'     => 'We need laptops',
            'quantity_needed' => 5,
            'latitude'        => 40.7128,
            'longitude'       => -74.0060,
        ]);
        $this->requirementId = (int) $req['id'];

        // Allocation: request → confirm → produces confirmedAllocationId
        $this->confirmedAllocationId = $this->buildConfirmedAllocation();
    }

    /**
     * Walk the allocation state machine to 'confirmed' so a pickup can be proposed.
     */
    private function buildConfirmedAllocation(): int
    {
        // NGO submits a request
        $request = $this->allocationSvc->createRequest(
            $this->ngoUserId,
            [
                'donation_id'        => $this->donationId,
                'requirement_id'     => $this->requirementId,
                'requested_quantity' => 3,
                'notes'              => 'Please help',
            ]
        );
        $requestId = (int) $request['id'];

        // Donor accepts
        $this->allocationSvc->acceptRequest($this->donorId, $requestId);

        // Fetch the resulting allocation
        $stmt = $this->pdo->prepare("SELECT id FROM allocations WHERE request_id = :rid AND status = 'confirmed' LIMIT 1");
        $stmt->execute([':rid' => $requestId]);
        $alloc = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($alloc, 'Confirmed allocation must exist after accept+confirm');
        return (int) $alloc['id'];
    }

    /** Returns a valid scheduled_at string 2 hours from now */
    private function futureTs(int $offsetSeconds = 7200): string
    {
        return date('Y-m-d H:i:s', time() + $offsetSeconds);
    }

    /** Force a pickup into a specific state via direct DB update (helper). */
    private function forcePickupState(int $pickupId, string $state): void
    {
        $this->pdo->prepare("UPDATE pickups SET state = :s WHERE id = :id")
            ->execute([':s' => $state, ':id' => $pickupId]);
    }

    // ══════════════════════════════════════════════════════════════
    // T-10.1  Pickup scheduling lifecycle
    // ══════════════════════════════════════════════════════════════

    /** T-10.1-01: Donor can propose a pickup on a confirmed allocation. */
    public function testDonorCanProposePickup(): void
    {
        $result = $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id'    => $this->confirmedAllocationId,
            'scheduled_at'     => $this->futureTs(),
            'location_details' => 'Meet at lobby',
            'contact_note'     => 'Call before arriving',
        ]);

        $this->assertSame('proposed', $result['state']);
        $this->assertSame($this->donorId, $result['proposed_by']);
        $this->assertSame($this->confirmedAllocationId, $result['allocation_id']);
        $this->assertSame('Meet at lobby', $result['location_details']);
    }

    /** T-10.1-02: NGO can also propose a pickup on a confirmed allocation. */
    public function testNgoCanProposePickup(): void
    {
        $result = $this->pickupSvc->proposePickup($this->ngoUserId, 'ngo', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);

        $this->assertSame('proposed', $result['state']);
        $this->assertSame($this->ngoUserId, $result['proposed_by']);
    }

    /** T-10.1-03: Cannot propose pickup if allocation is not confirmed. */
    public function testCannotProposePickupForNonConfirmedAllocation(): void
    {
        // Create a request that is still pending (not accepted yet)
        $req2 = $this->reqRepo->create([
            'ngo_id'          => $this->ngoId,
            'category_id'     => $this->categoryId,
            'title'           => 'Extra req',
            'description'     => 'test',
            'quantity_needed' => 1,
            'latitude'        => 40.7128,
            'longitude'       => -74.0060,
        ]);

        // Create a second donation
        $don2 = $this->donationRepo->create([
            'donor_id'       => $this->donorId,
            'category_id'    => $this->categoryId,
            'title'          => 'Extra donation',
            'description'    => 'test',
            'total_quantity' => 1,
            'condition'      => 'good',
            'latitude'       => 40.0,
            'longitude'      => -74.0,
            'address_text'   => '100 Test St',
        ]);

        // Create allocation in 'reserved' state (after submit)
        $request2 = $this->allocationSvc->createRequest(
            $this->ngoUserId,
            ['donation_id' => (int) $don2['id'], 'requirement_id' => (int) $req2['id'], 'requested_quantity' => 1]
        );

        $stmt = $this->pdo->prepare("SELECT id FROM allocations WHERE request_id = :rid LIMIT 1");
        $stmt->execute([':rid' => $request2['id']]);
        $alloc2 = $stmt->fetch(PDO::FETCH_ASSOC);
        $allocationId2 = (int) $alloc2['id'];

        // Force to 'reserved' (not confirmed)
        $this->pdo->prepare("UPDATE allocations SET status = 'reserved' WHERE id = :id")
            ->execute([':id' => $allocationId2]);

        $this->expectException(ConflictException::class);
        $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $allocationId2,
            'scheduled_at'  => $this->futureTs(),
        ]);
    }

    /** T-10.1-04: Proposing with scheduled_at < 1 hour from now is rejected. */
    public function testProposePickupRejectsScheduledAtTooSoon(): void
    {
        $this->expectException(ValidationFailedException::class);
        $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => date('Y-m-d H:i:s', time() + 30), // 30 seconds
        ]);
    }

    /** T-10.1-05: Proposing with scheduled_at > 60 days is rejected. */
    public function testProposePickupRejectsScheduledAtTooFar(): void
    {
        $this->expectException(ValidationFailedException::class);
        $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => date('Y-m-d H:i:s', time() + (61 * 86400)),
        ]);
    }

    /** T-10.1-06: Unrelated user cannot propose pickup (FORBIDDEN). */
    public function testUnrelatedUserCannotProposePickup(): void
    {
        $stranger = $this->userRepo->create([
            'name'          => 'Stranger',
            'email'         => 'stranger_' . bin2hex(random_bytes(4)) . '@test.local',
            'password_hash' => password_hash('pass', PASSWORD_DEFAULT),
            'role'          => 'donor',
            'status'        => 'active',
        ]);
        $this->expectException(ForbiddenException::class);
        $this->pickupSvc->proposePickup((int) $stranger['id'], 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);
    }

    /** T-10.1-07: Proposer cannot confirm their own proposal. */
    public function testProposerCannotConfirmOwnPickup(): void
    {
        $p = $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);

        $this->expectException(ConflictException::class);
        $this->pickupSvc->confirmPickup($this->donorId, 'donor', $p['id']);
    }

    /** T-10.1-08: Other party can confirm the proposal → state becomes 'scheduled'. */
    public function testOtherPartyCanConfirmPickup(): void
    {
        $p = $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);

        $result = $this->pickupSvc->confirmPickup($this->ngoUserId, 'ngo', $p['id']);
        $this->assertSame('scheduled', $result['state']);
    }

    /** T-10.1-09: Rescheduling resets state to 'proposed' and invalidates OTP. */
    public function testRescheduleResetsStateAndInvalidatesOtp(): void
    {
        $p = $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);
        // Confirm so state = scheduled
        $this->pickupSvc->confirmPickup($this->ngoUserId, 'ngo', $p['id']);

        // NGO reschedules
        $result = $this->pickupSvc->reschedulePickup($this->ngoUserId, 'ngo', $p['id'], [
            'scheduled_at' => $this->futureTs(7201),
        ]);
        $this->assertSame('proposed', $result['state']);
        $this->assertNull($result['otp_expires_at']);
    }

    /** T-10.1-10: Cannot reschedule a collected/completed pickup. */
    public function testCannotRescheduleTerminalPickup(): void
    {
        $p = $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);
        $this->forcePickupState($p['id'], 'collected');

        $this->expectException(ConflictException::class);
        $this->pickupSvc->reschedulePickup($this->donorId, 'donor', $p['id'], [
            'scheduled_at' => $this->futureTs(7201),
        ]);
    }

    /** T-10.1-11: Cancel a proposed pickup → state becomes 'cancelled'. */
    public function testCancelProposedPickup(): void
    {
        $p = $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);
        $result = $this->pickupSvc->cancelPickup($this->donorId, 'donor', $p['id'], 'Changed plans');
        $this->assertSame('cancelled', $result['state']);
    }

    /** T-10.1-12: Cannot cancel a completed pickup. */
    public function testCannotCancelCompletedPickup(): void
    {
        $p = $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);
        $this->forcePickupState($p['id'], 'completed');

        $this->expectException(ConflictException::class);
        $this->pickupSvc->cancelPickup($this->donorId, 'donor', $p['id']);
    }

    /** T-10.1-13: Duplicate active pickup for same allocation is rejected. */
    public function testDuplicateActivePickupRejected(): void
    {
        $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);

        $this->expectException(ConflictException::class);
        $this->pickupSvc->proposePickup($this->ngoUserId, 'ngo', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(7201),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // Privacy tests
    // ══════════════════════════════════════════════════════════════

    /** T-10.1-LP-01: NGO sees only public coordinates in 'proposed' state. */
    public function testNgoSeeOnlyPublicCoordsWhenProposed(): void
    {
        $p = $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);

        $pickup = $this->pickupSvc->getPickup($this->ngoUserId, 'ngo', $p['id']);
        $this->assertArrayNotHasKey('address_text', $pickup['donation']);
        $this->assertArrayNotHasKey('latitude_exact', $pickup['donation']);
        $this->assertArrayNotHasKey('longitude_exact', $pickup['donation']);
        $this->assertArrayHasKey('latitude_public', $pickup['donation']);
    }

    /** T-10.1-LP-02: NGO sees exact location once pickup is 'scheduled'. */
    public function testNgoSeeExactCoordsWhenScheduled(): void
    {
        $p = $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);
        $this->pickupSvc->confirmPickup($this->ngoUserId, 'ngo', $p['id']);

        $pickup = $this->pickupSvc->getPickup($this->ngoUserId, 'ngo', $p['id']);
        $this->assertArrayHasKey('address_text', $pickup['donation']);
        $this->assertArrayHasKey('latitude_exact', $pickup['donation']);
    }

    /** T-10.1-LP-03: Donor always sees exact location. */
    public function testDonorAlwaysSeeExactCoords(): void
    {
        $p = $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);

        $pickup = $this->pickupSvc->getPickup($this->donorId, 'donor', $p['id']);
        $this->assertArrayHasKey('address_text', $pickup['donation']);
        $this->assertArrayHasKey('latitude_exact', $pickup['donation']);
    }

    // ══════════════════════════════════════════════════════════════
    // T-10.2  OTP issuance & verification
    // ══════════════════════════════════════════════════════════════

    private function buildScheduledPickup(): int
    {
        $p = $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);
        $this->pickupSvc->confirmPickup($this->ngoUserId, 'ngo', $p['id']);
        return $p['id'];
    }

    /** T-10.2-01: Non-NGO user cannot issue OTP. */
    public function testDonorCannotIssueOtp(): void
    {
        $pickupId = $this->buildScheduledPickup();
        $this->expectException(ForbiddenException::class);
        $this->otpSvc->issue($pickupId, $this->donorId);
    }

    /** T-10.2-02: OTP cannot be issued more than 24h before scheduled time. */
    public function testOtpCannotBeIssuedTooEarly(): void
    {
        // Schedule 48h from now → OTP issue window not open yet
        $p = $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(172800), // 48h
        ]);
        $this->pickupSvc->confirmPickup($this->ngoUserId, 'ngo', $p['id']);

        $this->expectException(ConflictException::class);
        // Pass a fake "now" that is 49h before scheduled (no window)
        $this->otpSvc->issue($p['id'], $this->ngoUserId, time()); // too early
    }

    /** T-10.2-03: OTP issued in valid window returns otp_issued status. */
    public function testOtpIssuedWithinWindowSucceeds(): void
    {
        $pickupId = $this->buildScheduledPickup();

        // Simulate "now" = scheduled_at - 1h (inside 24h window)
        $stmt = $this->pdo->prepare("SELECT scheduled_at FROM pickups WHERE id = :id");
        $stmt->execute([':id' => $pickupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $scheduledTs = strtotime($row['scheduled_at']);
        $fakeNow = $scheduledTs - 3600; // 1 hour before scheduled

        $result = $this->otpSvc->issue($pickupId, $this->ngoUserId, $fakeNow);

        $this->assertSame('otp_issued', $result['status']);
        $this->assertNotEmpty($result['expires_at']);

        // pickup state must be otp_issued
        $p = $this->pickupRepo->findById($pickupId);
        $this->assertSame('otp_issued', $p['state']);
        // HMAC must be stored; plaintext not
        $this->assertNotEmpty($p['otp_hmac']);
    }

    /** T-10.2-04: Plaintext OTP is never stored in DB. */
    public function testOtpPlaintextNotStoredInDb(): void
    {
        $pickupId = $this->buildScheduledPickup();
        $stmt = $this->pdo->prepare("SELECT scheduled_at FROM pickups WHERE id = :id");
        $stmt->execute([':id' => $pickupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $fakeNow = strtotime($row['scheduled_at']) - 3600;

        $this->otpSvc->issue($pickupId, $this->ngoUserId, $fakeNow);

        // Fetch raw DB row and verify no column holds a 6-digit numeric string
        $stmt2 = $this->pdo->prepare("SELECT * FROM pickups WHERE id = :id");
        $stmt2->execute([':id' => $pickupId]);
        $raw = $stmt2->fetch(PDO::FETCH_ASSOC);

        foreach ($raw as $col => $val) {
            if ($col === 'otp_hmac') {
                continue; // hmac is ok
            }
            $this->assertDoesNotMatchRegularExpression(
                '/^\d{6}$/',
                (string) $val,
                "Plaintext 6-digit OTP should not appear in column '{$col}'"
            );
        }
    }

    /** T-10.2-05: Rate limit – 4th OTP issue in 24h is rejected. */
    public function testOtpRateLimitAfterThreeIssues(): void
    {
        $pickupId = $this->buildScheduledPickup();
        $stmt = $this->pdo->prepare("SELECT scheduled_at FROM pickups WHERE id = :id");
        $stmt->execute([':id' => $pickupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $fakeNow = strtotime($row['scheduled_at']) - 3600;

        // Issue 3 times (max allowed)
        for ($i = 0; $i < 3; $i++) {
            $this->otpSvc->issue($pickupId, $this->ngoUserId, $fakeNow);
        }

        $this->expectException(TooManyRequestsException::class);
        $this->otpSvc->issue($pickupId, $this->ngoUserId, $fakeNow);
    }

    /** T-10.2-06: Donor can verify a correct OTP → state becomes 'collected'. */
    public function testDonorCanVerifyCorrectOtp(): void
    {
        $pickupId = $this->buildScheduledPickup();
        $stmt = $this->pdo->prepare("SELECT scheduled_at FROM pickups WHERE id = :id");
        $stmt->execute([':id' => $pickupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $fakeNow = strtotime($row['scheduled_at']) - 3600;

        $this->otpSvc->issue($pickupId, $this->ngoUserId, $fakeNow);

        // Extract HMAC, reverse-engineer OTP by brute-force scan (testing only)
        $rawPickup = $this->pickupRepo->findById($pickupId);
        $hmacKey   = 'test-hmac-key';
        $storedHmac = $rawPickup['otp_hmac'];
        $foundOtp = null;
        for ($code = 0; $code <= 999999; $code++) {
            $candidate = str_pad((string) $code, 6, '0', STR_PAD_LEFT);
            $computed  = hash_hmac('sha256', $pickupId . '|' . $candidate, $hmacKey);
            if (hash_equals($storedHmac, $computed)) {
                $foundOtp = $candidate;
                break;
            }
        }
        $this->assertNotNull($foundOtp, 'Test must be able to brute-force the OTP for verification');

        $result = $this->otpSvc->verify($pickupId, $this->donorId, $foundOtp, $fakeNow + 1);
        $this->assertSame('collected', $result['status']);

        // pickup state = collected
        $p = $this->pickupRepo->findById($pickupId);
        $this->assertSame('collected', $p['state']);
        $this->assertNull($p['otp_hmac']); // cleared on success
    }

    /** T-10.2-07: Wrong OTP increments attempt counter. */
    public function testWrongOtpIncrementsAttempts(): void
    {
        $pickupId = $this->buildScheduledPickup();
        $stmt = $this->pdo->prepare("SELECT scheduled_at FROM pickups WHERE id = :id");
        $stmt->execute([':id' => $pickupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $fakeNow = strtotime($row['scheduled_at']) - 3600;
        $this->otpSvc->issue($pickupId, $this->ngoUserId, $fakeNow);

        try {
            $this->otpSvc->verify($pickupId, $this->donorId, '000000', $fakeNow + 1);
        } catch (ValidationFailedException $e) {
            // expected
        }

        $p = $this->pickupRepo->findById($pickupId);
        $this->assertSame(1, (int) $p['otp_attempts']);
        $this->assertFalse((bool) $p['otp_locked']);
    }

    /** T-10.2-08: Five wrong OTPs lock the pickup. */
    public function testFiveWrongOtpsLockPickup(): void
    {
        $pickupId = $this->buildScheduledPickup();
        $stmt = $this->pdo->prepare("SELECT scheduled_at FROM pickups WHERE id = :id");
        $stmt->execute([':id' => $pickupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $fakeNow = strtotime($row['scheduled_at']) - 3600;
        $this->otpSvc->issue($pickupId, $this->ngoUserId, $fakeNow);

        for ($i = 0; $i < 5; $i++) {
            try {
                $this->otpSvc->verify($pickupId, $this->donorId, '000000', $fakeNow + 1);
            } catch (ValidationFailedException $e) {
                // expected
            }
        }

        $p = $this->pickupRepo->findById($pickupId);
        $this->assertTrue((bool) $p['otp_locked'], 'Pickup must be locked after 5 failed attempts');
        $this->assertSame(5, (int) $p['otp_attempts']);
    }

    /** T-10.2-09: Locked pickup rejects further verify attempts immediately. */
    public function testLockedPickupRejectsVerify(): void
    {
        $pickupId = $this->buildScheduledPickup();
        $stmt = $this->pdo->prepare("SELECT scheduled_at FROM pickups WHERE id = :id");
        $stmt->execute([':id' => $pickupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $fakeNow = strtotime($row['scheduled_at']) - 3600;
        $this->otpSvc->issue($pickupId, $this->ngoUserId, $fakeNow);

        // Lock it via 5 wrong attempts
        for ($i = 0; $i < 5; $i++) {
            try { $this->otpSvc->verify($pickupId, $this->donorId, '000000', $fakeNow + 1); } catch (\Exception $e) {}
        }

        $this->expectException(ValidationFailedException::class);
        $this->otpSvc->verify($pickupId, $this->donorId, '123456', $fakeNow + 2);
    }

    /** T-10.2-10: Expired OTP is rejected (even if code matches). */
    public function testExpiredOtpIsRejected(): void
    {
        $pickupId = $this->buildScheduledPickup();
        $stmt = $this->pdo->prepare("SELECT scheduled_at FROM pickups WHERE id = :id");
        $stmt->execute([':id' => $pickupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $fakeNow = strtotime($row['scheduled_at']) - 3600;
        $this->otpSvc->issue($pickupId, $this->ngoUserId, $fakeNow);

        // Try to verify 31 minutes later (TTL = 30min)
        $this->expectException(ValidationFailedException::class);
        $this->otpSvc->verify($pickupId, $this->donorId, '000000', $fakeNow + (31 * 60));
    }

    /** T-10.2-11: Non-donor cannot verify OTP. */
    public function testNgoCannotVerifyOtp(): void
    {
        $pickupId = $this->buildScheduledPickup();
        $stmt = $this->pdo->prepare("SELECT scheduled_at FROM pickups WHERE id = :id");
        $stmt->execute([':id' => $pickupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $fakeNow = strtotime($row['scheduled_at']) - 3600;
        $this->otpSvc->issue($pickupId, $this->ngoUserId, $fakeNow);

        $this->expectException(ForbiddenException::class);
        $this->otpSvc->verify($pickupId, $this->ngoUserId, '123456', $fakeNow + 60);
    }

    /** T-10.2-12: OTP cannot be issued for pickup not in scheduled/otp_issued state. */
    public function testOtpIssueInvalidStateRejected(): void
    {
        $p = $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);
        // Still 'proposed', not yet confirmed
        $this->expectException(ConflictException::class);

        $stmt = $this->pdo->prepare("SELECT scheduled_at FROM pickups WHERE id = :id");
        $stmt->execute([':id' => $p['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $fakeNow = strtotime($row['scheduled_at']) - 3600;
        $this->otpSvc->issue($p['id'], $this->ngoUserId, $fakeNow);
    }

    // ══════════════════════════════════════════════════════════════
    // T-10.3  Receipt confirmation & accounting
    // ══════════════════════════════════════════════════════════════

    /**
     * Build a pickup through to 'collected' state.
     * Returns [pickupId, requirementId, allocationId, donationId].
     */
    private function buildCollectedPickup(): array
    {
        $pickupId = $this->buildScheduledPickup();

        // Issue OTP inside 24h window
        $stmt = $this->pdo->prepare("SELECT scheduled_at FROM pickups WHERE id = :id");
        $stmt->execute([':id' => $pickupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $fakeNow = strtotime($row['scheduled_at']) - 3600;
        $this->otpSvc->issue($pickupId, $this->ngoUserId, $fakeNow);

        // Brute-force OTP from HMAC
        $rawPickup = $this->pickupRepo->findById($pickupId);
        $storedHmac = $rawPickup['otp_hmac'];
        $foundOtp = null;
        for ($code = 0; $code <= 999999; $code++) {
            $candidate = str_pad((string) $code, 6, '0', STR_PAD_LEFT);
            if (hash_equals($storedHmac, hash_hmac('sha256', $pickupId . '|' . $candidate, 'test-hmac-key'))) {
                $foundOtp = $candidate;
                break;
            }
        }
        $this->assertNotNull($foundOtp);
        $this->otpSvc->verify($pickupId, $this->donorId, $foundOtp, $fakeNow + 1);

        $pickup = $this->pickupRepo->findById($pickupId);
        return [
            $pickupId,
            (int) $pickup['requirement_id'],
            (int) $pickup['allocation_id'],
            (int) $pickup['donation_id'],
        ];
    }

    /** T-10.3-01: NGO confirms receipt → pickup moves to 'completed'. */
    public function testConfirmReceiptCompletesPickup(): void
    {
        [$pickupId] = $this->buildCollectedPickup();

        $result = $this->pickupSvc->confirmReceipt($this->ngoUserId, 'ngo', $pickupId);
        $this->assertSame('completed', $result['state']);

        $p = $this->pickupRepo->findById($pickupId);
        $this->assertSame('completed', $p['state']);
        $this->assertNotNull($p['completed_at']);
    }

    /** T-10.3-02: Donor cannot confirm receipt (NGO-only action). */
    public function testDonorCannotConfirmReceipt(): void
    {
        [$pickupId] = $this->buildCollectedPickup();
        $this->expectException(ForbiddenException::class);
        $this->pickupSvc->confirmReceipt($this->donorId, 'donor', $pickupId);
    }

    /** T-10.3-03: Cannot confirm receipt on non-collected pickup. */
    public function testCannotConfirmReceiptWhenNotCollected(): void
    {
        $p = $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);
        $this->pickupSvc->confirmPickup($this->ngoUserId, 'ngo', $p['id']);
        // State is now 'scheduled', not 'collected'
        $this->expectException(ConflictException::class);
        $this->pickupSvc->confirmReceipt($this->ngoUserId, 'ngo', $p['id']);
    }

    /** T-10.3-04: Allocation status becomes 'completed' after receipt confirmation. */
    public function testAllocationStatusCompletedAfterReceipt(): void
    {
        [$pickupId, , $allocationId] = $this->buildCollectedPickup();
        $this->pickupSvc->confirmReceipt($this->ngoUserId, 'ngo', $pickupId);

        $stmt = $this->pdo->prepare("SELECT status FROM allocations WHERE id = :id");
        $stmt->execute([':id' => $allocationId]);
        $alloc = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('completed', $alloc['status']);
    }

    /** T-10.3-05: Requirement quantity_fulfilled increments by allocated_quantity. */
    public function testRequirementQuantityFulfilledIncremented(): void
    {
        [$pickupId, $reqId, $allocationId] = $this->buildCollectedPickup();

        $before = $this->pdo->prepare("SELECT quantity_fulfilled FROM ngo_requirements WHERE id = :id");
        $before->execute([':id' => $reqId]);
        $qBefore = (int) $before->fetchColumn();

        $this->pickupSvc->confirmReceipt($this->ngoUserId, 'ngo', $pickupId);

        $after = $this->pdo->prepare("SELECT quantity_fulfilled FROM ngo_requirements WHERE id = :id");
        $after->execute([':id' => $reqId]);
        $qAfter = (int) $after->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT allocated_quantity FROM allocations WHERE id = :id");
        $stmt->execute([':id' => $allocationId]);
        $qty = (int) $stmt->fetchColumn();

        $this->assertSame($qBefore + $qty, $qAfter);
    }

    /** T-10.3-06: Donation transitions to 'completed' when all stock is gone and no open allocations remain. */
    public function testDonationCompletedWhenFullyFulfilled(): void
    {
        [$pickupId, , , $donationId] = $this->buildCollectedPickup();

        // Ensure available_quantity is 0 (all 3 units allocated)
        $this->pdo->prepare("UPDATE donations SET available_quantity = 0 WHERE id = :id")
            ->execute([':id' => $donationId]);

        $this->pickupSvc->confirmReceipt($this->ngoUserId, 'ngo', $pickupId);

        $stmt = $this->pdo->prepare("SELECT status FROM donations WHERE id = :id");
        $stmt->execute([':id' => $donationId]);
        $status = $stmt->fetchColumn();
        $this->assertSame('completed', $status);
    }

    /** T-10.3-07: Donation history is returned with all relevant audit events. */
    public function testDonationHistoryContainsAllAuditEvents(): void
    {
        [$pickupId, , , $donationId] = $this->buildCollectedPickup();
        $this->pickupSvc->confirmReceipt($this->ngoUserId, 'ngo', $pickupId);

        $history = $this->pickupSvc->getDonationHistory($this->donorId, 'donor', $donationId);
        $this->assertIsArray($history);
        $this->assertNotEmpty($history);

        $actions = array_column($history, 'action');
        // Expect at least otp issuance + verification + handover events
        $this->assertContains('otp.issued', $actions);
        $this->assertContains('otp.verify.success', $actions);
        $this->assertContains('handover.completed', $actions);
    }

    /** T-10.3-08: Donation history never exposes plaintext OTP in metadata. */
    public function testDonationHistoryNeverExposeOtp(): void
    {
        [$pickupId, , , $donationId] = $this->buildCollectedPickup();
        $this->pickupSvc->confirmReceipt($this->ngoUserId, 'ngo', $pickupId);

        $history = $this->pickupSvc->getDonationHistory($this->donorId, 'donor', $donationId);
        foreach ($history as $event) {
            $meta = $event['metadata'] ?? [];
            $this->assertArrayNotHasKey('otp', $meta, "Event '{$event['action']}' must not expose OTP in metadata");
            foreach ($meta as $v) {
                if (is_string($v)) {
                    $this->assertDoesNotMatchRegularExpression(
                        '/^\d{6}$/',
                        $v,
                        "Event '{$event['action']}' metadata value should not be a 6-digit OTP"
                    );
                }
            }
        }
    }

    /** T-10.3-09: Non-owner cannot retrieve donation history. */
    public function testNonOwnerCannotGetDonationHistory(): void
    {
        $this->expectException(NotFoundException::class);
        $this->pickupSvc->getDonationHistory($this->ngo2UserId, 'ngo', $this->donationId);
    }

    /** T-10.3-10: getPickup returns 404 for unrelated user (location privacy). */
    public function testGetPickupReturns404ForUnrelatedUser(): void
    {
        $p = $this->pickupSvc->proposePickup($this->donorId, 'donor', [
            'allocation_id' => $this->confirmedAllocationId,
            'scheduled_at'  => $this->futureTs(),
        ]);

        $this->expectException(NotFoundException::class);
        $this->pickupSvc->getPickup($this->ngo2UserId, 'ngo', $p['id']);
    }
}
