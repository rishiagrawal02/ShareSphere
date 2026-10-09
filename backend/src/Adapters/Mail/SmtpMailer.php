<?php

declare(strict_types=1);

namespace App\Adapters\Mail;

use App\Support\Config;
use App\Support\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class SmtpMailer implements MailerInterface
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private string $fromAddress;
    private string $fromName;

    public function __construct(
        ?string $host = null,
        ?int $port = null,
        ?string $username = null,
        ?string $password = null,
        ?string $encryption = null,
        ?string $fromAddress = null,
        ?string $fromName = null
    ) {
        $this->host = $host ?? Config::get('MAIL_HOST', '127.0.0.1');
        $this->port = $port ?? (int) Config::get('MAIL_PORT', 1025);
        $this->username = $username ?? Config::get('MAIL_USERNAME', '');
        $this->password = $password ?? Config::get('MAIL_PASSWORD', '');
        $this->encryption = $encryption ?? Config::get('MAIL_ENCRYPTION', '');
        $this->fromAddress = $fromAddress ?? Config::get('MAIL_FROM_ADDRESS', 'noreply@sharesphere.local');
        $this->fromName = $fromName ?? Config::get('MAIL_FROM_NAME', 'ShareSphere');
    }

    public function send(string $to, string $subject, string $text, ?string $html = null): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;
            $mail->CharSet = 'UTF-8';

            // SMTP Authentication
            if ($this->username !== '' || $this->password !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $this->username;
                $mail->Password = $this->password;
            } else {
                $mail->SMTPAuth = false;
            }

            // Auto-configure TLS when appropriate
            if (!empty($this->encryption)) {
                $mail->SMTPSecure = strtolower($this->encryption) === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($this->port === 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;

            if ($html !== null && $html !== '') {
                $mail->isHTML(true);
                $mail->Body = $html;
                $mail->AltBody = $text;
            } else {
                $mail->isHTML(false);
                $mail->Body = $text;
            }

            return $mail->send();
        } catch (PHPMailerException $e) {
            Logger::error('SmtpMailer failed to deliver message: ' . $e->getMessage(), [
                'to' => $to,
                'subject' => $subject,
            ]);
            throw $e;
        }
    }
}
