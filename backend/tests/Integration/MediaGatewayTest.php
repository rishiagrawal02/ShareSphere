<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\UnauthorizedException;
use App\Http\Request;
use App\Http\Router;
use App\Repositories\NgoRepository;
use App\Repositories\UserRepository;
use App\Services\FileStorage;
use App\Support\Config;
use App\Support\Database;
use App\Support\SessionManager;
use PDO;
use PHPUnit\Framework\TestCase;

class MediaGatewayTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private NgoRepository $ngoRepo;
    private FileStorage $fileStorage;
    private Router $router;
    private int $ngo1UserId;
    private int $ngo2UserId;
    private int $adminUserId;
    private int $docId;
    private string $tempFile;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $rootPath = dirname(__DIR__, 2);
        Config::load($rootPath);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec("TRUNCATE TABLE users, ngos, ngo_documents, audit_logs CASCADE");

        $this->userRepo = new UserRepository($this->pdo);
        $this->ngoRepo = new NgoRepository($this->pdo);
        $this->fileStorage = new FileStorage();

        $this->router = new Router();
        $routesFn = require dirname(__DIR__, 2) . '/src/routes.php';
        $routesFn($this->router);

        // Create NGO 1
        $u1 = $this->userRepo->create([
            'name' => 'NGO One',
            'email' => 'ngo1@example.com',
            'password_hash' => password_hash('Pass12345!', PASSWORD_DEFAULT),
            'role' => 'ngo',
        ]);
        $this->ngo1UserId = (int) $u1['id'];

        $ngo1 = $this->ngoRepo->createNgo([
            'user_id' => $this->ngo1UserId,
            'organization_name' => 'Org One',
            'registration_number' => 'REG-1',
            'address_text' => 'Address 1',
            'latitude' => 18.52,
            'longitude' => 73.85,
        ]);
        $ngo1Id = (int) $ngo1['id'];

        // Create NGO 2
        $u2 = $this->userRepo->create([
            'name' => 'NGO Two',
            'email' => 'ngo2@example.com',
            'password_hash' => password_hash('Pass12345!', PASSWORD_DEFAULT),
            'role' => 'ngo',
        ]);
        $this->ngo2UserId = (int) $u2['id'];

        // Create Admin
        $admin = $this->userRepo->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password_hash' => password_hash('Pass12345!', PASSWORD_DEFAULT),
            'role' => 'admin',
        ]);
        $this->adminUserId = (int) $admin['id'];

        // Store a test document for NGO 1
        $this->tempFile = tempnam(sys_get_temp_dir(), 'test_media_');
        file_put_contents($this->tempFile, "%PDF-1.4\nTest PDF content\n%%EOF");

        $stored = $this->fileStorage->store($this->tempFile, 'documents', 'pdf', 'application/pdf');
        $doc = $this->ngoRepo->addDocument($ngo1Id, $stored['storage_name'], $stored['mime'], $stored['size_bytes'], $stored['sha256']);
        $this->docId = (int) $doc['id'];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testUnauthenticatedMediaAccessThrowsUnauthorized(): void
    {
        $this->expectException(UnauthorizedException::class);
        $req = new Request('GET', "/api/media/ngo-documents/{$this->docId}");
        $this->router->dispatch($req);
    }

    public function testUnauthorizedUserAccessReturnsNotFound(): void
    {
        SessionManager::setUser($this->ngo2UserId, 'ngo');

        $this->expectException(NotFoundException::class);
        $req = new Request('GET', "/api/media/ngo-documents/{$this->docId}");
        $this->router->dispatch($req);
    }

    public function testOwningNgoAccessReturnsFileWithSecureHeaders(): void
    {
        SessionManager::setUser($this->ngo1UserId, 'ngo');

        $req = new Request('GET', "/api/media/ngo-documents/{$this->docId}");
        $res = $this->router->dispatch($req);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('application/pdf', $res->getHeader('content-type'));
        $this->assertSame('nosniff', $res->getHeader('x-content-type-options'));
        $this->assertStringContainsString('attachment', $res->getHeader('content-disposition'));
        $this->assertStringContainsString('Test PDF content', $res->getContent());
    }

    public function testAdminCanAccessAnyDocument(): void
    {
        SessionManager::setUser($this->adminUserId, 'admin');

        $req = new Request('GET', "/api/media/ngo-documents/{$this->docId}");
        $res = $this->router->dispatch($req);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('Test PDF content', $res->getContent());
    }
}
