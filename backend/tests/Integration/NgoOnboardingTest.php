<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Http\Exceptions\ForbiddenException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Middleware\VerifiedNgoMiddleware;
use App\Repositories\NgoRepository;
use App\Repositories\UserRepository;
use App\Services\NgoService;
use App\Services\NgoVerificationService;
use App\Support\Config;
use App\Support\Database;
use App\Support\SessionManager;
use PDO;
use PHPUnit\Framework\TestCase;

class NgoOnboardingTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private NgoRepository $ngoRepo;
    private NgoService $ngoService;
    private NgoVerificationService $verificationService;
    private Router $router;
    private string $tempDoc;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $rootPath = dirname(__DIR__, 2);
        Config::load($rootPath);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec("TRUNCATE TABLE users, ngos, ngo_documents, notifications, email_outbox, audit_logs CASCADE");

        $this->userRepo = new UserRepository($this->pdo);
        $this->ngoRepo = new NgoRepository($this->pdo);
        $this->ngoService = new NgoService($this->pdo);
        $this->verificationService = new NgoVerificationService($this->pdo);

        $this->router = new Router();
        $routesFn = require dirname(__DIR__, 2) . '/src/routes.php';
        $routesFn($this->router);

        $this->tempDoc = tempnam(sys_get_temp_dir(), 'ngo_doc_');
        file_put_contents($this->tempDoc, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        if (file_exists($this->tempDoc)) {
            unlink($this->tempDoc);
        }
    }

    public function testNgoRegistrationAndVerificationLifecycle(): void
    {
        // 1. Register NGO with document
        $input = [
            'name'                  => 'Hope Foundation Leader',
            'email'                 => 'hope@ngo.org',
            'password'              => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'organization_name'     => 'Hope Foundation',
            'registration_number'   => 'NGO-2026-999',
            'address_text'          => '100 Social Welfare Road',
            'latitude'              => '18.5204',
            'longitude'             => '73.8567',
            'service_radius_km'     => '30',
        ];

        $files = [
            'documents' => [
                'name'     => 'cert.pdf',
                'type'     => 'application/pdf',
                'tmp_name' => $this->tempDoc,
                'size'     => filesize($this->tempDoc),
                'error'    => 0,
            ],
        ];

        $result = $this->ngoService->registerNgo($input, $files);
        $userId = (int) $result['user']['id'];
        $ngoId  = (int) $result['ngo']['id'];

        $this->assertSame('pending', $result['ngo']['verification_status']);

        // Check document recorded
        $docs = $this->ngoRepo->listDocuments($ngoId);
        $this->assertCount(1, $docs);

        // 2. Pending NGO hits VerifiedNgoMiddleware -> blocked (403)
        $middleware = new VerifiedNgoMiddleware($this->userRepo);
        $req = new Request('GET', '/api/ngo/donations');
        $req->setAttribute('auth_user_id', $userId);
        $req->setAttribute('auth_role', 'ngo');

        try {
            $middleware->handle($req, fn($r) => Response::success(['ok' => true]));
            $this->fail('Expected ForbiddenException for pending NGO');
        } catch (ForbiddenException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame('NGO_NOT_VERIFIED', $e->getErrorCode());
        }

        // 3. Create Admin and Approve NGO
        $admin = $this->userRepo->create([
            'name'          => 'Super Admin',
            'email'         => 'admin@sharesphere.org',
            'password_hash' => password_hash('AdminPass123!', PASSWORD_DEFAULT),
            'role'          => 'admin',
        ]);
        $adminId = (int) $admin['id'];

        $verifiedNgo = $this->verificationService->processDecision($adminId, $ngoId, 'approve', null);
        $this->assertSame('verified', $verifiedNgo['verification_status']);

        // 4. NGO calls verified route immediately -> succeeds without relogin
        $res = $middleware->handle($req, fn($r) => Response::success(['ok' => true]));
        $this->assertSame(200, $res->getStatusCode());

        // 5. Admin suspends NGO -> blocked again
        $this->verificationService->processDecision($adminId, $ngoId, 'suspend', 'Violation of terms');

        try {
            $middleware->handle($req, fn($r) => Response::success(['ok' => true]));
            $this->fail('Expected ForbiddenException for suspended NGO');
        } catch (ForbiddenException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
