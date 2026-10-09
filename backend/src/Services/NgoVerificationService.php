<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\ValidationFailedException;
use App\Repositories\AuditRepository;
use App\Repositories\NgoRepository;
use App\Support\Database;
use App\Support\EmailTemplates;
use PDO;
use Throwable;

class NgoVerificationService
{
    private const ALLOWED_DECISIONS = ['approve', 'reject', 'request_correction', 'suspend', 'reinstate'];

    private const LEGAL_TRANSITIONS = [
        'pending'              => ['approve' => 'verified', 'reject' => 'rejected', 'request_correction' => 'correction_requested'],
        'correction_requested' => ['approve' => 'verified', 'reject' => 'rejected'],
        'verified'             => ['suspend' => 'suspended'],
        'suspended'            => ['reinstate' => 'verified'],
    ];

    private PDO $pdo;
    private NgoRepository $ngoRepo;
    private Notifier $notifier;
    private AuditRepository $auditRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?NgoRepository $ngoRepo = null,
        ?Notifier $notifier = null,
        ?AuditRepository $auditRepo = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->ngoRepo = $ngoRepo ?? new NgoRepository($this->pdo);
        $this->notifier = $notifier ?? new Notifier($this->pdo);
        $this->auditRepo = $auditRepo ?? new AuditRepository($this->pdo);
    }

    public function processDecision(int $adminUserId, int $ngoId, string $decision, ?string $note): array
    {
        if (!in_array($decision, self::ALLOWED_DECISIONS, true)) {
            throw new ValidationFailedException(['decision' => 'Invalid verification decision']);
        }

        if (in_array($decision, ['reject', 'request_correction', 'suspend'], true) && empty(trim((string) $note))) {
            throw new ValidationFailedException(['note' => 'A review note is mandatory for this decision']);
        }

        $this->pdo->beginTransaction();

        try {
            // Concurrency protection: Lock NGO row FOR UPDATE
            $stmt = $this->pdo->prepare("
                SELECT n.id, n.user_id, n.organization_name, n.verification_status, u.email
                FROM ngos n
                JOIN users u ON n.user_id = u.id
                WHERE n.id = :id
                FOR UPDATE
            ");
            $stmt->execute([':id' => $ngoId]);
            $ngo = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($ngo === false) {
                $this->pdo->rollBack();
                throw new NotFoundException('NGO organisation not found');
            }

            $currentStatus = $ngo['verification_status'];
            $allowedTransitions = self::LEGAL_TRANSITIONS[$currentStatus] ?? [];

            if (!isset($allowedTransitions[$decision])) {
                $this->pdo->rollBack();
                throw new ConflictException(
                    "Cannot perform decision '{$decision}' on NGO with status '{$currentStatus}'",
                    'INVALID_TRANSITION'
                );
            }

            $nextStatus = $allowedTransitions[$decision];
            $cleanNote = !empty($note) ? trim($note) : null;

            // 1. Update verification status
            $this->ngoRepo->updateVerification($ngoId, $nextStatus, $adminUserId, $cleanNote);

            // 2. Audit log
            $this->auditRepo->log(
                $adminUserId,
                'ngo.verification.decision',
                'ngos',
                $ngoId,
                'success',
                ['previous_status' => $currentStatus, 'new_status' => $nextStatus, 'decision' => $decision, 'note' => $cleanNote]
            );

            // 3. Notify NGO (In-App + Email fan-out)
            $ngoUserId = (int) $ngo['user_id'];
            $orgName   = $ngo['organization_name'];

            if ($nextStatus === 'verified') {
                $emailTpl = EmailTemplates::ngoVerified($orgName);
                $this->notifier->notify(
                    $ngoUserId,
                    Notifier::TYPE_NGO_VERIFIED,
                    'NGO Account Verified',
                    "Congratulations! Your organisation '{$orgName}' has been verified. You can now browse community donations.",
                    'ngos',
                    $ngoId,
                    true,
                    $emailTpl['subject'],
                    $emailTpl['html']
                );
            } elseif ($nextStatus === 'rejected') {
                $emailTpl = EmailTemplates::ngoRejected($orgName, (string)$cleanNote);
                $this->notifier->notify(
                    $ngoUserId,
                    Notifier::TYPE_NGO_REJECTED,
                    'NGO Application Update',
                    "Your application for '{$orgName}' could not be approved. Note: {$cleanNote}",
                    'ngos',
                    $ngoId,
                    true,
                    $emailTpl['subject'],
                    $emailTpl['html']
                );
            } else {
                $this->notifier->notify(
                    $ngoUserId,
                    "ngo.status.{$nextStatus}",
                    'NGO Account Status Updated',
                    "Your organisation status is now '{$nextStatus}'." . ($cleanNote ? " Note: {$cleanNote}" : ""),
                    'ngos',
                    $ngoId
                );
            }

            $this->pdo->commit();

            return $this->ngoRepo->findNgoById($ngoId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
