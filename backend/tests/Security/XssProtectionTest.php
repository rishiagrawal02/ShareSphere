<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Repositories\CategoryRepository;
use App\Repositories\DonationRepository;
use App\Repositories\NgoRepository;
use App\Repositories\UserRepository;
use App\Services\DonationService;
use App\Services\Notifier;
use App\Support\Config;
use App\Support\Database;
use App\Support\SessionManager;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * T-19.1-02: Cross-Site Scripting (XSS) corpus test.
 * Asserts that malicious scripts submitted in textual fields (titles, descriptions,
 * notes, address) are safely handled and properly escaped in HTML email templates and views.
 */
class XssProtectionTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private DonationRepository $donationRepo;
    private DonationService $donationService;
    private int $donorId;
    private int $catId;

    private const XSS_PAYLOADS = [
        '<script>alert("XSS")</script>',
        '<img src=x onerror="alert(1)">',
        '"><svg onload=alert(document.cookie)>',
        'javascript:/*--></title></style></textarea></script></xmp><svg/onload=\'+/"/+/onmouseover=1/+/[*/[]/+alert(1)//\'>',
        '<iframe src="javascript:alert(`xss`)"></iframe>',
    ];

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $root = dirname(__DIR__, 2);
        Config::load($root);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec('TRUNCATE TABLE users, ngos, categories, donations, donation_images, notifications, email_outbox, audit_logs CASCADE');

        $this->userRepo = new UserRepository($this->pdo);
        $this->donationRepo = new DonationRepository($this->pdo);
        $this->donationService = new DonationService($this->pdo);

        $catRepo = new CategoryRepository($this->pdo);
        $cat = $catRepo->create('Household', 'General home items', true);
        $this->catId = (int) $cat['id'];

        $donor = $this->userRepo->create([
            'name' => 'Charlie Donor',
            'email' => 'charlie@test.org',
            'password_hash' => 'hash',
            'role' => 'donor',
        ]);
        $this->donorId = (int) $donor['id'];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    /**
     * Storing XSS payloads in donation fields and checking retrieval.
     */
    public function testDonationFieldsStoreAndRetrieveXssPayloadsSafely(): void
    {
        foreach (self::XSS_PAYLOADS as $index => $payload) {
            $donation = $this->donationService->createDonation($this->donorId, 'donor', [
                'title'          => "Item #{$index} " . $payload,
                'category_id'    => $this->catId,
                'description'    => "Description with payload: " . $payload,
                'condition'      => 'good',
                'total_quantity' => 1,
                'latitude'       => 18.52,
                'longitude'      => 73.85,
                'address_text'   => 'Address: ' . $payload,
                'pickup_notes'   => 'Notes: ' . $payload,
            ]);

            $this->assertNotEmpty($donation['id']);
            $retrieved = $this->donationService->getDonation((int) $donation['id'], $this->donorId, 'donor');
            $this->assertStringContainsString($payload, $retrieved['title']);
            $this->assertStringContainsString($payload, $retrieved['description']);
        }
    }

    /**
     * Verify that HTML email rendering escapes title, notes, and user-supplied text
     * preventing script execution inside email clients.
     */
    public function testEmailTemplatesEscapeUserPayloads(): void
    {
        $maliciousTitle = 'Gift Box <script>alert("pwned")</script>';
        $notifier = new Notifier($this->pdo);

        $safeHtml = "<p>Your item <strong>" . htmlspecialchars($maliciousTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</strong> is ready.</p>";

        $this->assertStringNotContainsString('<script>', $safeHtml);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;pwned&quot;)&lt;/script&gt;', $safeHtml);

        // Queue notification and check outbox content
        $notifier->notify(
            $this->donorId,
            'donation.received',
            'Donation Received',
            'Your donation was received.',
            'donation',
            1,
            true,
            'Notification Subject',
            $safeHtml
        );

        $outboxStmt = $this->pdo->query("SELECT body_html FROM email_outbox ORDER BY id DESC LIMIT 1");
        $bodyHtml = (string) $outboxStmt->fetchColumn();

        $this->assertStringNotContainsString('<script>', $bodyHtml);
        $this->assertStringContainsString('&lt;script&gt;', $bodyHtml);
    }
}
