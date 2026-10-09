<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Support\Database;
use PDO;

class Notifier
{
    // Canonical notification types
    public const TYPE_NGO_VERIFIED       = 'ngo.verified';
    public const TYPE_NGO_REJECTED       = 'ngo.rejected';
    public const TYPE_NGO_SUBMITTED      = 'ngo.application.submitted';
    public const TYPE_REQUEST_CREATED    = 'request.created';
    public const TYPE_REQUEST_ACCEPTED   = 'request.accepted';
    public const TYPE_REQUEST_REJECTED   = 'request.rejected';
    public const TYPE_PICKUP_PROPOSED    = 'pickup.proposed';
    public const TYPE_PICKUP_SCHEDULED   = 'pickup.scheduled';
    public const TYPE_OTP_ISSUED         = 'otp.issued';
    public const TYPE_HANDOVER_COMPLETED = 'handover.completed';
    public const TYPE_DONATION_CANCELLED = 'donation.cancelled';
    public const TYPE_USER_SUSPENDED     = 'user.suspended';
    public const TYPE_SYSTEM             = 'system.alert';

    private PDO $pdo;
    private Outbox $outbox;
    private UserRepository $userRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?Outbox $outbox = null,
        ?UserRepository $userRepo = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->outbox = $outbox ?? new Outbox($this->pdo);
        $this->userRepo = $userRepo ?? new UserRepository($this->pdo);
    }

    /**
     * Dispatch an in-app notification and optionally enqueue an email outbox row.
     * Operates within the current transaction.
     */
    public function notify(
        int $userId,
        string $type,
        string $title,
        string $body,
        ?string $refType = null,
        ?int $refId = null,
        bool $emailToo = false,
        ?string $emailSubject = null,
        ?string $emailBodyHtml = null
    ): array {
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (user_id, type, title, body, ref_type, ref_id)
            VALUES (:user_id, :type, :title, :body, :ref_type, :ref_id)
            RETURNING id, user_id, type, title, body, ref_type, ref_id, read_at, created_at
        ");

        $stmt->execute([
            ':user_id'  => $userId,
            ':type'     => $type,
            ':title'    => $title,
            ':body'     => $body,
            ':ref_type' => $refType,
            ':ref_id'   => $refId,
        ]);

        $notification = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($emailToo) {
            $user = $this->userRepo->findById($userId);
            if ($user !== null && !empty($user['email'])) {
                $subject = $emailSubject ?? $title;
                $this->outbox->enqueue(
                    $user['email'],
                    $subject,
                    $body,
                    $emailBodyHtml
                );
            }
        }

        return $notification;
    }

    public function listForUser(int $userId, bool $unreadOnly, int $limit, int $offset): array
    {
        $sql = "
            SELECT id, user_id, type, title, body, ref_type, ref_id, read_at, created_at
            FROM notifications
            WHERE user_id = :user_id
        ";

        if ($unreadOnly) {
            $sql .= " AND read_at IS NULL";
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countForUser(int $userId, bool $unreadOnly): int
    {
        $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id";
        if ($unreadOnly) {
            $sql .= " AND read_at IS NULL";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function markAsRead(int $userId, int $notificationId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE notifications
            SET read_at = NOW()
            WHERE id = :id AND user_id = :user_id AND read_at IS NULL
        ");
        $stmt->execute([
            ':id'      => $notificationId,
            ':user_id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markAllAsRead(int $userId): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE notifications
            SET read_at = NOW()
            WHERE user_id = :user_id AND read_at IS NULL
        ");
        $stmt->execute([':user_id' => $userId]);

        return $stmt->rowCount();
    }
}
