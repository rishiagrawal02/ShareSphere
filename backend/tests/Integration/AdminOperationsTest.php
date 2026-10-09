<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\UnauthorizedException;
use App\Http\Exceptions\ValidationFailedException;
use App\Repositories\CategoryRepository;
use App\Repositories\DonationRepository;
use App\Repositories\MatchRepository;
use App\Repositories\NgoRepository;
use App\Repositories\RequirementRepository;
use App\Repositories\UserRepository;
use App\Services\ReportService;
use App\Services\UserAdminService;
use App\Support\Config;
use App\Support\Database;
use App\Support\SessionManager;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminOperationsTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private NgoRepository $ngoRepo;
    private CategoryRepository $catRepo;
    private DonationRepository $donationRepo;
    private RequirementRepository $reqRepo;
    private MatchRepository $matchRepo;
    private UserAdminService $userAdminSvc;
    private ReportService $reportSvc;

    private int $adminId;
    private int $admin2Id;
    private int $donorId;
    private int $ngoUserId;
    private int $ngoId;
    private int $categoryId;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $rootPath = dirname(__DIR__, 2);
        Config::load($rootPath);

        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec("
            TRUNCATE TABLE pickups, allocations, donation_requests,
                           donations, ngo_requirements, ngos,
                           categories, category_compatibility,
                           users, notifications, audit_logs,
                           email_outbox
            CASCADE
        ");

        $this->userRepo     = new UserRepository($this->pdo);
        $this->ngoRepo      = new NgoRepository($this->pdo);
        $this->catRepo      = new CategoryRepository($this->pdo);
        $this->donationRepo = new DonationRepository($this->pdo);
        $this->reqRepo      = new RequirementRepository($this->pdo);
        $this->matchRepo    = new MatchRepository($this->pdo);
        $this->userAdminSvc = new UserAdminService($this->pdo);
        $this->reportSvc    = new ReportService($this->pdo);

        $this->seedFixtures();
    }

    private function seedFixtures(): void
    {
        $sfx = bin2hex(random_bytes(3));

        // Primary Admin
        $adm = $this->userRepo->create([
            'name'          => "Admin Primary {$sfx}",
            'email'         => "admin_{$sfx}@test.local",
            'password_hash' => password_hash('Pass!123', PASSWORD_DEFAULT),
            'role'          => 'admin',
            'account_status'=> 'active',
        ]);
        $this->adminId = (int) $adm['id'];

        // Secondary Admin
        $adm2 = $this->userRepo->create([
            'name'          => "Admin Secondary {$sfx}",
            'email'         => "admin2_{$sfx}@test.local",
            'password_hash' => password_hash('Pass!123', PASSWORD_DEFAULT),
            'role'          => 'admin',
            'account_status'=> 'active',
        ]);
        $this->admin2Id = (int) $adm2['id'];

        // Donor User
        $donor = $this->userRepo->create([
            'name'          => "Donor User {$sfx}",
            'email'         => "donor_{$sfx}@test.local",
            'password_hash' => password_hash('Pass!123', PASSWORD_DEFAULT),
            'role'          => 'donor',
            'account_status'=> 'active',
        ]);
        $this->donorId = (int) $donor['id'];

        // NGO User
        $ngoU = $this->userRepo->create([
            'name'          => "NGO User {$sfx}",
            'email'         => "ngo_{$sfx}@test.local",
            'password_hash' => password_hash('Pass!123', PASSWORD_DEFAULT),
            'role'          => 'ngo',
            'account_status'=> 'active',
        ]);
        $this->ngoUserId = (int) $ngoU['id'];

        $ngo = $this->ngoRepo->createNgo([
            'user_id'             => $this->ngoUserId,
            'organization_name'   => "Helping Org {$sfx}",
            'registration_number' => "REG-NGO-{$sfx}",
            'contact_phone'       => '+1234567890',
            'description'         => 'Test NGO',
            'address_text'        => '123 Main St',
            'latitude'            => 40.7128,
            'longitude'           => -74.0060,
        ]);
        $this->ngoId = (int) $ngo['id'];
        $this->ngoRepo->updateVerification($this->ngoId, 'verified', $this->adminId, 'Verified');

        // Category
        $cat = $this->catRepo->create("Supplies {$sfx}", 'Essential supplies');
        $this->categoryId = (int) $cat['id'];
    }

    // ══════════════════════════════════════════════════════════════
    // Milestone 11.1: User Management & Suspension Tests
    // ══════════════════════════════════════════════════════════════

    /**
     * T-11.1-01 (P): Admin lists and filters users.
     */
    public function testListAndFilterUsers(): void
    {
        $res = $this->userAdminSvc->listUsers(['role' => 'donor'], 1, 20);

        $this->assertGreaterThanOrEqual(1, $res['total']);
        $this->assertSame('donor', $res['items'][0]['role']);
        $this->assertArrayNotHasKey('password_hash', $res['items'][0]);
    }

    /**
     * T-11.1-02 (P): Suspend user -> status becomes suspended, audited, and immediate session rejection.
     */
    public function testSuspendUserAndImmediateSessionRejection(): void
    {
        // Suspend donor
        $updated = $this->userAdminSvc->updateStatus(
            $this->adminId,
            $this->donorId,
            'suspended',
            'Violated community guidelines'
        );

        $this->assertSame('suspended', $updated['account_status']);

        // Check DB row
        $user = $this->userRepo->findById($this->donorId);
        $this->assertSame('suspended', $user['account_status']);

        // Check AuthMiddleware live check throws 401 ACCOUNT_SUSPENDED
        SessionManager::setUser($this->donorId, 'donor');
        $authMiddleware = new \App\Middleware\AuthMiddleware($this->userRepo);
        $request = new \App\Http\Request('GET', '/api/donations');

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Account is suspended or does not exist');
        $authMiddleware->handle($request, fn() => \App\Http\Response::success());
    }

    /**
     * T-11.1-03 (V): Suspend without reason throws 422.
     */
    public function testSuspendWithoutReasonThrowsValidationException(): void
    {
        $this->expectException(ValidationFailedException::class);
        $this->userAdminSvc->updateStatus($this->adminId, $this->donorId, 'suspended', '');
    }

    /**
     * T-11.1-04 (N): Admin cannot suspend own account.
     */
    public function testAdminCannotSuspendSelf(): void
    {
        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Administrators cannot suspend their own account');
        $this->userAdminSvc->updateStatus($this->adminId, $this->adminId, 'suspended', 'Self test');
    }

    /**
     * T-11.1-05 (N): Cannot suspend the last active administrator.
     */
    public function testCannotSuspendLastActiveAdmin(): void
    {
        // Suspend secondary admin first (leaving 1 active admin)
        $this->userAdminSvc->updateStatus($this->adminId, $this->admin2Id, 'suspended', 'Suspend secondary');

        // Attempting to suspend the primary admin (now the sole active admin) by another context should fail
        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Cannot suspend the last active administrator');
        $this->userAdminSvc->updateStatus($this->admin2Id, $this->adminId, 'suspended', 'Attempt last admin suspend');
    }

    /**
     * T-11.1-07 (S): Passwords/hashes strictly absent from payload.
     */
    public function testNoHashesInPayload(): void
    {
        $user = $this->userAdminSvc->getUser($this->donorId);
        $this->assertArrayNotHasKey('password_hash', $user);

        $list = $this->userAdminSvc->listUsers();
        foreach ($list['items'] as $item) {
            $this->assertArrayNotHasKey('password_hash', $item);
        }
    }

    /**
     * T-11.1-08 (P): Reinstate suspended user -> can make requests again.
     */
    public function testReinstateSuspendedUser(): void
    {
        $this->userAdminSvc->updateStatus($this->adminId, $this->donorId, 'suspended', 'Policy review');
        $reinstated = $this->userAdminSvc->updateStatus($this->adminId, $this->donorId, 'active');

        $this->assertSame('active', $reinstated['account_status']);

        // Check AuthMiddleware allows request
        SessionManager::setUser($this->donorId, 'donor');
        $authMiddleware = new \App\Middleware\AuthMiddleware($this->userRepo);
        $request = new \App\Http\Request('GET', '/api/donations');

        $resp = $authMiddleware->handle($request, fn() => \App\Http\Response::success(['status' => 'ok']));
        $this->assertSame(200, $resp->getStatusCode());
    }

    /**
     * T-11.1-09 (I): Suspended donor's listings are excluded from matching queries.
     */
    public function testSuspendedDonorListingsExcludedFromMatching(): void
    {
        // Create donation by donor
        $don = $this->donationRepo->create([
            'donor_id'       => $this->donorId,
            'category_id'    => $this->categoryId,
            'title'          => 'Emergency Food Cans',
            'description'    => 'Non-perishable canned food',
            'total_quantity' => 20,
            'condition'      => 'new',
            'latitude'       => 40.7128,
            'longitude'      => -74.0060,
            'address_text'   => '100 Main St',
        ]);

        // Create requirement by verified NGO
        $req = $this->reqRepo->create([
            'ngo_id'          => $this->ngoId,
            'category_id'     => $this->categoryId,
            'title'           => 'Need food cans',
            'description'     => 'Urgent food need',
            'quantity_needed' => 20,
            'latitude'        => 40.7128,
            'longitude'       => -74.0060,
            'radius_km'       => 25,
        ]);

        // Before suspension: match is found
        $matchesBefore = $this->matchRepo->candidatesForRequirement((int) $req['id']);
        $this->assertNotEmpty($matchesBefore);

        // Suspend donor
        $this->userAdminSvc->updateStatus($this->adminId, $this->donorId, 'suspended', 'Donor fraud check');

        // After suspension: donor's donation is excluded
        $matchesAfter = $this->matchRepo->candidatesForRequirement((int) $req['id']);
        $this->assertEmpty($matchesAfter);
    }

    /**
     * T-11.1-10 (C): Suspend cascade closes active donations.
     */
    public function testSuspendCascadeClosesActiveDonations(): void
    {
        $don = $this->donationRepo->create([
            'donor_id'       => $this->donorId,
            'category_id'    => $this->categoryId,
            'title'          => 'Warm Blankets',
            'description'    => 'Wool blankets',
            'total_quantity' => 10,
            'condition'      => 'good',
            'latitude'       => 40.7128,
            'longitude'      => -74.0060,
            'address_text'   => '100 Main St',
        ]);

        $this->userAdminSvc->updateStatus($this->adminId, $this->donorId, 'suspended', 'Closing listings', true);

        $d = $this->donationRepo->findById((int) $don['id']);
        $this->assertSame('closed', $d['status']);
    }

    // ══════════════════════════════════════════════════════════════
    // Milestone 11.2: Reports, Analytics & Dashboards Tests
    // ══════════════════════════════════════════════════════════════

    /**
     * T-11.2-01 (P): Summary numbers reconcile with SQL ground truth.
     */
    public function testSummaryNumbersReconcile(): void
    {
        $summary = $this->reportSvc->getSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('users_by_role', $summary);
        $this->assertArrayHasKey('ngos_by_status', $summary);
        $this->assertArrayHasKey('donations_by_status', $summary);
        $this->assertArrayHasKey('requests_by_status', $summary);
        $this->assertArrayHasKey('completed_handovers', $summary);
        $this->assertArrayHasKey('completion_rate', $summary);

        $this->assertSame(2, $summary['users_by_role']['admin']);
        $this->assertSame(1, $summary['users_by_role']['donor']);
        $this->assertSame(1, $summary['users_by_role']['ngo']);
        $this->assertSame(1, $summary['ngos_by_status']['verified']);
    }

    /**
     * T-11.2-02 (E): Empty range / zero accepted requests handles division-by-zero safely.
     */
    public function testEmptyRangeHandlesZeroDivision(): void
    {
        // Query future date with zero activity
        $future = date('Y-m-d H:i:s', time() + 864000);
        $summary = $this->reportSvc->getSummary($future, date('Y-m-d H:i:s', time() + 864100));

        $this->assertSame(0.0, $summary['completion_rate']);
        $this->assertSame(0, $summary['completed_handovers']);
    }

    /**
     * T-11.2-03 (V): Bad date range or range > 366 days is rejected.
     */
    public function testDateRangeValidation(): void
    {
        $this->expectException(ValidationFailedException::class);
        $this->reportSvc->getSummary('2024-01-01', '2026-01-01'); // > 366 days
    }

    /**
     * T-11.2-05 (P) & T-11.2-06 (S): Audit log filtering and zero-secret guarantee.
     */
    public function testAuditLogFilteringAndZeroSecrets(): void
    {
        // Trigger an audit log
        $this->userAdminSvc->updateStatus($this->adminId, $this->donorId, 'suspended', 'Audit test');

        $logs = $this->reportSvc->getAuditLogs(['action' => 'user.suspended']);
        $this->assertGreaterThanOrEqual(1, $logs['total']);
        $this->assertSame('user.suspended', $logs['items'][0]['action']);

        // Inspect metadata for absence of secrets
        foreach ($logs['items'] as $item) {
            $meta = $item['metadata'];
            $this->assertArrayNotHasKey('password', $meta);
            $this->assertArrayNotHasKey('otp', $meta);
            $this->assertArrayNotHasKey('token', $meta);
        }
    }

    /**
     * T-11.2-07 (P): Donor Dashboard returns accurate metrics.
     */
    public function testDonorDashboardReturnsAccurateMetrics(): void
    {
        $this->donationRepo->create([
            'donor_id'       => $this->donorId,
            'category_id'    => $this->categoryId,
            'title'          => 'Books',
            'description'    => 'Educational books',
            'total_quantity' => 15,
            'condition'      => 'good',
            'latitude'       => 40.7128,
            'longitude'      => -74.0060,
            'address_text'   => '100 Main St',
        ]);

        $dash = $this->reportSvc->getDashboard($this->donorId, 'donor');

        $this->assertSame('donor', $dash['role']);
        $this->assertSame(1, $dash['total_donations']);
        $this->assertSame(1, $dash['active_donations']);
        $this->assertSame(0, $dash['scheduled_pickups']);
    }

    /**
     * T-11.2-08 (P): NGO Dashboard returns accurate metrics.
     */
    public function testNgoDashboardReturnsAccurateMetrics(): void
    {
        $this->reqRepo->create([
            'ngo_id'          => $this->ngoId,
            'category_id'     => $this->categoryId,
            'title'           => 'Need textbooks',
            'description'     => 'School books',
            'quantity_needed' => 10,
            'latitude'        => 40.7128,
            'longitude'       => -74.0060,
        ]);

        $dash = $this->reportSvc->getDashboard($this->ngoUserId, 'ngo');

        $this->assertSame('ngo', $dash['role']);
        $this->assertSame(1, $dash['active_requirements']);
        $this->assertSame(0, $dash['pending_requests']);
    }

    /**
     * T-11.2-09 (S): Admin Dashboard metrics.
     */
    public function testAdminDashboardMetrics(): void
    {
        $dash = $this->reportSvc->getDashboard($this->adminId, 'admin');

        $this->assertSame('admin', $dash['role']);
        $this->assertSame(4, $dash['total_users']); // 2 admins + 1 donor + 1 ngo
        $this->assertSame(0, $dash['pending_ngos']); // already verified
    }

    /**
     * T-11.2-10 (E): Trends query returns bucketed results.
     */
    public function testTrendsQueryBuckets(): void
    {
        $this->donationRepo->create([
            'donor_id'       => $this->donorId,
            'category_id'    => $this->categoryId,
            'title'          => 'Clothing items',
            'description'    => 'Jackets and scarves',
            'total_quantity' => 10,
            'condition'      => 'good',
            'latitude'       => 40.7128,
            'longitude'      => -74.0060,
            'address_text'   => '100 Main St',
        ]);

        $trends = $this->reportSvc->getTrends('donations', 'week');
        $this->assertIsArray($trends);
        $this->assertNotEmpty($trends);
        $this->assertArrayHasKey('period', $trends[0]);
        $this->assertArrayHasKey('count', $trends[0]);
    }

    /**
     * T-11.2-11 (S): CSV injection formula prefix mitigation.
     */
    public function testCsvInjectionSanitization(): void
    {
        $headers = ['Title', 'Description'];
        $rows = [
            ['=cmd|"/C calc"!A0', '+12345', '-SUM(A1:A2)', '@link'],
        ];

        $csv = ReportService::formatCsv($headers, $rows);

        $this->assertStringContainsString("'=cmd", $csv);
        $this->assertStringContainsString("'+12345", $csv);
        $this->assertStringContainsString("'-SUM", $csv);
        $this->assertStringContainsString("'@link", $csv);
    }
}
