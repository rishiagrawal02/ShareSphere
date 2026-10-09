<?php

declare(strict_types=1);

namespace App\Support;

class EmailTemplates
{
    public static function ngoVerified(string $orgName): array
    {
        $safeOrg = htmlspecialchars($orgName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subject = 'Your NGO Account Has Been Verified - ShareSphere';
        $text = "Hello {$orgName},\n\nCongratulations! Your NGO organisation has been verified by the ShareSphere administration team. You can now browse community donations, submit item requests, and coordinate pickups.\n\nThank you for making a difference,\nThe ShareSphere Team";
        $html = "<p>Hello <strong>{$safeOrg}</strong>,</p><p>Congratulations! Your NGO organisation has been verified by the ShareSphere administration team. You can now browse community donations, submit item requests, and coordinate pickups.</p><p>Thank you for making a difference,<br><strong>The ShareSphere Team</strong></p>";

        return ['subject' => $subject, 'text' => $text, 'html' => $html];
    }

    public static function ngoRejected(string $orgName, string $note): array
    {
        $safeOrg = htmlspecialchars($orgName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeNote = htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subject = 'Update Regarding Your NGO Application - ShareSphere';
        $text = "Hello {$orgName},\n\nThank you for your interest in ShareSphere. After review, your application could not be approved at this time.\n\nNote from reviewer:\n{$note}\n\nYou may log in to update your documents or contact support for clarification.\n\nRegards,\nThe ShareSphere Team";
        $html = "<p>Hello <strong>{$safeOrg}</strong>,</p><p>Thank you for your interest in ShareSphere. After review, your application could not be approved at this time.</p><blockquote>{$safeNote}</blockquote><p>You may log in to update your documents or contact support for clarification.</p><p>Regards,<br><strong>The ShareSphere Team</strong></p>";

        return ['subject' => $subject, 'text' => $text, 'html' => $html];
    }

    public static function requestCreated(string $donorName, string $donationTitle, string $orgName): array
    {
        $safeDonor = htmlspecialchars($donorName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeTitle = htmlspecialchars($donationTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeOrg   = htmlspecialchars($orgName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $subject = "New Item Request for '{$donationTitle}' - ShareSphere";
        $text = "Hello {$donorName},\n\nAn NGO ({$orgName}) has submitted a request for your donation '{$donationTitle}'.\n\nPlease log in to your ShareSphere account to review the request and accept or decline it.\n\nRegards,\nThe ShareSphere Team";
        $html = "<p>Hello <strong>{$safeDonor}</strong>,</p><p>An NGO (<strong>{$safeOrg}</strong>) has submitted a request for your donation <em>{$safeTitle}</em>.</p><p>Please log in to your ShareSphere account to review the request and accept or decline it.</p><p>Regards,<br><strong>The ShareSphere Team</strong></p>";

        return ['subject' => $subject, 'text' => $text, 'html' => $html];
    }

    public static function requestAccepted(string $orgName, string $donationTitle): array
    {
        $safeOrg   = htmlspecialchars($orgName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeTitle = htmlspecialchars($donationTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $subject = "Request Accepted for '{$donationTitle}' - ShareSphere";
        $text = "Hello {$orgName},\n\nGreat news! The donor has accepted your request for '{$donationTitle}'. You can now coordinate the pickup schedule.\n\nRegards,\nThe ShareSphere Team";
        $html = "<p>Hello <strong>{$safeOrg}</strong>,</p><p>Great news! The donor has accepted your request for <em>{$safeTitle}</em>. You can now coordinate the pickup schedule.</p><p>Regards,<br><strong>The ShareSphere Team</strong></p>";

        return ['subject' => $subject, 'text' => $text, 'html' => $html];
    }

    public static function pickupScheduled(string $recipientName, string $donationTitle, string $scheduledTime): array
    {
        $safeName = htmlspecialchars($recipientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeTitle = htmlspecialchars($donationTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeTime = htmlspecialchars($scheduledTime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $subject = "Pickup Scheduled for '{$donationTitle}' - ShareSphere";
        $text = "Hello {$recipientName},\n\nThe handover pickup for '{$donationTitle}' is confirmed for {$scheduledTime}.\n\nRegards,\nThe ShareSphere Team";
        $html = "<p>Hello <strong>{$safeName}</strong>,</p><p>The handover pickup for <em>{$safeTitle}</em> is confirmed for <strong>{$safeTime}</strong>.</p><p>Regards,<br><strong>The ShareSphere Team</strong></p>";

        return ['subject' => $subject, 'text' => $text, 'html' => $html];
    }
}
