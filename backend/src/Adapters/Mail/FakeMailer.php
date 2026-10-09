<?php

declare(strict_types=1);

namespace App\Adapters\Mail;

use RuntimeException;

class FakeMailer implements MailerInterface
{
    /** @var array<int, array{to: string, subject: string, text: string, html: ?string, sent_at: int}> */
    private array $sent = [];
    private bool $shouldFail = false;
    private string $failureMessage = 'Simulated SMTP connection failure';

    public function setShouldFail(bool $fail, string $msg = 'Simulated SMTP connection failure'): void
    {
        $this->shouldFail = $fail;
        $this->failureMessage = $msg;
    }

    public function send(string $to, string $subject, string $text, ?string $html = null): bool
    {
        if ($this->shouldFail) {
            throw new RuntimeException($this->failureMessage);
        }

        $this->sent[] = [
            'to' => $to,
            'subject' => $subject,
            'text' => $text,
            'html' => $html,
            'sent_at' => time(),
        ];

        return true;
    }

    public function getSent(): array
    {
        return $this->sent;
    }

    public function count(): int
    {
        return count($this->sent);
    }

    public function clear(): void
    {
        $this->sent = [];
    }
}
