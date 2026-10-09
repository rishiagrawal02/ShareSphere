<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\ValidationFailedException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\NgoRepository;
use App\Repositories\UserRepository;
use App\Services\NgoService;
use App\Support\Validator;

class ProfileController
{
    private UserRepository $userRepo;
    private NgoRepository $ngoRepo;
    private NgoService $ngoService;

    public function __construct(
        ?UserRepository $userRepo = null,
        ?NgoRepository $ngoRepo = null,
        ?NgoService $ngoService = null
    ) {
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->ngoRepo = $ngoRepo ?? new NgoRepository();
        $this->ngoService = $ngoService ?? new NgoService();
    }

    /**
     * GET /api/profile
     */
    public function show(Request $request): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');
        $user = $this->userRepo->findById($userId);

        if ($user === null) {
            throw new NotFoundException('User not found');
        }

        $profile = ['user' => $user];

        if ($user['role'] === 'ngo') {
            $ngo = $this->ngoRepo->findNgoByUserId($userId);
            if ($ngo !== null) {
                $docs = $this->ngoRepo->listDocuments((int) $ngo['id']);
                foreach ($docs as &$doc) {
                    $doc['media_url'] = "/api/media/ngo-documents/{$doc['id']}";
                }
                $ngo['documents'] = $docs;
                $profile['ngo'] = $ngo;
            }
        }

        return Response::json([
            'status'  => 'ok',
            'profile' => $profile,
        ]);
    }

    /**
     * PATCH /api/profile
     */
    public function update(Request $request): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');
        $body = $request->getBody();

        // Whitelist updates to prevent mass assignment
        $userFields = [];
        if (isset($body['name'])) {
            $userFields['name'] = trim((string) $body['name']);
        }
        if (isset($body['phone'])) {
            $userFields['phone'] = trim((string) $body['phone']);
        }

        if (!empty($userFields)) {
            $validated = Validator::validate($userFields, [
                'name'  => 'string|min:2|max:100',
                'phone' => 'string|max:32',
            ]);

            $pdo = \App\Support\Database::getConnection();
            $sets = [];
            $params = [':id' => $userId];
            if (isset($validated['name'])) {
                $sets[] = "name = :name";
                $params[':name'] = $validated['name'];
            }
            if (isset($validated['phone'])) {
                $sets[] = "phone = :phone";
                $params[':phone'] = $validated['phone'];
            }
            if (!empty($sets)) {
                $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = :id");
                $stmt->execute($params);
            }
        }

        // NGO updates if user is an NGO
        $user = $this->userRepo->findById($userId);
        if ($user['role'] === 'ngo') {
            $ngo = $this->ngoRepo->findNgoByUserId($userId);
            if ($ngo !== null) {
                $ngoUpdates = [];
                if (isset($body['organization_name'])) {
                    $ngoUpdates['organization_name'] = trim((string) $body['organization_name']);
                }
                if (isset($body['registration_number'])) {
                    $ngoUpdates['registration_number'] = trim((string) $body['registration_number']);
                }
                if (isset($body['address_text'])) {
                    $ngoUpdates['address_text'] = trim((string) $body['address_text']);
                }
                if (isset($body['latitude']) && isset($body['longitude'])) {
                    $ngoUpdates['latitude'] = (float) $body['latitude'];
                    $ngoUpdates['longitude'] = (float) $body['longitude'];
                }
                if (isset($body['service_radius_km'])) {
                    $ngoUpdates['service_radius_km'] = (float) $body['service_radius_km'];
                }

                // Re-verification trigger rule: if verified NGO changes name or reg number, reset status to pending
                if (
                    $ngo['verification_status'] === 'verified' &&
                    (isset($ngoUpdates['organization_name']) && $ngoUpdates['organization_name'] !== $ngo['organization_name'] ||
                     isset($ngoUpdates['registration_number']) && $ngoUpdates['registration_number'] !== $ngo['registration_number'])
                ) {
                    $ngoUpdates['verification_status'] = 'pending';
                }

                if (!empty($ngoUpdates)) {
                    $this->ngoRepo->updateNgo((int) $ngo['id'], $ngoUpdates);
                }
            }
        }

        return $this->show($request);
    }

    /**
     * POST /api/profile/ngo-documents
     */
    public function uploadDocument(Request $request): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');
        $files = $request->getFiles();

        $file = $files['document'] ?? $files['file'] ?? null;
        if ($file === null) {
            throw new ValidationFailedException(['document' => 'No document file provided']);
        }

        $doc = $this->ngoService->uploadDocument($userId, $file);
        $doc['media_url'] = "/api/media/ngo-documents/{$doc['id']}";

        return Response::json([
            'status'   => 'ok',
            'message'  => 'Document uploaded successfully',
            'document' => $doc,
        ], 201);
    }

    /**
     * GET /api/profile/ngo-documents
     */
    public function listDocuments(Request $request): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');
        $ngo = $this->ngoRepo->findNgoByUserId($userId);

        if ($ngo === null) {
            throw new NotFoundException('NGO profile not found');
        }

        $docs = $this->ngoRepo->listDocuments((int) $ngo['id']);
        foreach ($docs as &$doc) {
            $doc['media_url'] = "/api/media/ngo-documents/{$doc['id']}";
        }

        return Response::json([
            'status'    => 'ok',
            'documents' => $docs,
        ]);
    }

    /**
     * DELETE /api/profile/ngo-documents/{id}
     */
    public function deleteDocument(Request $request, int $id): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');
        $this->ngoService->deleteDocument($userId, $id);

        return Response::json([
            'status'  => 'ok',
            'message' => 'Document deleted successfully',
        ]);
    }

    /**
     * POST /api/profile/ngo-resubmit
     */
    public function resubmit(Request $request): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');
        $updatedNgo = $this->ngoService->resubmitVerification($userId);

        return Response::json([
            'status'  => 'ok',
            'message' => 'Application resubmitted for verification review',
            'ngo'     => $updatedNgo,
        ]);
    }
}
