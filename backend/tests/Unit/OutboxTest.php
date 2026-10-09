<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Adapters\Mail\FakeMailer;
use App\Http\Exceptions\ValidationFailedException;
use App\Services\Outbox;
use App\Support\Config;
use App\Support\Database;
use PDO;
use PHPUnit\Framework\TestCase;

class OutboxTest extends TestCase
{
    private PDO $pdo;
    private Outbox $outbox;
    private FakeMailer $mailer;

    protected function setUp(): void
    {
        $rootPath = dirname(__DIR__, 2);
        Config::load($rootPath);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec("TRUNCATE TABLE email_outbox CASCADE");

        $this->outbox = new Outbox($this->pdo);
        $this->mailer = new FakeMailer();
    }

    public function testHeaderInjectionThrowsValidationException(): void
    {
        $this->expectException(ValidationFailedException::class);
        $this->outbox->enqueue("attacker@example.com\r\nBcc:victim@example.com", "Subject", "Body");
    }

    public function testSubjectHeaderInjectionThrowsValidationException(): void
    {
        $this->expectException(ValidationFailedException::class);
        $this->outbox->enqueue("user@example.com", "Subject\nBcc:victim@example.com", "Body");
    }

    public function testInvalidEmailRecipientThrowsValidationException(): void
    {
        $this->expectException(ValidationFailedException::class);
        $this->outbox->enqueue("not-an-email", "Subject", "Body");
    }

    public function testEnqueueAndProcessOutboxSuccessfully(): void
    {
        $id = $this->outbox->enqueue("recipient@example.com", "Test Subject", "Hello Plain Text", "<p>Hello HTML</p>");
        $this->assertGreaterThan(0, $id);

        $sentCount = $this->outbox->sendPending($this->mailer, 10);
        $this->assertSame(1, $sentCount);
        $this->assertSame(1, $this->mailer->count());

        $sentMails = $this->mailer->getSent();
        $this->assertSame("recipient@example.com", $sentMails[0]['to']);
        $this->assertSame("Test Subject", $sentMails[0]['subject']);
        $this->assertSame("Hello Plain Text", $sentMails[0]['text']);
        $this->assertSame("<p>Hello HTML</p>", $sentMails[0]['html']);

        // Check DB row status is sent
        $stmt = $this->pdo->query("SELECT status, attempts FROM email_outbox WHERE id = {$id}");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('sent', $row['status']);
        $this->assertSame(1, (int)$row['attempts']);
    }

    public function testOutboxHandlesFailureAndExponentialBackoff(): void
    {
        $id = $this->outbox->enqueue("fail@example.com", "Failing Email", "Body");

        $this->mailer->setShouldFail(true, "SMTP connection timeout");

        $sentCount = $this->outbox->sendPending($this->mailer, 10, 3);
        $this->assertSame(0, $sentCount);

        // Check DB row: still pending, attempts = 1, error recorded
        $stmt = $this->pdo->query("SELECT status, attempts, last_error FROM email_outbox WHERE id = {$id}");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('pending', $row['status']);
        $this->assertSame(1, (int)$row['attempts']);
        $this->assertStringContainsString('SMTP connection timeout', $row['last_error']);
    }

    public function testOutboxMarksFailedAfterMaxAttempts(): void
    {
        $id = $this->outbox->enqueue("fail_max@example.com", "Failing Max", "Body");

        // Manually set attempts to 4
        $this->pdo->exec("UPDATE email_outbox SET attempts = 4 WHERE id = {$id}");

        $this->mailer->setShouldFail(true, "Permanent failure");

        $sentCount = $this->outbox->sendPending($this->mailer, 10, 5);
        $this->assertSame(0, $sentCount);

        $stmt = $this->pdo->query("SELECT status, attempts FROM email_outbox WHERE id = {$id}");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('failed', $row['status']);
        $this->assertSame(5, (int)$row['attempts']);
    }
}
