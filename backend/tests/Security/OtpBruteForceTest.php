<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Http\Exceptions\TooManyRequestsException;
use App\Http\Exceptions\ValidationFailedException;
use App\Repositories\AllocationRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\DonationRepository;
use App\Repositories\NgoRepository;
use App\Repositories\PickupRepository;
use App\Repositories\RequestRepository;
use App\Repositories\UserRepository;
use App\Services\DonationService;
use App\Services\OtpService;
use App\Support\Config;
use App\Support\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * T-19.1-09: OTP brute-force resistance, 5-attempt lockout, and rate limiting.
 * Verifies that random brute-force guesses lock the code after 5 failures,
 * reissues are capped at 3 per 24 hours, and plaintext OTP is never logged or stored.
 */
class OtpBruteForceTest extends TestCase
{
    private PDO $pdo;
    private OtpService $otpService;
    private PickupRepository $pickupRepo;
    private int $donorId;
    private int $ngoUserId;
    private int $pickupId;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        $root = dirname(__DIR__, 2);
        Config::load($root);
        $this->pdo = Database::getOwnerConnection();
        $this->pdo->exec('TRUNCATE TABLE users, ngos, categories, donations, donation_requests, allocations, pickups, notifications, email_outbox, audit_logs CASCADE');

        $userRepo = new UserRepository($this->pdo);
        $ngoRepo = new NgoRepository($this->pdo);
        $catRepo = new CategoryRepository($this->pdo);
        $reqRepo = new RequestRepository($this->pdo);
        $allocRepo = new AllocationRepository($this->pdo);
        $donationService = new DonationService($this->pdo);
        $this->pickupRepo = new PickupRepository($this->pdo);
        $this->otpService = new OtpService($this->pdo);

        // Donor
        $donor = $userRepo->create(['name' => 'Fiona Donor', 'email' => 'fiona@test.org', 'password_hash' => 'h', 'role' => 'donor']);
        $this->donorId = (int) $donor['id'];

        // NGO
        $ngoUser = $userRepo->create(['name' => 'Hope Rep', 'email' => 'hope@ngo.org', 'password_hash' => 'h', 'role' => 'ngo']);
        $this->ngoUserId = (int) $ngoUser['id'];
        $ngo = $ngoRepo->createNgo([
            'user_id' => $this->ngoUserId,
            'organization_name' => 'Hope Shelter',
            'registration_number' => 'HOPE-77',
            'address_text' => 'Shelter Rd',
            'latitude' => 18.52,
            'longitude' => 73.85,
            'verification_status' => 'verified',
        ]);
        $ngoId = (int) $ngo['id'];

        $cat = $catRepo->create('Kitchen', 'Cookware', true);
        $donation = $donationService->createDonation($this->donorId, 'donor', [
            'title' => 'Pots & Pans',
            'category_id' => (int) $cat['id'],
            'description' => 'Cooking set',
            'condition' => 'good',
            'total_quantity' => 1,
            'latitude' => 18.52,
            'longitude' => 73.85,
            'address_text' => 'Donor Home',
        ]);
        $donationId = (int) $donation['id'];

        $reqId = $reqRepo->create([
            'donation_id' => $donationId,
            'ngo_id' => $ngoId,
            'requested_quantity' => 1,
            'status' => 'accepted',
        ]);

        $allocId = $allocRepo->create([
            'request_id' => $reqId,
            'donation_id' => $donationId,
            'allocated_quantity' => 1,
            'status' => 'confirmed',
        ]);

        $this->pickupId = $this->pickupRepo->create([
            'allocation_id' => $allocId,
            'proposed_by' => $this->ngoUserId,
            'scheduled_at' => date('Y-m-d H:i:s', time() + 3600),
            'location_details' => 'Front porch',
            'state' => 'scheduled',
        ]);
    }

    /**
     * T-19.1-09: 5 failed guesses triggers OTP lockout and prevents further attempts.
     */
    public function testOtpLocksAfterFiveFailedAttempts(): void
    {
        $this->otpService->issue($this->pickupId, $this->ngoUserId);

        // Perform 4 incorrect guesses
        for ($i = 1; $i <= 4; $i++) {
            try {
                $this->otpService->verify($this->pickupId, $this->donorId, sprintf('%06d', $i));
                $this->fail('Incorrect OTP guess should have thrown exception');
            } catch (ValidationFailedException $e) {
                $this->assertStringContainsString('Invalid pickup code', $e->getFields()['otp'] ?? '');
            }

            $pickup = $this->pickupRepo->findById($this->pickupId);
            $this->assertSame($i, (int) $pickup['otp_attempts']);
            $this->assertFalse((bool) $pickup['otp_locked']);
        }

        // 5th incorrect guess locks the OTP
        try {
            $this->otpService->verify($this->pickupId, $this->donorId, '000005');
            $this->fail('5th incorrect guess should trigger lockout exception');
        } catch (ValidationFailedException $e) {
            $this->assertStringContainsString('locked', strtolower($e->getFields()['otp'] ?? ''));
        }

        $lockedPickup = $this->pickupRepo->findById($this->pickupId);
        $this->assertSame(5, (int) $lockedPickup['otp_attempts']);
        $this->assertTrue((bool) $lockedPickup['otp_locked'], 'Pickup OTP must be locked');

        // 6th attempt is blocked by lockout guard
        try {
            $this->otpService->verify($this->pickupId, $this->donorId, '000006');
            $this->fail('6th attempt should be blocked by lockout guard');
        } catch (ValidationFailedException $e) {
            $this->assertStringContainsString('locked due to too many failed attempts', $e->getFields()['otp'] ?? '');
        }
    }

    /**
     * T-19.1-09: Reissue rate limit prevents issuing more than 3 codes per 24 hours.
     */
    public function testOtpReissueRateLimitEnforced(): void
    {
        // 1st issue
        $this->otpService->issue($this->pickupId, $this->ngoUserId);
        // 2nd reissue
        $this->otpService->issue($this->pickupId, $this->ngoUserId);
        // 3rd reissue
        $this->otpService->issue($this->pickupId, $this->ngoUserId);

        // 4th issue in 24h must be rejected with 429 TooManyRequestsException
        $this->expectException(TooManyRequestsException::class);
        $this->expectExceptionMessage('Maximum OTP reissues reached');
        $this->otpService->issue($this->pickupId, $this->ngoUserId);
    }

    /**
     * T-19.1-09: Plaintext OTP is NEVER stored in database or audit logs.
     */
    public function testPlaintextOtpNeverStoredInDatabaseOrLogs(): void
    {
        $this->otpService->issue($this->pickupId, $this->ngoUserId);

        // Check pickups table: only otp_hmac exists (64 hex characters)
        $pickup = $this->pickupRepo->findById($this->pickupId);
        $this->assertNotEmpty($pickup['otp_hmac']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $pickup['otp_hmac']);

        // Check audit_logs table: metadata must not contain plaintext OTP
        $stmt = $this->pdo->query("SELECT metadata::text FROM audit_logs WHERE target_id = {$this->pickupId}");
        $logs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($logs as $logJson) {
            $this->assertStringNotContainsString('"otp"', (string) $logJson);
            $this->assertStringNotContainsString('"code"', (string) $logJson);
        }
    }
}
