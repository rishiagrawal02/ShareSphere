<?php

declare(strict_types=1);

namespace App\Services;

use App\Adapters\Mail\MailerInterface;
use App\Http\Exceptions\ValidationFailedException;
use App\Support\Database;
use App\Support\Logger;
use PDO;
use Throwable;

class Outbox
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Enqueue an email into the transactional outbox table.
     * Must be called inside or will execute in current DB connection.
     */
    public function enqueue(
        string $to,
        string $subject,
        string $bodyText,
        ?string $bodyHtml = null
    ): int {
        // Anti-header injection checks
        if (preg_match('/[\r\n]/', $to)) {
            throw new ValidationFailedException(['to' => 'Email recipient contains invalid newline characters']);
        }

        if (preg_match('/[\r\n]/', $subject)) {
            throw new ValidationFailedException(['subject' => 'Email subject contains invalid newline characters']);
        }

        $cleanEmail = trim(strtolower($to));
        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationFailedException(['to' => 'Invalid email recipient format']);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO email_outbox (to_email, subject, body_text, body_html, status, attempts, next_attempt_at)
            VALUES (:to, :subject, :body_text, :body_html, 'pending', 0, NOW())
            RETURNING id
        ");

        $stmt->execute([
            ':to'        => $cleanEmail,
            ':subject'   => trim($subject),
            ':body_text' => $bodyText,
            ':body_html' => $bodyHtml,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Process pending outbox messages using SKIP LOCKED concurrency protection.
     *
     * @return int Number of successfully delivered messages
     */
    public function sendPending(MailerInterface $mailer, int $batchSize = 25, int $maxAttempts = 5): int
    {
        $sentCount = 0;

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, to_email, subject, body_text, body_html, attempts
                FROM email_outbox
                WHERE status = 'pending' AND next_attempt_at <= NOW()
                ORDER BY next_attempt_at ASC
                LIMIT :limit
                FOR UPDATE SKIP LOCKED
            ");
            $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
            $stmt->execute();
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($messages)) {
                $this->pdo->commit();
                return 0;
            }

            foreach ($messages as $msg) {
                $id = (int) $msg['id'];
                $attempts = (int) $msg['attempts'] + 1;

                try {
                    $mailer->send(
                        $msg['to_email'],
                        $msg['subject'],
                        $msg['body_text'],
                        $msg['body_html']
                    );

                    $update = $this->pdo->prepare("
                        UPDATE email_outbox
                        SET status = 'sent', sent_at = NOW(), attempts = :attempts, last_error = NULL
                        WHERE id = :id
                    ");
                    $update->execute([':attempts' => $attempts, ':id' => $id]);
                    $sentCount++;
                } catch (Throwable $e) {
                    $errorMsg = substr($e->getMessage(), 0, 1000);
                    Logger::warning("Outbox delivery attempt {$attempts} failed for message {$id}: {$errorMsg}");

                    if ($attempts >= $maxAttempts) {
                        $update = $this->pdo->prepare("
                            UPDATE email_outbox
                            SET status = 'failed', attempts = :attempts, last_error = :err
                            WHERE id = :id
                        ");
                        $update->execute([':attempts' => $attempts, ':err' => $errorMsg, ':id' => $id]);
                    } else {
                        // Exponential backoff: 30s * 2^(attempts-1) => 30s, 60s, 120s, 240s
                        $delaySeconds = 30 * (2 ** ($attempts - 1));
                        $update = $this->pdo->prepare("
                            UPDATE email_outbox
                            SET attempts = :attempts,
                                next_attempt_at = NOW() + (:delay || ' seconds')::INTERVAL,
                                last_error = :err
                            WHERE id = :id
                        ");
                        $update->execute([
                            ':attempts' => $attempts,
                            ':delay'    => (string) $delaySeconds,
                            ':err'      => $errorMsg,
                            ':id'       => $id,
                        ]);
                    }
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::error("Outbox worker transaction error: " . $e->getMessage());
            throw $e;
        }

        return $sentCount;
    }
}
