<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Config;
use RuntimeException;

class FileStorage
{
    private string $baseStoragePath;

    public function __construct(?string $baseStoragePath = null)
    {
        if ($baseStoragePath !== null) {
            $this->baseStoragePath = rtrim($baseStoragePath, '/\\');
        } else {
            $root = dirname(__DIR__, 2);
            $this->baseStoragePath = $root . '/storage/private';
        }
    }

    /**
     * Store an uploaded or temporary file safely on disk.
     */
    public function store(string $sourcePath, string $kind, string $extension, string $mime): array
    {
        if (!in_array($kind, ['images', 'documents'], true)) {
            throw new RuntimeException("Invalid storage kind: {$kind}");
        }

        // Sanitize image if applicable
        if (str_starts_with($mime, 'image/')) {
            ImageSanitizer::sanitize($sourcePath, $mime);
        }

        $hash = hash_file('sha256', $sourcePath);
        $randomHex = bin2hex(random_bytes(16)); // 32 hex chars
        $shard = substr($randomHex, 0, 2);
        $storageName = "{$randomHex}.{$extension}";

        $dir = "{$this->baseStoragePath}/{$kind}/{$shard}";
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $destPath = "{$dir}/{$storageName}";

        if (is_uploaded_file($sourcePath)) {
            if (!move_uploaded_file($sourcePath, $destPath)) {
                throw new RuntimeException('Failed to move uploaded file to private storage');
            }
        } else {
            if (!copy($sourcePath, $destPath)) {
                throw new RuntimeException('Failed to copy file to private storage');
            }
        }

        @chmod($destPath, 0640);
        $sizeBytes = filesize($destPath);

        return [
            'storage_name' => "{$shard}/{$storageName}",
            'sha256'       => $hash,
            'size_bytes'   => $sizeBytes !== false ? $sizeBytes : 0,
            'mime'         => $mime,
            'path'         => $destPath,
        ];
    }

    public function resolvePath(string $storageName, string $kind): string
    {
        // Anti-traversal guard
        if (str_contains($storageName, '..') || str_contains($storageName, "\0")) {
            throw new RuntimeException('Path traversal attempt detected');
        }

        return "{$this->baseStoragePath}/{$kind}/{$storageName}";
    }

    public function delete(string $storageName, string $kind): bool
    {
        $path = $this->resolvePath($storageName, $kind);
        if (file_exists($path)) {
            return unlink($path);
        }
        return false;
    }

    public function deleteMany(array $storageNames, string $kind): void
    {
        foreach ($storageNames as $name) {
            $this->delete($name, $kind);
        }
    }
}
