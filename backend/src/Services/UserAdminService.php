<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\ValidationFailedException;
use App\Repositories\AuditRepository;
use App\Repositories\DonationRepository;
use App\Repositories\NgoRepository;
use App\Repositories\UserRepository;
use App\Support\Database;
use PDO;
use Throwable;

class UserAdminService
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private NgoRepository $ngoRepo;
    private DonationRepository $donationRepo;
    private Notifier $notifier;
    private AuditRepository $auditRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?UserRepository $userRepo = null,
        ?NgoRepository $ngoRepo = null,
        ?DonationRepository $donationRepo = null,
        ?Notifier $notifier = null,
        ?AuditRepository $auditRepo = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->userRepo = $userRepo ?? new UserRepository($this->pdo);
        $this->ngoRepo = $ngoRepo ?? new NgoRepository($this->pdo);
        $this->donationRepo = $donationRepo ?? new DonationRepository($this->pdo);
        $this->notifier = $notifier ?? new Notifier($this->pdo);
        $this->auditRepo = $auditRepo ?? new AuditRepository($this->pdo);
    }

    /**
     * List and filter users with pagination (no password hashes).
     */
    public function listUsers(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $items = $this->userRepo->listUsers($filters, $limit, $offset);
        $total = $this->userRepo->countUsers($filters);

        // Sanitize - strictly ensure no password hashes
        $sanitized = array_map(function (array $user) {
            unset($user['password_hash']);
            return [
                'id'             => (int) $user['id'],
                'name'           => $user['name'],
                'email'          => $user['email'],
                'role'           => $user['role'],
                'account_status' => $user['account_status'],
                'phone'          => $user['phone'] ?? null,
                'created_at'     => $user['created_at'],
                'updated_at'     => $user['updated_at'] ?? null,
                'last_login_at'  => $user['last_login_at'] ?? null,
                'ngo'            => !empty($user['ngo_id']) ? [
                    'id'                  => (int) $user['ngo_id'],
                    'organization_name'   => $user['organization_name'],
                    'verification_status' => $user['ngo_verification_status'],
                ] : null,
            ];
        }, $items);

        return [
            'items' => $sanitized,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'pages' => (int) ceil($total / $limit),
        ];
    }

    /**
     * Get single user details by ID.
     */
    public function getUser(int $id): array
    {
        $user = $this->userRepo->findById($id);
        if (!$user) {
            throw new NotFoundException('User not found', 'USER_NOT_FOUND');
        }

        unset($user['password_hash']);

        $ngo = $this->ngoRepo->findNgoByUserId($id);

        return [
            'id'             => (int) $user['id'],
            'name'           => $user['name'],
            'email'          => $user['email'],
            'role'           => $user['role'],
            'account_status' => $user['account_status'],
            'phone'          => $user['phone'] ?? null,
            'created_at'     => $user['created_at'],
            'updated_at'     => $user['updated_at'] ?? null,
            'last_login_at'  => $user['last_login_at'] ?? null,
            'ngo'            => $ngo ? [
                'id'                  => (int) $ngo['id'],
                'organization_name'   => $ngo['organization_name'],
                'registration_number' => $ngo['registration_number'],
                'verification_status' => $ngo['verification_status'],
                'address_text'        => $ngo['address_text'],
            ] : null,
        ];
    }

    /**
     * Update user account status (active | suspended).
     */
    public function updateStatus(int $adminUserId, int $targetUserId, string $status, ?string $reason = null, bool $cascade = false): array
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['active', 'suspended'], true)) {
            throw new ValidationFailedException(['status' => 'Status must be either active or suspended']);
        }

        $reason = $reason !== null ? trim($reason) : '';
        if ($status === 'suspended' && $reason === '') {
            throw new ValidationFailedException(['reason' => 'Reason is required when suspending an account']);
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $targetUserId]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$targetUser) {
                throw new NotFoundException('User not found', 'USER_NOT_FOUND');
            }

            // Admin cannot suspend self
            if ($status === 'suspended' && $adminUserId === $targetUserId) {
                throw new ConflictException('Administrators cannot suspend their own account', 'CANNOT_SUSPEND_SELF');
            }

            // Cannot suspend the last active admin
            if ($status === 'suspended' && $targetUser['role'] === 'admin' && $targetUser['account_status'] === 'active') {
                $activeAdmins = $this->userRepo->countActiveAdmins();
                if ($activeAdmins <= 1) {
                    throw new ConflictException('Cannot suspend the last active administrator', 'CANNOT_SUSPEND_LAST_ADMIN');
                }
            }

            // Update user status
            $this->userRepo->updateStatus($targetUserId, $status);

            // Optional cascade for suspended donor: close active donations and cancel pending requests
            if ($status === 'suspended' && $cascade && $targetUser['role'] === 'donor') {
                // Close active donations that have no open allocations
                $this->pdo->prepare("
                    UPDATE donations
                    SET status = 'closed', updated_at = NOW()
                    WHERE donor_id = :d_id AND status = 'active'
                ")->execute([':d_id' => $targetUserId]);
            }

            // Audit log
            $action = ($status === 'suspended') ? 'user.suspended' : 'user.reinstated';
            $this->auditRepo->log($adminUserId, $action, 'user', $targetUserId, 'success', [
                'previous_status' => $targetUser['account_status'],
                'new_status'      => $status,
                'reason'          => $reason ?: null,
                'cascade'         => $cascade,
            ]);

            // Notify user via email
            $subject = ($status === 'suspended')
                ? 'Your ShareSphere Account Has Been Suspended'
                : 'Your ShareSphere Account Has Been Reinstated';

            $body = ($status === 'suspended')
                ? "Hello {$targetUser['name']},\n\nYour ShareSphere account has been suspended by an administrator.\nReason: {$reason}\n\nIf you believe this was an error, please consult our appeal guidelines at docs/admin-policy.md or contact support."
                : "Hello {$targetUser['name']},\n\nYour ShareSphere account has been reinstated. You may now log in and continue using the platform.";

            $this->notifier->notify(
                $targetUserId,
                ($status === 'suspended') ? Notifier::TYPE_SYSTEM : Notifier::TYPE_SYSTEM,
                $subject,
                $body,
                'user',
                $targetUserId,
                true,
                $subject,
                "<p>" . nl2br(htmlspecialchars($body)) . "</p>"
            );

            $this->pdo->commit();

            return $this->getUser($targetUserId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
