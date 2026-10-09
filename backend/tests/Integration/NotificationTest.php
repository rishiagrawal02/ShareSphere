<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\UnauthorizedException;
use App\Http\Request;
use App\Http\Router;
use App\Repositories\UserRepository;
use App\Services\Notifier;
use App\Support\Config;
use App\Support\CsrfGuard;
use App\Support\Database;
use App\Support\SessionManager;
use PDO;
use PHPUnit\Framework\TestCase;

class NotificationTest extends TestCase
{
    private PDO $pdo;
    private Notifier $notifier;
    private UserRepository $userRepo;
    private Router $router;
    private int $user1Id;
    private int $user2Id;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $rootPath = dirname(__DIR__, 2);
        Config::load($rootPath);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec("TRUNCATE TABLE users, notifications, email_outbox CASCADE");

        $this->userRepo = new UserRepository($this->pdo);
        $this->notifier = new Notifier($this->pdo);

        $user1 = $this->userRepo->create([
            'name' => 'User One',
            'email' => 'user1@example.com',
            'password_hash' => password_hash('Pass12345!', PASSWORD_DEFAULT),
            'role' => 'donor',
            'account_status' => 'active',
        ]);
        $this->user1Id = (int) $user1['id'];

        $user2 = $this->userRepo->create([
            'name' => 'User Two',
            'email' => 'user2@example.com',
            'password_hash' => password_hash('Pass12345!', PASSWORD_DEFAULT),
            'role' => 'donor',
            'account_status' => 'active',
        ]);
        $this->user2Id = (int) $user2['id'];

        $this->router = new Router();
        $routesFn = require dirname(__DIR__, 2) . '/src/routes.php';
        $routesFn($this->router);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    public function testNotifyCreatesInAppNotificationAndEmailOutbox(): void
    {
        $notif = $this->notifier->notify(
            $this->user1Id,
            Notifier::TYPE_REQUEST_CREATED,
            'New Request Created',
            'An NGO requested your donation item.',
            'donations',
            10,
            true, // emailToo
            'New Donation Request',
            '<p>HTML Body</p>'
        );

        $this->assertNotEmpty($notif['id']);
        $this->assertSame($this->user1Id, (int)$notif['user_id']);
        $this->assertNull($notif['read_at']);

        // Verify outbox row created
        $stmt = $this->pdo->query("SELECT to_email, subject, body_text FROM email_outbox WHERE to_email = 'user1@example.com'");
        $outboxRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($outboxRow);
        $this->assertSame('New Donation Request', $outboxRow['subject']);
    }

    public function testListNotificationsAuthenticated(): void
    {
        // Create 2 notifs for user1, 1 for user2
        $this->notifier->notify($this->user1Id, Notifier::TYPE_REQUEST_CREATED, 'N1', 'B1');
        $n2 = $this->notifier->notify($this->user1Id, Notifier::TYPE_REQUEST_ACCEPTED, 'N2', 'B2');
        $this->notifier->notify($this->user2Id, Notifier::TYPE_REQUEST_REJECTED, 'N3', 'B3');

        // Mark N2 as read
        $this->notifier->markAsRead($this->user1Id, (int)$n2['id']);

        SessionManager::setUser($this->user1Id, 'donor');

        // Query all
        $req = new Request('GET', '/api/notifications');
        $res = $this->router->dispatch($req);
        $data = json_decode($res->getContent(), true);

        $this->assertSame('ok', $data['status']);
        $this->assertCount(2, $data['data']);
        $this->assertSame(2, $data['meta']['total_records']);

        // Query unread only
        $reqUnread = new Request('GET', '/api/notifications', ['unread' => '1']);
        $resUnread = $this->router->dispatch($reqUnread);
        $dataUnread = json_decode($resUnread->getContent(), true);

        $this->assertSame('ok', $dataUnread['status']);
        $this->assertCount(1, $dataUnread['data']);
        $this->assertSame('N1', $dataUnread['data'][0]['title']);
    }

    public function testMarkOtherUserNotificationAsReadThrowsNotFound(): void
    {
        $notifUser2 = $this->notifier->notify($this->user2Id, Notifier::TYPE_REQUEST_CREATED, 'Secret', 'Body');

        SessionManager::setUser($this->user1Id, 'donor');
        $token = CsrfGuard::getToken();

        $req = new Request(
            'POST',
            "/api/notifications/{$notifUser2['id']}/read",
            [],
            ['x-csrf-token' => $token]
        );

        $this->expectException(NotFoundException::class);
        $this->router->dispatch($req);
    }

    public function testMarkAllAsRead(): void
    {
        $this->notifier->notify($this->user1Id, Notifier::TYPE_REQUEST_CREATED, 'N1', 'B1');
        $this->notifier->notify($this->user1Id, Notifier::TYPE_REQUEST_ACCEPTED, 'N2', 'B2');

        SessionManager::setUser($this->user1Id, 'donor');
        $token = CsrfGuard::getToken();

        $req = new Request(
            'POST',
            '/api/notifications/read-all',
            [],
            ['x-csrf-token' => $token]
        );

        $res = $this->router->dispatch($req);
        $data = json_decode($res->getContent(), true);

        $this->assertSame('ok', $data['status']);
        $this->assertSame(2, $data['count']);

        // Ensure count of unread is now 0
        $unreadCount = $this->notifier->countForUser($this->user1Id, true);
        $this->assertSame(0, $unreadCount);
    }

    public function testAnonymousAccessDenied(): void
    {
        $this->expectException(UnauthorizedException::class);
        $req = new Request('GET', '/api/notifications');
        $this->router->dispatch($req);
    }
}
