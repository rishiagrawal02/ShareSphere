<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Request;
use App\Http\Router;
use App\Repositories\AllocationRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\DonationRepository;
use App\Repositories\RequestRepository;
use App\Repositories\NgoRepository;
use App\Repositories\PickupRepository;
use App\Repositories\RequirementRepository;
use App\Repositories\UserRepository;
use App\Services\DonationService;
use App\Services\OtpService;
use App\Services\PickupService;
use App\Services\RequirementService;
use App\Support\Config;
use App\Support\CsrfGuard;
use App\Support\Database;
use App\Support\SessionManager;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * T-19.1-03: IDOR (Insecure Direct Object Reference) sweep.
 * Asserts that users of identical roles cannot modify, access, or perform
 * state actions on resources belonging to other users.
 */
class IdorTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private NgoRepository $ngoRepo;
    private CategoryRepository $catRepo;
    private DonationRepository $donationRepo;
    private RequirementRepository $reqRepo;
    private RequestRepository $requestRepo;
    private AllocationRepository $allocationRepo;
    private PickupRepository $pickupRepo;
    private DonationService $donationService;
    private RequirementService $reqService;
    private PickupService $pickupService;
    private OtpService $otpService;
    private Router $router;

    private int $donor1Id;
    private int $donor2Id;
    private int $ngo1UserId;
    private int $ngo1Id;
    private int $ngo2UserId;
    private int $ngo2Id;
    private int $adminId;
    private int $catId;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $root = dirname(__DIR__, 2);
        Config::load($root);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec('TRUNCATE TABLE users, ngos, categories, donations, donation_images, ngo_requirements, donation_requests, allocations, pickups, notifications, audit_logs CASCADE');

        $this->userRepo = new UserRepository($this->pdo);
        $this->ngoRepo = new NgoRepository($this->pdo);
        $this->catRepo = new CategoryRepository($this->pdo);
        $this->donationRepo = new DonationRepository($this->pdo);
        $this->reqRepo = new RequirementRepository($this->pdo);
        $this->requestRepo = new RequestRepository($this->pdo);
        $this->allocationRepo = new AllocationRepository($this->pdo);
        $this->pickupRepo = new PickupRepository($this->pdo);
        $this->donationService = new DonationService($this->pdo);
        $this->reqService = new RequirementService($this->pdo);
        $this->pickupService = new PickupService($this->pdo);
        $this->otpService = new OtpService($this->pdo);

        $this->router = new Router();
        $routesFn = require $root . '/src/routes.php';
        $routesFn($this->router);

        // Donor 1 and Donor 2
        $d1 = $this->userRepo->create(['name' => 'Donor One', 'email' => 'd1@test.org', 'password_hash' => 'hash', 'role' => 'donor']);
        $this->donor1Id = (int) $d1['id'];
        $d2 = $this->userRepo->create(['name' => 'Donor Two', 'email' => 'd2@test.org', 'password_hash' => 'hash', 'role' => 'donor']);
        $this->donor2Id = (int) $d2['id'];

        // NGO 1 and NGO 2
        $n1User = $this->userRepo->create(['name' => 'NGO One Rep', 'email' => 'n1@test.org', 'password_hash' => 'hash', 'role' => 'ngo']);
        $this->ngo1UserId = (int) $n1User['id'];
        $n1 = $this->ngoRepo->createNgo([
            'user_id' => $this->ngo1UserId,
            'organization_name' => 'NGO First Org',
            'registration_number' => 'REG-101',
            'address_text' => '101 First Ave',
            'latitude' => 18.52,
            'longitude' => 73.85,
            'verification_status' => 'verified',
        ]);
        $this->ngo1Id = (int) $n1['id'];

        $n2User = $this->userRepo->create(['name' => 'NGO Two Rep', 'email' => 'n2@test.org', 'password_hash' => 'hash', 'role' => 'ngo']);
        $this->ngo2UserId = (int) $n2User['id'];
        $n2 = $this->ngoRepo->createNgo([
            'user_id' => $this->ngo2UserId,
            'organization_name' => 'NGO Second Org',
            'registration_number' => 'REG-202',
            'address_text' => '202 Second Ave',
            'latitude' => 18.53,
            'longitude' => 73.86,
            'verification_status' => 'verified',
        ]);
        $this->ngo2Id = (int) $n2['id'];

        // Admin
        $adm = $this->userRepo->create(['name' => 'Admin User', 'email' => 'admin@test.org', 'password_hash' => 'hash', 'role' => 'admin']);
        $this->adminId = (int) $adm['id'];

        // Category
        $cat = $this->catRepo->create('Apparel', 'Clothing items', true);
        $this->catId = (int) $cat['id'];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    public function testDonorTwoCannotUpdateDonorOneDonation(): void
    {
        $donation = $this->donationService->createDonation($this->donor1Id, 'donor', [
            'title' => 'Warm Blankets',
            'category_id' => $this->catId,
            'description' => '10 wool blankets',
            'condition' => 'good',
            'total_quantity' => 10,
            'latitude' => 18.52,
            'longitude' => 73.85,
            'address_text' => 'Test Location',
        ]);

        $this->expectException(NotFoundException::class);
        $this->donationService->updateDonation($this->donor2Id, (int) $donation['id'], [
            'title' => 'Maliciously Hijacked Blankets',
        ]);
    }

    public function testDonorTwoCannotCloseDonorOneDonation(): void
    {
        $donation = $this->donationService->createDonation($this->donor1Id, 'donor', [
            'title' => 'Jackets',
            'category_id' => $this->catId,
            'description' => '5 winter jackets',
            'condition' => 'good',
            'total_quantity' => 5,
            'latitude' => 18.52,
            'longitude' => 73.85,
            'address_text' => 'Test Location',
        ]);

        $this->expectException(NotFoundException::class);
        $this->donationService->closeDonation($this->donor2Id, (int) $donation['id']);
    }

    public function testNgoTwoCannotUpdateNgoOneRequirement(): void
    {
        $req = $this->reqService->createRequirement($this->ngo1UserId, [
            'category_id' => $this->catId,
            'title' => 'Urgent Winter Clothes Needed',
            'quantity_needed' => 50,
            'urgency' => 'high',
        ]);

        $this->expectException(NotFoundException::class);
        $this->reqService->updateRequirement($this->ngo2UserId, (int) $req['id'], [
            'title' => 'Altered Requirement Title',
        ]);
    }

    public function testNgoTwoCannotCloseNgoOneRequirement(): void
    {
        $req = $this->reqService->createRequirement($this->ngo1UserId, [
            'category_id' => $this->catId,
            'title' => 'Blankets Needed',
            'quantity_needed' => 20,
            'urgency' => 'medium',
        ]);

        $this->expectException(NotFoundException::class);
        $this->reqService->closeRequirement($this->ngo2UserId, (int) $req['id']);
    }

    private function dispatch(Request $request): \App\Http\Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (\Throwable $e) {
            return \App\Http\ErrorHandler::handle($e, $request);
        }
    }

    public function testNgoTwoCannotAccessNgoOnePrivateVerificationDocument(): void
    {
        // Insert a private verification document for NGO 1
        $stmt = $this->pdo->prepare("
            INSERT INTO ngo_documents (ngo_id, storage_name, mime, size_bytes, sha256, doc_type)
            VALUES (:ngo_id, 'fake_storage_path.pdf', 'application/pdf', 1024, 'dummyhash', 'registration_proof')
            RETURNING id
        ");
        $stmt->execute([':ngo_id' => $this->ngo1Id]);
        $docId = (int) $stmt->fetchColumn();

        SessionManager::setUser($this->ngo2UserId, 'ngo');
        $csrf = CsrfGuard::getToken();

        // NGO 2 attempts to delete NGO 1's document
        $request = new Request('DELETE', "/api/profile/ngo-documents/{$docId}", [], ['x-csrf-token' => $csrf]);
        $response = $this->dispatch($request);
        $this->assertSame(403, $response->getStatusCode(), 'NGO 2 must not be able to delete NGO 1 document');

        // NGO 2 attempts to stream NGO 1's document via media endpoint
        $mediaReq = new Request('GET', "/api/media/ngo-documents/{$docId}", []);
        $mediaResp = $this->dispatch($mediaReq);
        // Media endpoint returns 404 to prevent resource enumeration
        $this->assertSame(404, $mediaResp->getStatusCode(), 'NGO 2 streaming NGO 1 document must return 404');
    }

    public function testDonorTwoCannotVerifyPickupOtpForDonorOneDonation(): void
    {
        // Create donation by Donor 1
        $donation = $this->donationService->createDonation($this->donor1Id, 'donor', [
            'title' => 'Books',
            'category_id' => $this->catId,
            'description' => 'Textbooks',
            'condition' => 'good',
            'total_quantity' => 10,
            'latitude' => 18.52,
            'longitude' => 73.85,
            'address_text' => 'Donor Address',
        ]);
        $donationId = (int) $donation['id'];

        // NGO 1 requests donation
        $reqId = $this->requestRepo->create([
            'donation_id' => $donationId,
            'ngo_id' => $this->ngo1Id,
            'requested_quantity' => 5,
            'status' => 'pending',
        ]);

        // Allocate and create pickup
        $allocId = $this->allocationRepo->create([
            'request_id' => $reqId,
            'donation_id' => $donationId,
            'allocated_quantity' => 5,
            'status' => 'reserved',
        ]);

        $pickupId = $this->pickupRepo->create([
            'allocation_id' => $allocId,
            'proposed_by' => $this->ngo1UserId,
            'scheduled_at' => date('Y-m-d H:i:s', time() + 3600),
            'location_details' => 'Gate 1',
            'state' => 'scheduled',
        ]);

        // NGO 1 issues OTP
        $this->otpService->issue($pickupId, $this->ngo1UserId);

        // Donor 2 attempts to verify OTP on Donor 1's pickup
        $this->expectException(ForbiddenException::class);
        $this->otpService->verify($pickupId, $this->donor2Id, '123456');
    }

    public function testNgoTwoCannotIssueOtpForPickupAssignedToNgoOne(): void
    {
        $donation = $this->donationService->createDonation($this->donor1Id, 'donor', [
            'title' => 'Shoes',
            'category_id' => $this->catId,
            'description' => 'Running shoes',
            'condition' => 'new',
            'total_quantity' => 2,
            'latitude' => 18.52,
            'longitude' => 73.85,
            'address_text' => 'Donor Address',
        ]);
        $donationId = (int) $donation['id'];

        $reqId = $this->requestRepo->create([
            'donation_id' => $donationId,
            'ngo_id' => $this->ngo1Id,
            'requested_quantity' => 2,
            'status' => 'accepted',
        ]);

        $allocId = $this->allocationRepo->create([
            'request_id' => $reqId,
            'donation_id' => $donationId,
            'allocated_quantity' => 2,
            'status' => 'confirmed',
        ]);

        $pickupId = $this->pickupRepo->create([
            'allocation_id' => $allocId,
            'proposed_by' => $this->ngo1UserId,
            'scheduled_at' => date('Y-m-d H:i:s', time() + 3600),
            'location_details' => 'Front lobby',
            'state' => 'scheduled',
        ]);

        // NGO 2 attempts to issue OTP for NGO 1's pickup
        $this->expectException(ForbiddenException::class);
        $this->otpService->issue($pickupId, $this->ngo2UserId);
    }
}
