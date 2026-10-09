<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Http\Exceptions\ConflictException;
use App\Repositories\CategoryRepository;
use App\Repositories\UserRepository;
use App\Services\CategoryService;
use App\Support\Config;
use App\Support\Database;
use PDO;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
    private PDO $pdo;
    private CategoryRepository $categoryRepo;
    private CategoryService $categoryService;
    private int $adminId;

    protected function setUp(): void
    {
        $rootPath = dirname(__DIR__, 2);
        Config::load($rootPath);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec("TRUNCATE TABLE users, categories, category_compatibility, donations, ngo_requirements, audit_logs CASCADE");

        $this->categoryRepo = new CategoryRepository($this->pdo);
        $this->categoryService = new CategoryService($this->categoryRepo);

        $userRepo = new UserRepository($this->pdo);
        $admin = $userRepo->create([
            'name'          => 'Category Admin',
            'email'         => 'catadmin@sharesphere.org',
            'password_hash' => password_hash('AdminPass123!', PASSWORD_DEFAULT),
            'role'          => 'admin',
        ]);
        $this->adminId = (int) $admin['id'];
    }

    public function testCategoryCreationAndPublicListing(): void
    {
        $cat1 = $this->categoryService->createCategory($this->adminId, [
            'name'        => 'Clothing & Apparel',
            'description' => 'Shirts, jackets, trousers, shoes',
            'is_active'   => true,
        ]);

        $cat2 = $this->categoryService->createCategory($this->adminId, [
            'name'        => 'Defunct Category',
            'description' => 'Inactive items',
            'is_active'   => false,
        ]);

        $publicList = $this->categoryService->listPublicCategories();
        $this->assertCount(1, $publicList);
        $this->assertSame('Clothing & Apparel', $publicList[0]['name']);
    }

    public function testDuplicateCategoryNameThrowsConflict(): void
    {
        $this->categoryService->createCategory($this->adminId, ['name' => 'Blankets']);

        $this->expectException(ConflictException::class);
        $this->categoryService->createCategory($this->adminId, ['name' => 'blankets']);
    }

    public function testSymmetricCompatibilityMapping(): void
    {
        $cat1 = $this->categoryService->createCategory($this->adminId, ['name' => 'Rice & Grains']);
        $cat2 = $this->categoryService->createCategory($this->adminId, ['name' => 'Dry Rations']);

        $id1 = (int) $cat1['id'];
        $id2 = (int) $cat2['id'];

        $this->categoryService->setCompatibility($this->adminId, $id1, [
            ['compatible_category_id' => $id2, 'score_factor' => 85.0],
        ]);

        // Verify compatibility is readable from both sides
        $compat1 = $this->categoryRepo->getCompatibility($id1);
        $this->assertCount(1, $compat1);
        $this->assertSame($id2, (int)$compat1[0]['compatible_category_id']);
        $this->assertEquals(85.0, (float)$compat1[0]['score_factor']);

        $compat2 = $this->categoryRepo->getCompatibility($id2);
        $this->assertCount(1, $compat2);
        $this->assertSame($id1, (int)$compat2[0]['compatible_category_id']);
        $this->assertEquals(85.0, (float)$compat2[0]['score_factor']);
    }

    public function testDeactivatingCategoryWithActiveDonationsFails(): void
    {
        $cat = $this->categoryService->createCategory($this->adminId, ['name' => 'Medicines']);
        $catId = (int) $cat['id'];

        // Create a user and donation referencing this category
        $userRepo = new UserRepository($this->pdo);
        $donor = $userRepo->create([
            'name'          => 'Donor Meds',
            'email'         => 'donor.meds@example.com',
            'password_hash' => password_hash('DonorPass123!', PASSWORD_DEFAULT),
            'role'          => 'donor',
        ]);

        $this->pdo->exec("
            INSERT INTO donations (donor_id, category_id, title, description, condition, total_quantity, available_quantity, status, address_text, location, location_public)
            VALUES ({$donor['id']}, {$catId}, 'First Aid Kits', 'Desc', 'new', 10, 10, 'active', 'Address', ST_SetSRID(ST_MakePoint(73.85, 18.52), 4326)::geography, ST_SetSRID(ST_MakePoint(73.85, 18.52), 4326)::geography)
        ");

        $this->expectException(ConflictException::class);
        $this->categoryService->updateCategory($this->adminId, $catId, ['is_active' => false]);
    }
}
