<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\TooManyRequestsException;
use App\Http\Exceptions\ValidationFailedException;
use App\Repositories\AllocationRepository;
use App\Repositories\AuditRepository;
use App\Repositories\PickupRepository;
use App\Repositories\UserRepository;
use App\Support\Config;
use App\Support\Database;
use PDO;

class OtpService
{
    private const TTL_SECONDS = 1800; // 30 minutes
    private const MAX_ATTEMPTS = 5;
    private const MAX_ISSUES_PER_24H = 3;

    private PDO $pdo;
    private PickupRepository $pickupRepo;
    private AllocationRepository $allocationRepo;
    private UserRepository $userRepo;
    private Notifier $notifier;
    private AuditRepository $auditRepo;
    private string $hmacKey;

    public function __construct(
        ?PDO $pdo = null,
        ?PickupRepository $pickupRepo = null,
        ?AllocationRepository $allocationRepo = null,
        ?UserRepository $userRepo = null,
        ?Notifier $notifier = null,
        ?AuditRepository $auditRepo = null,
        ?string $hmacKey = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->pickupRepo = $pickupRepo ?? new PickupRepository($this->pdo);
        $this->allocationRepo = $allocationRepo ?? new AllocationRepository($this->pdo);
        $this->userRepo = $userRepo ?? new UserRepository($this->pdo);
        $this->notifier = $notifier ?? new Notifier($this->pdo);
        $this->auditRepo = $auditRepo ?? new AuditRepository($this->pdo);
        $this->hmacKey = $hmacKey ?? (string) Config::get('OTP_HMAC_KEY', Config::get('APP_KEY', 'sharesphere-default-otp-key'));
    }

    /**
     * Issue a 6-digit OTP to the NGO for pickup verification.
     */
    public function issue(int $pickupId, int $actorUserId, ?int $nowTimestamp = null): array
    {
        $now = $nowTimestamp ?? time();
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                SELECT p.*, n.user_id AS ngo_user_id, u.email AS ngo_email, d.title AS donation_title
                FROM pickups p
                JOIN allocations a ON a.id = p.allocation_id
                JOIN donation_requests r ON r.id = a.request_id
                JOIN ngos n ON n.id = r.ngo_id
                JOIN users u ON u.id = n.user_id
                JOIN donations d ON d.id = a.donation_id
                WHERE p.id = :id
                FOR UPDATE
            ");
            $stmt->execute([':id' => $pickupId]);
            $pickup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pickup) {
                throw new NotFoundException('Pickup not found', 'PICKUP_NOT_FOUND');
            }

            if ((int) $pickup['ngo_user_id'] !== $actorUserId) {
                throw new ForbiddenException('Only the assigned NGO can issue a pickup OTP', 'FORBIDDEN_NGO');
            }

            if (!in_array($pickup['state'], ['scheduled', 'otp_issued'], true)) {
                throw new ConflictException("Cannot issue OTP in '{$pickup['state']}' state", 'INVALID_PICKUP_STATE');
            }

            // Must be within 24h of scheduled time
            $schedTs = strtotime($pickup['scheduled_at']);
            if ($schedTs - $now > 86400) {
                throw new ConflictException('OTP can only be issued within 24 hours of scheduled pickup time', 'OTP_TOO_EARLY');
            }

            // Check 24-hour rate limit
            $issueCount = (int) $pickup['otp_issue_count'];
            $lastIssued = $pickup['last_otp_issued_at'] ? strtotime($pickup['last_otp_issued_at']) : 0;
            if ($now - $lastIssued < 86400 && $issueCount >= self::MAX_ISSUES_PER_24H) {
                throw new TooManyRequestsException('Maximum OTP reissues reached for this pickup (3 per 24h)', 'OTP_REISSUE_LIMIT');
            }

            // Generate CSPRNG 6-digit OTP
            $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $hmac = hash_hmac('sha256', $pickupId . '|' . $otpCode, $this->hmacKey);
            $expiresAtStr = date('Y-m-d H:i:s', $now + self::TTL_SECONDS);

            $newIssueCount = ($now - $lastIssued < 86400) ? $issueCount + 1 : 1;

            $this->pickupRepo->updateOtp($pickupId, $hmac, $expiresAtStr, $newIssueCount);

            // Enqueue email with OTP to NGO user
            $ngoEmail = (string) $pickup['ngo_email'];
            $emailSubject = "Your ShareSphere Pickup Verification Code: {$otpCode}";
            $emailBody = "Hello,\n\nYour 6-digit pickup verification code for '{$pickup['donation_title']}' is:\n\n{$otpCode}\n\nThis code expires in 30 minutes. Share it with the donor at the physical handover.\n\nThank you,\nShareSphere Team";
            $emailHtml = "<p>Hello,</p><p>Your 6-digit pickup verification code for <strong>" . htmlspecialchars($pickup['donation_title']) . "</strong> is:</p><h2 style='font-size:24px;letter-spacing:4px;'>" . htmlspecialchars($otpCode) . "</h2><p>This code expires in 30 minutes. Share it with the donor at the physical handover.</p><p>ShareSphere Team</p>";

            // Note: In-app notification mentions that a code was emailed, WITHOUT exposing the code itself
            $this->notifier->notify(
                $actorUserId,
                Notifier::TYPE_OTP_ISSUED,
                'Pickup Code Issued',
                "A pickup verification code was emailed to your registered address for '{$pickup['donation_title']}'.",
                'pickup',
                $pickupId,
                true,
                $emailSubject,
                $emailHtml
            );

            // Audit log: NO plaintext OTP in audit logs
            $this->auditRepo->log(
                $actorUserId,
                'otp.issued',
                'pickup',
                $pickupId,
                'success',
                [
                    'expires_at'  => $expiresAtStr,
                    'issue_count' => $newIssueCount,
                ]
            );

            $this->pdo->commit();

            return [
                'status'       => 'otp_issued',
                'email_status' => 'queued',
                'expires_at'   => gmdate('Y-m-d\TH:i:s\Z', $now + self::TTL_SECONDS),
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Verify the 6-digit OTP submitted by the donor at physical handover.
     */
    public function verify(int $pickupId, int $actorUserId, string $otp, ?int $nowTimestamp = null): array
    {
        $now = $nowTimestamp ?? time();
        $otp = trim($otp);

        if (!preg_match('/^\d{6}$/', $otp)) {
            throw new ValidationFailedException(['otp' => 'OTP must be exactly 6 digits']);
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                SELECT p.*, d.donor_id, d.title AS donation_title, n.user_id AS ngo_user_id
                FROM pickups p
                JOIN allocations a ON a.id = p.allocation_id
                JOIN donations d ON d.id = a.donation_id
                JOIN donation_requests r ON r.id = a.request_id
                JOIN ngos n ON n.id = r.ngo_id
                WHERE p.id = :id
                FOR UPDATE
            ");
            $stmt->execute([':id' => $pickupId]);
            $pickup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pickup) {
                throw new NotFoundException('Pickup not found', 'PICKUP_NOT_FOUND');
            }

            if ((int) $pickup['donor_id'] !== $actorUserId) {
                throw new ForbiddenException('Only the donor owning this donation can verify the pickup OTP', 'FORBIDDEN_DONOR');
            }

            if ($pickup['state'] === 'collected') {
                throw new ConflictException('Pickup has already been collected', 'ALREADY_COLLECTED');
            }

            if ($pickup['state'] !== 'otp_issued') {
                throw new ConflictException("Cannot verify OTP in '{$pickup['state']}' state", 'INVALID_PICKUP_STATE');
            }

            // Lockout check
            if ((bool) $pickup['otp_locked'] || (int) $pickup['otp_attempts'] >= self::MAX_ATTEMPTS) {
                $this->auditRepo->log($actorUserId, 'otp.locked', 'pickup', $pickupId, 'failed');
                throw new ValidationFailedException(['otp' => 'Pickup code is locked due to too many failed attempts. The NGO must reissue a code.']);
            }

            // Expiry check
            $expiresAt = strtotime((string) $pickup['otp_expires_at']);
            if ($now > $expiresAt) {
                $this->auditRepo->log($actorUserId, 'otp.expired', 'pickup', $pickupId, 'failed');
                throw new ValidationFailedException(['otp' => 'Pickup code has expired. The NGO must reissue a code.']);
            }

            // Timing-safe HMAC comparison
            $computedHmac = hash_hmac('sha256', $pickupId . '|' . $otp, $this->hmacKey);
            $storedHmac = (string) $pickup['otp_hmac'];

            if (!hash_equals($storedHmac, $computedHmac)) {
                $attempts = (int) $pickup['otp_attempts'] + 1;
                $isLock = ($attempts >= self::MAX_ATTEMPTS);
                $this->pickupRepo->incrementOtpAttempts($pickupId, $isLock);

                $this->auditRepo->log(
                    $actorUserId,
                    $isLock ? 'otp.locked' : 'otp.verify.failed',
                    'pickup',
                    $pickupId,
                    'failed',
                    ['attempts' => $attempts]
                );

                $this->pdo->commit();

                if ($isLock) {
                    throw new ValidationFailedException(['otp' => 'Pickup code is now locked after 5 failed attempts. The NGO must reissue a code.']);
                }

                throw new ValidationFailedException(['otp' => 'Invalid pickup code']);
            }

            // OTP verified successfully: mark collected
            $this->pickupRepo->markCollected($pickupId);
            $this->allocationRepo->updateStatus((int) $pickup['allocation_id'], 'collected');

            // Notify NGO
            $ngoUserId = (int) $pickup['ngo_user_id'];
            $this->notifier->notify(
                $ngoUserId,
                Notifier::TYPE_HANDOVER_COMPLETED,
                'Items Collected',
                "The donor has verified the code for '{$pickup['donation_title']}'. Please inspect the items and confirm receipt to complete the handover.",
                'pickup',
                $pickupId
            );

            // Audit log
            $this->auditRepo->log(
                $actorUserId,
                'otp.verify.success',
                'pickup',
                $pickupId,
                'success'
            );

            $this->pdo->commit();

            return [
                'status'       => 'collected',
                'collected_at' => gmdate('Y-m-d\TH:i:s\Z', $now),
                'message'      => 'Pickup verified and marked as collected',
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
