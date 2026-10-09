<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\PayloadTooLargeException;
use App\Http\Exceptions\UnsupportedMediaTypeException;
use App\Http\Exceptions\ValidationFailedException;

class FileValidator
{
    public const MAX_IMAGE_SIZE = 5242880; // 5 MB
    public const MAX_DOCUMENT_SIZE = 8388608; // 8 MB
    public const MAX_IMAGE_DIMENSION = 6000;

    public const ALLOWED_IMAGE_MIMES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
    ];

    public const ALLOWED_DOCUMENT_MIMES = [
        'application/pdf' => ['pdf'],
        'image/jpeg'      => ['jpg', 'jpeg'],
        'image/png'       => ['png'],
        'image/webp'      => ['webp'],
    ];

    /** Magic bytes signatures */
    private const MAGIC_BYTES = [
        'image/jpeg'      => ["\xFF\xD8\xFF"],
        'image/png'       => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
        'image/webp'      => ["RIFF", "WEBP"], // RIFF at 0, WEBP at 8
        'application/pdf' => ["%PDF-"],
    ];

    /**
     * Validate an image file upload.
     */
    public static function validateImage(array $file): array
    {
        return self::validate($file, self::ALLOWED_IMAGE_MIMES, self::MAX_IMAGE_SIZE, true);
    }

    /**
     * Validate a document file upload (PDF or Image).
     */
    public static function validateDocument(array $file): array
    {
        return self::validate($file, self::ALLOWED_DOCUMENT_MIMES, self::MAX_DOCUMENT_SIZE, false);
    }

    private static function validate(
        array $file,
        array $allowedMimes,
        int $maxSize,
        bool $requireImage
    ): array {
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            throw new ValidationFailedException(['file' => 'No file was uploaded or file missing']);
        }

        $tmpPath = $file['tmp_name'];
        $originalName = $file['name'] ?? 'upload';
        $size = (int) ($file['size'] ?? filesize($tmpPath));

        // Size check
        if ($size <= 0) {
            throw new ValidationFailedException(['file' => 'Uploaded file is empty']);
        }
        if ($size > $maxSize) {
            throw new PayloadTooLargeException("File exceeds maximum allowed size of {$maxSize} bytes");
        }

        // Detect MIME via fileinfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if (!$detectedMime || !array_key_exists($detectedMime, $allowedMimes)) {
            throw new UnsupportedMediaTypeException("Unsupported file type: {$detectedMime}");
        }

        // Magic byte verification
        self::verifyMagicBytes($tmpPath, $detectedMime);

        // Sanitize extension
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $validExts = $allowedMimes[$detectedMime];
        if (!in_array($ext, $validExts, true)) {
            $ext = $validExts[0]; // Normalize extension based on detected mime
        }

        // Extra image dimension checks if it's an image
        if (str_starts_with($detectedMime, 'image/')) {
            $imageInfo = @getimagesize($tmpPath);
            if ($imageInfo === false) {
                throw new ValidationFailedException(['file' => 'File is corrupted or not a valid image']);
            }

            [$width, $height] = $imageInfo;
            if ($width > self::MAX_IMAGE_DIMENSION || $height > self::MAX_IMAGE_DIMENSION) {
                throw new ValidationFailedException(['file' => "Image dimensions exceed max allowed " . self::MAX_IMAGE_DIMENSION . "px"]);
            }
        } elseif ($requireImage) {
            throw new UnsupportedMediaTypeException('File must be a valid image');
        }

        return [
            'tmp_path'      => $tmpPath,
            'original_name' => basename($originalName),
            'mime'          => $detectedMime,
            'extension'     => $ext,
            'size'          => $size,
        ];
    }

    private static function verifyMagicBytes(string $path, string $mime): void
    {
        $fp = fopen($path, 'rb');
        if (!$fp) {
            throw new ValidationFailedException(['file' => 'Cannot read file for verification']);
        }

        $header = fread($fp, 16);
        fclose($fp);

        if ($header === false || strlen($header) < 4) {
            throw new ValidationFailedException(['file' => 'Corrupt file header']);
        }

        if ($mime === 'image/jpeg') {
            if (!str_starts_with($header, "\xFF\xD8\xFF")) {
                throw new UnsupportedMediaTypeException('Invalid JPEG header');
            }
        } elseif ($mime === 'image/png') {
            if (!str_starts_with($header, "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A")) {
                throw new UnsupportedMediaTypeException('Invalid PNG header');
            }
        } elseif ($mime === 'image/webp') {
            if (!str_starts_with($header, "RIFF") || substr($header, 8, 4) !== "WEBP") {
                throw new UnsupportedMediaTypeException('Invalid WebP header');
            }
        } elseif ($mime === 'application/pdf') {
            if (!str_starts_with($header, "%PDF-")) {
                throw new UnsupportedMediaTypeException('Invalid PDF header');
            }
        }
    }
}
