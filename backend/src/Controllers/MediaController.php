<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Exceptions\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Services\FileStorage;
use App\Support\Logger;
use App\Support\MediaPolicyNgoDocument;

class MediaController
{
    private FileStorage $fileStorage;

    public function __construct(?FileStorage $fileStorage = null)
    {
        $this->fileStorage = $fileStorage ?? new FileStorage();
    }

    public function stream(Request $request, string $kind, int $id): Response
    {
        $userId   = (int) $request->getAttribute('auth_user_id');
        $userRole = $request->getAttribute('auth_role');

        $policy = match ($kind) {
            'ngo-documents' => new MediaPolicyNgoDocument(),
            default         => null,
        };

        if ($policy === null) {
            throw new NotFoundException('Resource not found');
        }

        $authInfo = $policy->authorize($userId, $userRole, $id);
        if ($authInfo === null) {
            // 404 to prevent resource enumeration
            throw new NotFoundException('Resource not found');
        }

        $storageKind = $authInfo['kind'] ?? 'documents';
        $storageName = $authInfo['storage_name'];
        $mime        = $authInfo['mime'];
        $disposition = $authInfo['disposition'] ?? 'inline';

        $filePath = $this->fileStorage->resolvePath($storageName, $storageKind);

        if (!file_exists($filePath)) {
            Logger::error("Media file missing on disk: {$filePath}", ['media_id' => $id, 'kind' => $kind]);
            throw new NotFoundException('Resource not found');
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new NotFoundException('Resource unreadable');
        }

        $response = new Response($content, 200, [
            'Content-Type'              => $mime,
            'Content-Length'            => (string) strlen($content),
            'X-Content-Type-Options'    => 'nosniff',
            'Cache-Control'             => 'private, max-age=0, must-revalidate',
            'Content-Disposition'       => "{$disposition}; filename=\"" . basename($storageName) . "\"",
        ]);

        return $response;
    }
}
