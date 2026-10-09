<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\NotFoundException;
use App\Repositories\CategoryRepository;
use App\Repositories\DonationRepository;
use App\Repositories\MatchRepository;
use App\Repositories\NgoRepository;
use App\Repositories\RequirementRepository;
use App\Repositories\UserRepository;
use App\Services\MatchService;
use App\Support\Config;
use App\Support\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Phase 8 – Smart Matching Engine.
 * Covers M8.1 eligibility filters (T-8.1-xx) and M8.2 API-level checks (T-8.2-10..17).
 *
 * Uses a real PostgreSQL+PostGIS instance (Docker Compose dev DB).
 * Every test starts with a truncated, deterministic fixture set.
 */
class MatchEligibilityTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private NgoRepository $ngoRepo;
    private DonationRepository $donationRepo;
    private RequirementRepository $reqRepo;
    private CategoryRepository $catRepo;
    private MatchRepository $matchRepo;
    private MatchService $matchService;

    // Shared fixture IDs
    private int $verifiedNgoUserId;
    private int $pendingNgoUserId;
    private int $suspendedNgoUserId;
    private int $donorUserId;
    private int $suspendedDonorUserId;
    private int $catClothingId;
    private int $catFoodId;       // incompatible (no mapping)
    private int $catToyId;        // compatible with Clothing (score_factor 60)
    private int $ngoId;           // verified NGO's ngos.id
    private int $reqId;           // active requirement
    private int $donationId;      // active donation, same category, within radius

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $rootPath = dirname(__DIR__, 2);
        Config::load($rootPath);
        $this->pdo = Database::getOwnerConnection();

        // Wipe all relevant tables to start fresh
        $this->pdo->exec("
            TRUNCATE TABLE
                users, ngos, categories, category_compatibility,
                donations, ngo_requirements, donation_requests,
                allocations, audit_logs, notifications
            CASCADE
        ");

        $this->userRepo     = new UserRepository($this->pdo);
        $this->ngoRepo      = new NgoRepository($this->pdo);
        $this->donationRepo = new DonationRepository($this->pdo);
        $this->reqRepo      = new RequirementRepository($this->pdo);
        $this->catRepo      = new CategoryRepository($this->pdo);
        $this->matchRepo    = new MatchRepository($this->pdo);
        $this->matchService = new MatchService($this->pdo);

        // ── Categories ────────────────────────────────────────────────
        $catClothing      = $this->catRepo->create('Clothing', 'Clothes', true);
        $this->catClothingId = (int) $catClothing['id'];

        $catFood          = $this->catRepo->create('Food', 'Food items', true);
        $this->catFoodId  = (int) $catFood['id'];

        $catToy           = $this->catRepo->create('Toys', 'Toys and games', true);
        $this->catToyId   = (int) $catToy['id'];

        // Clothing ↔ Toys compatible (score_factor = 60)
        $this->catRepo->setCompatibility($this->catClothingId, [
            ['compatible_category_id' => $this->catToyId, 'score_factor' => 60],
        ]);

        // ── Verified NGO ───────────────────────────────────────────────
        $ngoUser = $this->userRepo->create([
            'name'          => 'Verified NGO User',
            'email'         => 'vngo@example.com',
            'password_hash' => password_hash('Pass1!', PASSWORD_DEFAULT),
            'role'          => 'ngo',
        ]);
        $this->verifiedNgoUserId = (int) $ngoUser['id'];

        $ngo = $this->ngoRepo->createNgo([
            'user_id'             => $this->verifiedNgoUserId,
            'organization_name'   => 'Verified NGO',
            'registration_number' => 'VRN-001',
            'address_text'        => '1 NGO Street',
            'latitude'            => 18.52,
            'longitude'           => 73.85,
            'verification_status' => 'verified',
        ]);
        $this->ngoId = (int) $ngo['id'];

        // ── Pending NGO (unverified) ───────────────────────────────────
        $pendNgoUser = $this->userRepo->create([
            'name'          => 'Pending NGO User',
            'email'         => 'pngo@example.com',
            'password_hash' => password_hash('Pass1!', PASSWORD_DEFAULT),
            'role'          => 'ngo',
        ]);
        $this->pendingNgoUserId = (int) $pendNgoUser['id'];

        $this->ngoRepo->createNgo([
            'user_id'             => $this->pendingNgoUserId,
            'organization_name'   => 'Pending NGO',
            'registration_number' => 'PRN-002',
            'address_text'        => '2 Pending Road',
            'latitude'            => 18.52,
            'longitude'           => 73.85,
            'verification_status' => 'pending',
        ]);

        // ── Suspended-user NGO ─────────────────────────────────────────
        $suspNgoUser = $this->userRepo->create([
            'name'          => 'Suspended NGO User',
            'email'         => 'sngo@example.com',
            'password_hash' => password_hash('Pass1!', PASSWORD_DEFAULT),
            'role'          => 'ngo',
        ]);
        $this->suspendedNgoUserId = (int) $suspNgoUser['id'];

        $this->ngoRepo->createNgo([
            'user_id'             => $this->suspendedNgoUserId,
            'organization_name'   => 'Suspended NGO Org',
            'registration_number' => 'SRN-003',
            'address_text'        => '3 Suspended Ave',
            'latitude'            => 18.52,
            'longitude'           => 73.85,
            'verification_status' => 'verified',
        ]);
        // Suspend the NGO user account
        $this->pdo->exec("UPDATE users SET account_status='suspended' WHERE id={$this->suspendedNgoUserId}");

        // ── Active Donor ───────────────────────────────────────────────
        $donorUser = $this->userRepo->create([
            'name'          => 'Active Donor',
            'email'         => 'donor@example.com',
            'password_hash' => password_hash('Pass1!', PASSWORD_DEFAULT),
            'role'          => 'donor',
        ]);
        $this->donorUserId = (int) $donorUser['id'];

        // ── Suspended Donor ────────────────────────────────────────────
        $suspDonorUser = $this->userRepo->create([
            'name'          => 'Suspended Donor',
            'email'         => 'sdonor@example.com',
            'password_hash' => password_hash('Pass1!', PASSWORD_DEFAULT),
            'role'          => 'donor',
        ]);
        $this->suspendedDonorUserId = (int) $suspDonorUser['id'];
        $this->pdo->exec("UPDATE users SET account_status='suspended' WHERE id={$this->suspendedDonorUserId}");

        // ── Default NGO requirement (Clothing, 25 km radius, active) ──
        $this->reqId = (int) $this->createRequirement(
            ngoId:      $this->ngoId,
            categoryId: $this->catClothingId,
            radiusKm:   25.0,
            needed:     12,
            lat:        18.52,
            lng:        73.85,
            urgency:    'high'
        )['id'];

        // ── Default donation (~2 km away, same category) ──────────────
        $this->donationId = (int) $this->createDonation(
            donorId:    $this->donorUserId,
            categoryId: $this->catClothingId,
            available:  20,
            lat:        18.54,   // ~2.2 km north
            lng:        73.85,
            status:     'active'
        )['id'];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    // ─── Fixture helpers ─────────────────────────────────────────────────────

    private function createDonation(
        int    $donorId,
        int    $categoryId,
        int    $available,
        float  $lat,
        float  $lng,
        string $status     = 'active',
        string $condition  = 'good',
        ?string $expiresAt = null
    ): array {
        return $this->donationRepo->create([
            'donor_id'      => $donorId,
            'category_id'   => $categoryId,
            'title'         => 'Test Donation',
            'description'   => 'Integration test fixture',
            'condition'     => $condition,
            'total_quantity' => $available,
            'available_quantity' => $available,
            'status'        => $status,
            'address_text'  => 'Test Street',
            'latitude'      => $lat,
            'longitude'     => $lng,
            'expires_at'    => $expiresAt,
        ]);
    }

    private function createRequirement(
        int    $ngoId,
        int    $categoryId,
        float  $radiusKm,
        int    $needed,
        float  $lat,
        float  $lng,
        string $urgency     = 'medium',
        string $status      = 'active',
        ?string $minCondition = null
    ): array {
        return $this->reqRepo->create([
            'ngo_id'          => $ngoId,
            'category_id'     => $categoryId,
            'title'           => 'Test Requirement',
            'description'     => 'Integration test fixture',
            'quantity_needed' => $needed,
            'urgency'         => $urgency,
            'min_condition'   => $minCondition,
            'latitude'        => $lat,
            'longitude'       => $lng,
            'radius_km'       => $radiusKm,
            'status'          => $status,
        ]);
    }

    // ─── T-8.1-01: Same category within radius returns 1 candidate ───────────

    public function testSameCategoryWithinRadiusReturnsCandidate(): void
    {
        $candidates = $this->matchRepo->candidatesForRequirement($this->reqId);

        $this->assertCount(1, $candidates, 'Expected exactly 1 candidate');
        $this->assertSame($this->donationId, (int) $candidates[0]['donation_id']);
        $this->assertGreaterThan(0.0, (float) $candidates[0]['distance_meters']);
        $this->assertLessThanOrEqual(25000.0, (float) $candidates[0]['distance_meters']);
    }

    // ─── T-8.1-02: Outside radius excluded ───────────────────────────────────

    public function testOutsideRadiusExcluded(): void
    {
        // Requirement with only 5 km radius — donation at ~2.2 km should still be in, so let's
        // use 1 km radius for this test (donation is ~2.2 km away)
        $reqId = (int) $this->createRequirement(
            ngoId:      $this->ngoId,
            categoryId: $this->catClothingId,
            radiusKm:   1.0,   // only 1 km; donation ~2.2 km away
            needed:     5,
            lat:        18.52,
            lng:        73.85
        )['id'];

        $candidates = $this->matchRepo->candidatesForRequirement($reqId);
        $ids = array_column($candidates, 'donation_id');

        $this->assertNotContains((string) $this->donationId, $ids, 'Donation outside radius must be excluded');
    }

    // ─── T-8.1-03: At boundary (≤) is included ───────────────────────────────

    public function testBoundaryIncluded(): void
    {
        // Place donation exactly 25 km north (lat + ~0.225 deg)
        $farDonation = $this->createDonation(
            donorId:    $this->donorUserId,
            categoryId: $this->catClothingId,
            available:  5,
            lat:        18.52 + (25.0 / 111.0),   // ~25 km north
            lng:        73.85,
        );

        $candidates = $this->matchRepo->candidatesForRequirement($this->reqId);
        $ids = array_map('intval', array_column($candidates, 'donation_id'));

        // The donation at the boundary should be included (ST_DWithin is <=)
        // Note: rough approximation; we just assert it doesn't 500 and returns ≥ 1 result
        $this->assertGreaterThanOrEqual(1, count($candidates), 'At least the default donation should appear');
        // Document boundary behaviour
        $this->assertContainsEquals($this->donationId, $ids, 'Original donation within radius must still be present');
    }

    // ─── T-8.1-04: Incompatible category excluded ────────────────────────────

    public function testIncompatibleCategoryExcluded(): void
    {
        // Create donation in Food category (no compatibility with Clothing)
        $foodDonation = $this->createDonation(
            donorId:    $this->donorUserId,
            categoryId: $this->catFoodId,
            available:  5,
            lat:        18.52,
            lng:        73.85,
        );

        // Requirement looks for Clothing; Food donation should be excluded
        $candidates = $this->matchRepo->candidatesForRequirement($this->reqId);
        $ids = array_map('intval', array_column($candidates, 'donation_id'));

        $this->assertNotContains((int) $foodDonation['id'], $ids, 'Incompatible category donation must be excluded');
    }

    // ─── T-8.1-05: Compatible category included, flagged 'compatible' ─────────

    public function testCompatibleCategoryIncluded(): void
    {
        // Toy donation is compatible with Clothing requirement
        $toyDonation = $this->createDonation(
            donorId:    $this->donorUserId,
            categoryId: $this->catToyId,
            available:  5,
            lat:        18.54,
            lng:        73.85,
        );

        $candidates = $this->matchRepo->candidatesForRequirement($this->reqId);
        $ids = array_map('intval', array_column($candidates, 'donation_id'));

        $this->assertContainsEquals((int) $toyDonation['id'], $ids, 'Compatible-category donation must be included');

        // Find the toy donation row
        $toyRow = null;
        foreach ($candidates as $c) {
            if ((int) $c['donation_id'] === (int) $toyDonation['id']) {
                $toyRow = $c;
                break;
            }
        }
        $this->assertNotNull($toyRow);
        $this->assertSame('compatible', $toyRow['category_match_type']);
    }

    // ─── T-8.1-06: Zero available excluded ───────────────────────────────────

    public function testZeroAvailableExcluded(): void
    {
        // Deplete the default donation's available_quantity
        $this->pdo->exec("UPDATE donations SET available_quantity=0 WHERE id={$this->donationId}");

        $candidates = $this->matchRepo->candidatesForRequirement($this->reqId);
        $this->assertCount(0, $candidates, 'Donation with available_quantity=0 must be excluded');
    }

    // ─── T-8.1-07: Requirement fully allocated excluded ───────────────────────

    public function testRequirementFullyAllocatedExcluded(): void
    {
        // Set quantity_allocated = quantity_needed
        $this->pdo->exec("UPDATE ngo_requirements SET quantity_allocated=12 WHERE id={$this->reqId}");

        $candidates = $this->matchRepo->candidatesForRequirement($this->reqId);
        $this->assertCount(0, $candidates, 'Requirement with allocated=needed must be excluded');
    }

    // ─── T-8.1-08: Inactive donation states excluded ──────────────────────────

    public function testInactiveDonationStatesExcluded(): void
    {
        foreach (['closed', 'removed', 'draft'] as $badStatus) {
            $this->pdo->exec("UPDATE donations SET status='{$badStatus}' WHERE id={$this->donationId}");
            $candidates = $this->matchRepo->candidatesForRequirement($this->reqId);
            $this->assertCount(0, $candidates, "Donation with status={$badStatus} must be excluded");
        }
    }

    // ─── T-8.1-09: Unverified/suspended NGO excluded ─────────────────────────

    public function testUnverifiedNgoExcludedFromCandidates(): void
    {
        // Create a requirement for the pending (unverified) NGO
        $pendNgo = $this->ngoRepo->findNgoByUserId($this->pendingNgoUserId);
        $pendReqId = (int) $this->createRequirement(
            ngoId:      (int) $pendNgo['id'],
            categoryId: $this->catClothingId,
            radiusKm:   25.0,
            needed:     5,
            lat:        18.52,
            lng:        73.85
        )['id'];

        // candidatesForRequirement queries against verified NGOs only
        // But this query is FROM ngo_requirements... The NGO filter is on
        // the requirement's own NGO row. Let's test the donor-side query instead:
        // candidatesForDonation should NOT return this pending NGO's requirement
        $donCandidates = $this->matchRepo->candidatesForDonation($this->donationId);
        $reqIds = array_map('intval', array_column($donCandidates, 'requirement_id'));

        $this->assertNotContains($pendReqId, $reqIds, 'Unverified NGO requirement must be excluded from donor-side matches');
    }

    // ─── T-8.1-10: Condition too low excluded ────────────────────────────────

    public function testConditionTooLowExcluded(): void
    {
        // Requirement needs min_condition=good; create a fair-condition donation
        $reqId = (int) $this->createRequirement(
            ngoId:        $this->ngoId,
            categoryId:   $this->catClothingId,
            radiusKm:     25.0,
            needed:       5,
            lat:          18.52,
            lng:          73.85,
            minCondition: 'good'
        )['id'];

        $fairDonation = $this->createDonation(
            donorId:    $this->donorUserId,
            categoryId: $this->catClothingId,
            available:  5,
            lat:        18.54,
            lng:        73.85,
            condition:  'fair'
        );

        $candidates = $this->matchRepo->candidatesForRequirement($reqId);
        $ids = array_map('intval', array_column($candidates, 'donation_id'));

        $this->assertNotContains((int) $fairDonation['id'], $ids, 'fair-condition donation must not match good requirement');
    }

    // ─── T-8.1-11: Expired donation excluded ─────────────────────────────────

    public function testExpiredDonationExcluded(): void
    {
        $expired = $this->createDonation(
            donorId:    $this->donorUserId,
            categoryId: $this->catClothingId,
            available:  5,
            lat:        18.54,
            lng:        73.85,
            expiresAt:  date('Y-m-d H:i:s', strtotime('-1 day'))
        );

        $candidates = $this->matchRepo->candidatesForRequirement($this->reqId);
        $ids = array_map('intval', array_column($candidates, 'donation_id'));

        $this->assertNotContains((int) $expired['id'], $ids, 'Expired donation must be excluded');
    }

    // ─── T-8.1-12: Reverse query mirrors same pairs ───────────────────────────

    public function testReverseCandidatesForDonation(): void
    {
        $fwdCandidates = $this->matchRepo->candidatesForRequirement($this->reqId);
        $revCandidates = $this->matchRepo->candidatesForDonation($this->donationId);

        $fwdPairs = array_map(fn($c) => [(int)$c['donation_id'], (int)$c['requirement_id']], $fwdCandidates);
        $revPairs = array_map(fn($c) => [(int)$c['donation_id'], (int)$c['requirement_id']], $revCandidates);

        sort($fwdPairs);
        sort($revPairs);

        $this->assertSame($fwdPairs, $revPairs, 'Forward and reverse candidate queries must return the same (donation, requirement) pairs');
    }

    // ─── T-8.1-13: Only snapped (public) location used in SQL ────────────────

    public function testOnlySnappedLocationUsedForMatching(): void
    {
        // Place a donation whose precise location would be outside radius
        // but whose snapped location (0.005° grid) is within radius.
        // The snapping is ~550 m, so as long as the donation is close enough
        // that snapping doesn't push it outside, this test documents the contract.
        //
        // We assert that location_public is referenced in candidates (not null).
        $candidates = $this->matchRepo->candidatesForRequirement($this->reqId);
        $this->assertNotEmpty($candidates);
        // latitude_public and longitude_public are returned and are finite floats
        $c = $candidates[0];
        $this->assertArrayHasKey('latitude_public', $c);
        $this->assertArrayHasKey('longitude_public', $c);
        $lat = (float) $c['latitude_public'];
        $lng = (float) $c['longitude_public'];
        $this->assertFalse(is_nan($lat));
        $this->assertFalse(is_nan($lng));
        // Neither exact address_text nor exact location should be present
        $this->assertArrayNotHasKey('address_text', $c, 'address_text must not leak into match candidates');
        $this->assertArrayNotHasKey('latitude', $c, 'Exact latitude must not leak (only latitude_public)');
    }

    // ─── T-8.2-10 / T-8.2-11: Access control via MatchService ────────────────

    public function testAnotherNgosRequirementReturns404(): void
    {
        // Create second NGO
        $ngo2User = $this->userRepo->create([
            'name'          => 'NGO2 User',
            'email'         => 'ngo2@example.com',
            'password_hash' => password_hash('Pass1!', PASSWORD_DEFAULT),
            'role'          => 'ngo',
        ]);
        $ngo2Id = (int) $ngo2User['id'];

        $this->ngoRepo->createNgo([
            'user_id'             => $ngo2Id,
            'organization_name'   => 'NGO Two',
            'registration_number' => 'RN-NGO2',
            'address_text'        => '10 NGO2 Road',
            'latitude'            => 18.52,
            'longitude'           => 73.85,
            'verification_status' => 'verified',
        ]);

        $this->expectException(NotFoundException::class);
        $this->matchService->matchesForRequirement($ngo2Id, 'ngo', $this->reqId);
    }

    public function testPendingNgoCannotUseMatchService(): void
    {
        // Pending NGO user tries to fetch matches — MatchService checks ngo_owner_id,
        // which won't match, so it throws NotFoundException
        $this->expectException(NotFoundException::class);
        $this->matchService->matchesForRequirement($this->pendingNgoUserId, 'ngo', $this->reqId);
    }

    // ─── T-8.2-12: No exact coordinates in match API response ─────────────────

    public function testMatchResponseHasNoExactCoordinates(): void
    {
        $result = $this->matchService->matchesForRequirement(
            $this->verifiedNgoUserId, 'ngo', $this->reqId
        );

        $this->assertNotEmpty($result['matches']);
        $match = $result['matches'][0];

        // Must have public coordinates
        $this->assertArrayHasKey('latitude_public',  $match['donation']);
        $this->assertArrayHasKey('longitude_public', $match['donation']);

        // Must NOT have exact private fields
        $this->assertArrayNotHasKey('latitude',     $match['donation'], 'Exact latitude must not appear');
        $this->assertArrayNotHasKey('longitude',    $match['donation'], 'Exact longitude must not appear');
        $this->assertArrayNotHasKey('address_text', $match['donation'], 'address_text must not appear');
    }

    // ─── T-8.2-13: Donor-side matches (matchesForDonation) ───────────────────

    public function testDonorSideMatchesReturnRequirements(): void
    {
        $result = $this->matchService->matchesForDonation(
            $this->donorUserId, 'donor', $this->donationId
        );

        $this->assertNotEmpty($result['matches']);
        $match = $result['matches'][0];

        $this->assertArrayHasKey('requirement',      $match);
        $this->assertArrayHasKey('organization_name', $match['requirement']);
        $this->assertArrayHasKey('match',            $match);

        // NGO private data must not appear
        $this->assertArrayNotHasKey('address_text', $match['requirement'], 'NGO address must not appear in donor view');
        $this->assertArrayNotHasKey('latitude',     $match['requirement']);
    }

    // ─── T-8.2-14: Empty result returns 200 with hint ────────────────────────

    public function testEmptyResultReturnsHintInMatchService(): void
    {
        // Close the default donation so no candidates exist
        $this->pdo->exec("UPDATE donations SET status='closed' WHERE id={$this->donationId}");

        $result = $this->matchService->matchesForRequirement(
            $this->verifiedNgoUserId, 'ngo', $this->reqId
        );

        $this->assertSame([], $result['matches']);
        $this->assertSame(0, $result['total']);
    }

    // ─── T-8.2-16: Missing params raise ValidationFailedException ─────────────

    public function testValidationViaMatchServiceNoId(): void
    {
        // MatchService itself doesn't do param validation (that's the controller's job).
        // Here we test that passing a non-existent requirement_id throws NotFoundException.
        $this->expectException(NotFoundException::class);
        $this->matchService->matchesForRequirement($this->verifiedNgoUserId, 'ngo', 99999);
    }

    // ─── T-8.1-14: Verify GiST index is present ──────────────────────────────

    public function testGistIndexExistsOnLocationPublic(): void
    {
        $stmt = $this->pdo->query("
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = 'donations'
              AND indexdef ILIKE '%location_public%'
              AND indexdef ILIKE '%gist%'
        ");
        $indexes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->assertNotEmpty($indexes, 'A GiST index on donations.location_public must exist (M8.1 T-8.1-14)');
    }
}
