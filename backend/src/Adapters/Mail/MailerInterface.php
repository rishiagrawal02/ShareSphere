<?php

declare(strict_types=1);

namespace App\Adapters\Mail;

interface MailerInterface
{
    /**
     * Send an email.
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject line
     * @param string $text Plain-text email body
     * @param string|null $html Optional HTML formatted body
     * @return bool True if sent successfully, false otherwise
     */
    public function send(string $to, string $subject, string $text, ?string $html = null): bool;
}
