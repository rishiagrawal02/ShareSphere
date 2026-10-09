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
use App\Repositories\DonationRepository;
use App\Repositories\NgoRepository;
use App\Repositories\UserRepository;
use App\Services\DonationService;
use App\Support\Config;
use App\Support\CsrfGuard;
use App\Support\Database;
use App\Support\SessionManager;
use PDO;
use PHPUnit\Framework\TestCase;

class DonationTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private NgoRepository $ngoRepo;
    private CategoryRepository $categoryRepo;
    private DonationRepository $donationRepo;
    private DonationService $donationService;
    private Router $router;

    private int $donorId;
    private int $ngoUserId;
    private int $adminId;
    private int $categoryId;
    private string $tempImg;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $rootPath = dirname(__DIR__, 2);
        Config::load($rootPath);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec("TRUNCATE TABLE users, ngos, categories, donations, donation_images, allocations, notifications, audit_logs CASCADE");

        $this->userRepo = new UserRepository($this->pdo);
        $this->ngoRepo = new NgoRepository($this->pdo);
        $this->categoryRepo = new CategoryRepository($this->pdo);
        $this->donationRepo = new DonationRepository($this->pdo);
        $this->donationService = new DonationService($this->pdo);

        $this->router = new Router();
        $routesFn = require dirname(__DIR__, 2) . '/src/routes.php';
        $routesFn($this->router);

        // Seed Donor
        $donor = $this->userRepo->create([
            'name'          => 'Dave Donor',
            'email'         => 'dave@donor.org',
            'password_hash' => password_hash('DonorPass123!', PASSWORD_DEFAULT),
            'role'          => 'donor',
        ]);
        $this->donorId = (int) $donor['id'];

        // Seed Verified NGO
        $ngoUser = $this->userRepo->create([
            'name'          => 'Care NGO Rep',
            'email'         => 'care@ngo.org',
            'password_hash' => password_hash('NgoPass123!', PASSWORD_DEFAULT),
            'role'          => 'ngo',
        ]);
        $this->ngoUserId = (int) $ngoUser['id'];

        $this->ngoRepo->createNgo([
            'user_id'             => $this->ngoUserId,
            'organization_name'   => 'Care NGO',
            'registration_number' => 'CARE-123',
            'address_text'        => 'Care Center',
            'latitude'            => 18.52,
            'longitude'           => 73.85,
            'verification_status' => 'verified',
        ]);

        // Seed Admin
        $admin = $this->userRepo->create([
            'name'          => 'Site Admin',
            'email'         => 'admin@sharesphere.org',
            'password_hash' => password_hash('AdminPass123!', PASSWORD_DEFAULT),
            'role'          => 'admin',
        ]);
        $this->adminId = (int) $admin['id'];

        // Seed Category
        $cat = $this->categoryRepo->create('Winter Clothes', 'Jackets, coats and thermal wear', true);
        $this->categoryId = (int) $cat['id'];

        // Create a dummy image
        $this->tempImg = tempnam(sys_get_temp_dir(), 'test_img_') . '.jpg';
        $gd = imagecreatetruecolor(10, 10);
        imagejpeg($gd, $this->tempImg);
        imagedestroy($gd);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        if (file_exists($this->tempImg)) {
            unlink($this->tempImg);
        }
    }

    public function testDonorPublishesDonationSuccessfully(): void
    {
        $input = [
            'title'          => '20 Warm Jackets',
            'category_id'    => $this->categoryId,
            'description'    => 'Gently used winter jackets in various sizes.',
            'condition'      => 'good',
            'total_quantity' => 20,
            'latitude'       => 18.520432,
            'longitude'      => 73.856743,
            'address_text'   => 'Flat 402, Sunshine Heights, Pune',
            'pickup_notes'   => 'Ring doorbell twice',
        ];

        $donation = $this->donationService->createDonation($this->donorId, 'donor', $input);

        $this->assertNotEmpty($donation['id']);
        $this->assertSame('20 Warm Jackets', $donation['title']);
        $this->assertSame(20, $donation['total_quantity']);
        $this->assertSame(20, $donation['available_quantity']);
        $this->assertSame('active', $donation['status']);
        $this->assertSame(18.520432, $donation['latitude']);
        $this->assertSame(18.52, $donation['latitude_public']); // Snapped
    }

    public function testNgoCannotPublishDonation(): void
    {
        $input = [
            'title'          => 'Invalid Donation',
            'category_id'    => $this->categoryId,
            'condition'      => 'new',
            'total_quantity' => 10,
            'latitude'       => 18.52,
            'longitude'      => 73.85,
            'address_text'   => 'Address',
        ];

        $this->expectException(ForbiddenException::class);
        $this->donationService->createDonation($this->ngoUserId, 'ngo', $input);
    }

    public function testPublicNgoViewHidesExactCoordinatesAndAddress(): void
    {
        $donation = $this->donationService->createDonation($this->donorId, 'donor', [
            'title'          => 'Thermal Blankets',
            'category_id'    => $this->categoryId,
            'condition'      => 'new',
            'total_quantity' => 50,
            'latitude'       => 18.520432,
            'longitude'      => 73.856743,
            'address_text'   => 'Exact Home Address 123',
            'pickup_notes'   => 'Secret pickup code',
        ]);

        $publicView = $this->donationService->getDonation((int)$donation['id'], $this->ngoUserId, 'ngo');

        $this->assertSame('Thermal Blankets', $publicView['title']);
        $this->assertArrayNotHasKey('address_text', $publicView);
        $this->assertArrayNotHasKey('latitude', $publicView);
        $this->assertArrayNotHasKey('longitude', $publicView);
        $this->assertArrayNotHasKey('pickup_notes', $publicView);

        $this->assertSame(18.52, $publicView['latitude_public']);
        $this->assertSame(73.855, $publicView['longitude_public']);
    }

    public function testUpdateQuantityRules(): void
    {
        $donation = $this->donationService->createDonation($this->donorId, 'donor', [
            'title'          => 'Blankets',
            'category_id'    => $this->categoryId,
            'condition'      => 'good',
            'total_quantity' => 20,
            'latitude'       => 18.52,
            'longitude'      => 73.85,
            'address_text'   => 'Address',
        ]);
        $id = (int) $donation['id'];

        // Increase total quantity: 20 -> 30 (available becomes 30)
        $updated = $this->donationService->updateDonation($this->donorId, $id, ['total_quantity' => 30]);
        $this->assertSame(30, $updated['total_quantity']);
        $this->assertSame(30, $updated['available_quantity']);

        // Simulate allocation of 10 items (available becomes 20)
        $this->pdo->exec("UPDATE donations SET available_quantity = 20 WHERE id = {$id}");

        // Attempt to reduce total to 8 (allocated is 30 - 20 = 10, so 8 < 10 should fail)
        $this->expectException(ConflictException::class);
        $this->donationService->updateDonation($this->donorId, $id, ['total_quantity' => 8]);
    }

    public function testCloseDonationWithAndWithoutAllocations(): void
    {
        $donation = $this->donationService->createDonation($this->donorId, 'donor', [
            'title'          => 'Shoes',
            'category_id'    => $this->categoryId,
            'condition'      => 'good',
            'total_quantity' => 10,
            'latitude'       => 18.52,
            'longitude'      => 73.85,
            'address_text'   => 'Address',
        ]);
        $id = (int) $donation['id'];

        // Close without allocations succeeds
        $closed = $this->donationService->closeDonation($this->donorId, $id);
        $this->assertSame('closed', $closed['status']);
    }

    public function testImageUploadAndDeletion(): void
    {
        $donation = $this->donationService->createDonation($this->donorId, 'donor', [
            'title'          => 'Clothes',
            'category_id'    => $this->categoryId,
            'condition'      => 'good',
            'total_quantity' => 10,
            'latitude'       => 18.52,
            'longitude'      => 73.85,
            'address_text'   => 'Address',
        ]);
        $id = (int) $donation['id'];

        $files = [
            'images' => [
                'name'     => 'photo.jpg',
                'type'     => 'image/jpeg',
                'tmp_name' => $this->tempImg,
                'size'     => filesize($this->tempImg),
                'error'    => 0,
            ],
        ];

        $images = $this->donationService->uploadImages($this->donorId, $id, $files);
        $this->assertCount(1, $images);
        $imageId = (int) $images[0]['id'];

        // Delete image
        $this->donationService->deleteImage($this->donorId, $id, $imageId);
        $this->assertSame(0, $this->donationRepo->countImages($id));
    }
}
