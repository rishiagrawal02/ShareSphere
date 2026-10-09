<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\ValidationFailedException;
use App\Repositories\AuditRepository;
use App\Repositories\NgoRepository;
use App\Repositories\UserRepository;
use App\Support\Database;
use App\Support\Validator;
use PDO;
use Throwable;

class NgoService
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private NgoRepository $ngoRepo;
    private FileStorage $fileStorage;
    private Notifier $notifier;
    private AuditRepository $auditRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?UserRepository $userRepo = null,
        ?NgoRepository $ngoRepo = null,
        ?FileStorage $fileStorage = null,
        ?Notifier $notifier = null,
        ?AuditRepository $auditRepo = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->userRepo = $userRepo ?? new UserRepository($this->pdo);
        $this->ngoRepo = $ngoRepo ?? new NgoRepository($this->pdo);
        $this->fileStorage = $fileStorage ?? new FileStorage();
        $this->notifier = $notifier ?? new Notifier($this->pdo);
        $this->auditRepo = $auditRepo ?? new AuditRepository($this->pdo);
    }

    /**
     * Register an NGO organisation with required verification documents.
     */
    public function registerNgo(array $input, array $files): array
    {
        $validated = Validator::validate($input, [
            'name'                => 'required|string|min:2|max:100',
            'email'               => 'required|email|max:255',
            'password'            => 'required|string|min:10',
            'password_confirmation' => 'required|string',
            'organization_name'   => 'required|string|min:2|max:255',
            'registration_number' => 'required|string|min:2|max:128',
            'address_text'        => 'required|string|min:5',
            'latitude'            => 'required|numeric',
            'longitude'           => 'required|numeric',
            'service_radius_km'   => 'numeric',
            'phone'               => 'string|max:32',
        ]);

        if ($validated['password'] !== $validated['password_confirmation']) {
            throw new ValidationFailedException(['password_confirmation' => 'Password confirmation does not match']);
        }

        $existing = $this->userRepo->findByEmail($validated['email']);
        if ($existing !== null) {
            throw new ConflictException('An account with this email address already exists.', 'EMAIL_TAKEN');
        }

        // Validate documents
        $docFiles = $this->normalizeFilesArray($files['documents'] ?? null);
        if (empty($docFiles)) {
            throw new ValidationFailedException(['documents' => 'At least 1 verification document is required (max 3)']);
        }
        if (count($docFiles) > 3) {
            throw new ValidationFailedException(['documents' => 'A maximum of 3 verification documents may be uploaded']);
        }

        $validatedDocs = [];
        foreach ($docFiles as $doc) {
            $validatedDocs[] = FileValidator::validateDocument($doc);
        }

        // Transaction + Compensating storage cleanup
        $storedFileNames = [];

        $this->pdo->beginTransaction();

        try {
            // 1. Create User
            $passwordHash = password_hash($validated['password'], PASSWORD_DEFAULT);
            $user = $this->userRepo->create([
                'name'           => $validated['name'],
                'email'          => $validated['email'],
                'password_hash'  => $passwordHash,
                'role'           => 'ngo',
                'account_status' => 'active',
                'phone'          => $validated['phone'] ?? null,
            ]);
            $userId = (int) $user['id'];

            // 2. Create NGO organization
            $ngo = $this->ngoRepo->createNgo([
                'user_id'             => $userId,
                'organization_name'   => $validated['organization_name'],
                'registration_number' => $validated['registration_number'],
                'address_text'        => $validated['address_text'],
                'latitude'            => $validated['latitude'],
                'longitude'           => $validated['longitude'],
                'service_radius_km'   => $validated['service_radius_km'] ?? 25,
                'verification_status' => 'pending',
            ]);
            $ngoId = (int) $ngo['id'];

            // 3. Store documents to private storage and record in DB
            foreach ($validatedDocs as $vDoc) {
                $stored = $this->fileStorage->store(
                    $vDoc['tmp_path'],
                    'documents',
                    $vDoc['extension'],
                    $vDoc['mime']
                );
                $storedFileNames[] = $stored['storage_name'];

                $this->ngoRepo->addDocument(
                    $ngoId,
                    $stored['storage_name'],
                    $stored['mime'],
                    $stored['size_bytes'],
                    $stored['sha256'],
                    'registration_proof'
                );
            }

            // 4. Audit log
            $this->auditRepo->log(
                $userId,
                'ngo.registered',
                'ngos',
                $ngoId,
                'success',
                ['org' => $validated['organization_name'], 'docs_count' => count($storedFileNames)]
            );

            // 5. In-app notification to active admins
            $adminStmt = $this->pdo->query("SELECT id FROM users WHERE role = 'admin' AND account_status = 'active'");
            $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($adminIds as $adminId) {
                $this->notifier->notify(
                    (int) $adminId,
                    Notifier::TYPE_NGO_SUBMITTED,
                    'New NGO Application Submitted',
                    "NGO '{$validated['organization_name']}' has registered and submitted documents for verification.",
                    'ngos',
                    $ngoId
                );
            }

            $this->pdo->commit();

            return [
                'user' => $user,
                'ngo'  => $ngo,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Compensating storage cleanup
            $this->fileStorage->deleteMany($storedFileNames, 'documents');

            throw $e;
        }
    }

    /**
     * Upload an additional verification document for own NGO
     */
    public function uploadDocument(int $userId, array $file): array
    {
        $ngo = $this->ngoRepo->findNgoByUserId($userId);
        if ($ngo === null) {
            throw new NotFoundException('NGO profile not found');
        }

        if (!in_array($ngo['verification_status'], ['pending', 'correction_requested'], true)) {
            throw new ForbiddenException('Cannot modify documents once verified or rejected', 'INVALID_STATUS');
        }

        $currentCount = $this->ngoRepo->countDocuments((int) $ngo['id']);
        if ($currentCount >= 3) {
            throw new ValidationFailedException(['documents' => 'Maximum limit of 3 documents reached']);
        }

        $vDoc = FileValidator::validateDocument($file);
        $stored = $this->fileStorage->store($vDoc['tmp_path'], 'documents', $vDoc['extension'], $vDoc['mime']);

        try {
            return $this->ngoRepo->addDocument(
                (int) $ngo['id'],
                $stored['storage_name'],
                $stored['mime'],
                $stored['size_bytes'],
                $stored['sha256']
            );
        } catch (Throwable $e) {
            $this->fileStorage->delete($stored['storage_name'], 'documents');
            throw $e;
        }
    }

    /**
     * Delete an existing verification document for own NGO
     */
    public function deleteDocument(int $userId, int $documentId): void
    {
        $ngo = $this->ngoRepo->findNgoByUserId($userId);
        if ($ngo === null) {
            throw new NotFoundException('NGO profile not found');
        }

        if (!in_array($ngo['verification_status'], ['pending', 'correction_requested'], true)) {
            throw new ForbiddenException('Cannot modify documents once verified or rejected', 'INVALID_STATUS');
        }

        $doc = $this->ngoRepo->findDocumentById($documentId);
        if ($doc === null || (int) $doc['ngo_id'] !== (int) $ngo['id']) {
            throw new NotFoundException('Document not found');
        }

        $this->ngoRepo->deleteDocument($documentId);
        $this->fileStorage->delete($doc['storage_name'], 'documents');
    }

    public function resubmitVerification(int $userId): array
    {
        $ngo = $this->ngoRepo->findNgoByUserId($userId);
        if ($ngo === null) {
            throw new NotFoundException('NGO profile not found');
        }

        if ($ngo['verification_status'] !== 'correction_requested') {
            throw new ConflictException('Application is not in correction_requested state', 'INVALID_TRANSITION');
        }

        $this->ngoRepo->updateVerification((int) $ngo['id'], 'pending', null, null);

        return $this->ngoRepo->findNgoById((int) $ngo['id']);
    }

    private function normalizeFilesArray(?array $files): array
    {
        if ($files === null || empty($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if (empty($files['name'][$i])) {
                continue;
            }
            $normalized[] = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i] ?? 0,
                'size'     => $files['size'][$i] ?? 0,
            ];
        }

        return $normalized;
    }
}
