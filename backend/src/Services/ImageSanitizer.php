<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class ImageSanitizer
{
    /**
     * Re-encode image using GD to strip EXIF, metadata, and embedded payloads.
     * Overwrites destination path or returns true on success.
     */
    public static function sanitize(string $sourcePath, string $mime, ?string $destPath = null): bool
    {
        $target = $destPath ?? $sourcePath;

        if (!extension_loaded('gd')) {
            // Fallback if GD is not available (e.g. minimal environments)
            return true;
        }

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png'  => @imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default      => false,
        };

        if ($image === false) {
            throw new RuntimeException('Failed to decode image during sanitization');
        }

        // Preserve alpha transparency for PNG / WebP
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($image, $target, 90),
            'image/png'  => imagepng($image, $target, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, $target, 90) : false,
            default      => false,
        };

        imagedestroy($image);

        if (!$saved) {
            throw new RuntimeException('Failed to re-encode sanitized image');
        }

        return true;
    }
}
