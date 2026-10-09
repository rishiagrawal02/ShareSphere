<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\ValidationFailedException;
use App\Http\Request;
use App\Http\Router;
use App\Repositories\CategoryRepository;
use App\Repositories\NgoRepository;
use App\Repositories\RequirementRepository;
use App\Repositories\UserRepository;
use App\Services\RequirementService;
use App\Support\Config;
use App\Support\Database;
use App\Support\SessionManager;
use PDO;
use PHPUnit\Framework\TestCase;

class RequirementTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private NgoRepository $ngoRepo;
    private CategoryRepository $categoryRepo;
    private RequirementRepository $reqRepo;
    private RequirementService $reqService;
    private Router $router;

    private int $ngo1UserId;
    private int $ngo2UserId;
    private int $pendingNgoUserId;
    private int $donorUserId;
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
        $this->pdo->exec("TRUNCATE TABLE users, ngos, categories, ngo_requirements, allocations, audit_logs CASCADE");

        $this->userRepo = new UserRepository($this->pdo);
        $this->ngoRepo = new NgoRepository($this->pdo);
        $this->categoryRepo = new CategoryRepository($this->pdo);
        $this->reqRepo = new RequirementRepository($this->pdo);
        $this->reqService = new RequirementService($this->pdo);

        $this->router = new Router();
        $routesFn = require dirname(__DIR__, 2) . '/src/routes.php';
        $routesFn($this->router);

        // 1. Verified NGO 1
        $u1 = $this->userRepo->create([
            'name'          => 'Relief Leader',
            'email'         => 'relief@ngo.org',
            'password_hash' => password_hash('Pass12345!', PASSWORD_DEFAULT),
            'role'          => 'ngo',
        ]);
        $this->ngo1UserId = (int) $u1['id'];
        $this->ngoRepo->createNgo([
            'user_id'             => $this->ngo1UserId,
            'organization_name'   => 'Relief NGO',
            'registration_number' => 'RN-1',
            'address_text'        => 'Relief Street',
            'latitude'            => 18.52,
            'longitude'           => 73.85,
            'verification_status' => 'verified',
        ]);

        // 2. Verified NGO 2
        $u2 = $this->userRepo->create([
            'name'          => 'Hope Leader',
            'email'         => 'hope@ngo.org',
            'password_hash' => password_hash('Pass12345!', PASSWORD_DEFAULT),
            'role'          => 'ngo',
        ]);
        $this->ngo2UserId = (int) $u2['id'];
        $this->ngoRepo->createNgo([
            'user_id'             => $this->ngo2UserId,
            'organization_name'   => 'Hope NGO',
            'registration_number' => 'RN-2',
            'address_text'        => 'Hope Lane',
            'latitude'            => 18.53,
            'longitude'           => 73.86,
            'verification_status' => 'verified',
        ]);

        // 3. Pending NGO
        $u3 = $this->userRepo->create([
            'name'          => 'Pending Leader',
            'email'         => 'pending@ngo.org',
            'password_hash' => password_hash('Pass12345!', PASSWORD_DEFAULT),
            'role'          => 'ngo',
        ]);
        $this->pendingNgoUserId = (int) $u3['id'];
        $this->ngoRepo->createNgo([
            'user_id'             => $this->pendingNgoUserId,
            'organization_name'   => 'Pending NGO',
            'registration_number' => 'RN-3',
            'address_text'        => 'Pending Road',
            'latitude'            => 18.54,
            'longitude'           => 73.87,
            'verification_status' => 'pending',
        ]);

        // 4. Donor
        $donor = $this->userRepo->create([
            'name'          => 'Sam Donor',
            'email'         => 'sam@donor.org',
            'password_hash' => password_hash('Pass12345!', PASSWORD_DEFAULT),
            'role'          => 'donor',
        ]);
        $this->donorUserId = (int) $donor['id'];

        // 5. Category
        $cat = $this->categoryRepo->create('Winter Clothes', 'Jackets and blankets', true);
        $this->categoryId = (int) $cat['id'];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    public function testVerifiedNgoCreatesRequirementSuccessfully(): void
    {
        $input = [
            'title'           => '12 Winter Jackets for Shelter',
            'category_id'     => $this->categoryId,
            'description'     => 'Urgent requirement for homeless shelter during winter.',
            'quantity_needed' => 12,
            'urgency'         => 'high',
            'min_condition'   => 'good',
            'radius_km'       => 20,
        ];

        $req = $this->reqService->createRequirement($this->ngo1UserId, $input);

        $this->assertNotEmpty($req['id']);
        $this->assertSame('12 Winter Jackets for Shelter', $req['title']);
        $this->assertSame(12, $req['quantity_needed']);
        $this->assertSame(0, $req['quantity_allocated']);
        $this->assertSame(0, $req['quantity_fulfilled']);
        $this->assertSame(12, $req['outstanding']);
        $this->assertSame('high', $req['urgency']);
        $this->assertSame('good', $req['min_condition']);
        $this->assertSame('active', $req['status']);
    }

    public function testPendingNgoCannotCreateRequirement(): void
    {
        $input = [
            'title'           => 'Jackets',
            'category_id'     => $this->categoryId,
            'quantity_needed' => 10,
            'urgency'         => 'medium',
        ];

        $this->expectException(ForbiddenException::class);
        $this->reqService->createRequirement($this->pendingNgoUserId, $input);
    }

    public function testDonorCannotCreateRequirement(): void
    {
        $input = [
            'title'           => 'Jackets',
            'category_id'     => $this->categoryId,
            'quantity_needed' => 10,
            'urgency'         => 'medium',
        ];

        $this->expectException(NotFoundException::class); // No NGO profile found for donor
        $this->reqService->createRequirement($this->donorUserId, $input);
    }

    public function testAnotherNgoCannotAccessOrModifyRequirement(): void
    {
        $req = $this->reqService->createRequirement($this->ngo1UserId, [
            'title'           => 'Blankets',
            'category_id'     => $this->categoryId,
            'quantity_needed' => 20,
            'urgency'         => 'low',
        ]);
        $id = (int) $req['id'];

        // NGO 2 tries to GET
        $this->expectException(NotFoundException::class);
        $this->reqService->getRequirement($this->ngo2UserId, 'ngo', $id);
    }

    public function testCannotReduceQuantityNeededBelowAllocated(): void
    {
        $req = $this->reqService->createRequirement($this->ngo1UserId, [
            'title'           => 'Blankets',
            'category_id'     => $this->categoryId,
            'quantity_needed' => 20,
            'urgency'         => 'low',
        ]);
        $id = (int) $req['id'];

        // Simulate 10 items allocated via DB
        $this->pdo->exec("UPDATE ngo_requirements SET quantity_allocated = 10 WHERE id = {$id}");

        // Attempt to reduce quantity_needed to 5 (below 10) -> Conflict
        $this->expectException(ConflictException::class);
        $this->reqService->updateRequirement($this->ngo1UserId, $id, ['quantity_needed' => 5]);
    }

    public function testCloseRequirementAndPreventEdits(): void
    {
        $req = $this->reqService->createRequirement($this->ngo1UserId, [
            'title'           => 'Rations',
            'category_id'     => $this->categoryId,
            'quantity_needed' => 50,
            'urgency'         => 'critical',
        ]);
        $id = (int) $req['id'];

        $closed = $this->reqService->closeRequirement($this->ngo1UserId, $id);
        $this->assertSame('closed', $closed['status']);

        // Attempt to edit closed requirement -> Conflict
        $this->expectException(ConflictException::class);
        $this->reqService->updateRequirement($this->ngo1UserId, $id, ['title' => 'Updated Title']);
    }

    public function testListNgoRequirementsWithPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->reqService->createRequirement($this->ngo1UserId, [
                'title'           => "Need {$i}",
                'category_id'     => $this->categoryId,
                'quantity_needed' => 10 * $i,
                'urgency'         => 'medium',
            ]);
        }

        $items = $this->reqService->listNgoRequirements($this->ngo1UserId, 'active', null, 3, 0);
        $this->assertCount(3, $items);
        $this->assertSame(5, $this->reqService->countNgoRequirements($this->ngo1UserId, 'active', null));
    }
}
