<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\ValidationFailedException;
use App\Repositories\AuditRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\NgoRepository;
use App\Repositories\RequirementRepository;
use App\Support\Database;
use App\Support\Validator;
use PDO;
use Throwable;

class RequirementService
{
    private const ALLOWED_URGENCIES = ['low', 'medium', 'high', 'critical'];
    private const ALLOWED_CONDITIONS = ['new', 'like_new', 'good', 'fair'];

    private PDO $pdo;
    private RequirementRepository $reqRepo;
    private NgoRepository $ngoRepo;
    private CategoryRepository $catRepo;
    private AuditRepository $auditRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?RequirementRepository $reqRepo = null,
        ?NgoRepository $ngoRepo = null,
        ?CategoryRepository $catRepo = null,
        ?AuditRepository $auditRepo = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->reqRepo = $reqRepo ?? new RequirementRepository($this->pdo);
        $this->ngoRepo = $ngoRepo ?? new NgoRepository($this->pdo);
        $this->catRepo = $catRepo ?? new CategoryRepository($this->pdo);
        $this->auditRepo = $auditRepo ?? new AuditRepository($this->pdo);
    }

    public function createRequirement(int $userId, array $input): array
    {
        $ngo = $this->ngoRepo->findNgoByUserId($userId);
        if ($ngo === null) {
            throw new NotFoundException('NGO organisation profile not found');
        }

        if ($ngo['verification_status'] !== 'verified') {
            throw new ForbiddenException('Only verified NGOs can create requirements', 'NGO_NOT_VERIFIED');
        }

        $validated = Validator::validate($input, [
            'title'           => 'required|string|min:3|max:120',
            'category_id'     => 'required|integer',
            'description'     => 'string|max:2000',
            'quantity_needed' => 'required|integer|min:1|max:100000',
            'urgency'         => 'required|string',
            'radius_km'       => 'numeric',
        ]);

        if (!in_array($validated['urgency'], self::ALLOWED_URGENCIES, true)) {
            throw new ValidationFailedException(['urgency' => 'Urgency must be one of: ' . implode(', ', self::ALLOWED_URGENCIES)]);
        }

        if (!empty($input['min_condition']) && !in_array($input['min_condition'], self::ALLOWED_CONDITIONS, true)) {
            throw new ValidationFailedException(['min_condition' => 'Condition must be one of: ' . implode(', ', self::ALLOWED_CONDITIONS)]);
        }

        // Validate category
        $catId = (int) $validated['category_id'];
        $cat = $this->catRepo->findById($catId);
        if ($cat === null || !(bool) $cat['is_active']) {
            throw new ValidationFailedException(['category_id' => 'Selected category is invalid or inactive']);
        }

        // Location: default to NGO headquarters if omitted
        $lat = isset($input['latitude']) ? (float) $input['latitude'] : (float) $ngo['latitude'];
        $lng = isset($input['longitude']) ? (float) $input['longitude'] : (float) $ngo['longitude'];
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new ValidationFailedException(['location' => 'Invalid latitude or longitude']);
        }

        $radius = isset($validated['radius_km']) ? (float) $validated['radius_km'] : (float) ($ngo['service_radius_km'] ?? 25);
        if ($radius < 1 || $radius > 500) {
            throw new ValidationFailedException(['radius_km' => 'Radius must be between 1 and 500 km']);
        }

        $neededBy = null;
        if (!empty($input['needed_by'])) {
            $ts = strtotime((string) $input['needed_by']);
            if ($ts === false || $ts < strtotime('today')) {
                throw new ValidationFailedException(['needed_by' => 'Needed by date must be today or in the future']);
            }
            $neededBy = date('Y-m-d', $ts);
        }

        $status = $input['status'] ?? 'active';
        if (!in_array($status, ['draft', 'active'], true)) {
            throw new ValidationFailedException(['status' => 'Status must be draft or active']);
        }

        $req = $this->reqRepo->create([
            'ngo_id'          => (int) $ngo['id'],
            'category_id'     => $catId,
            'title'           => $validated['title'],
            'description'     => $validated['description'] ?? '',
            'quantity_needed' => (int) $validated['quantity_needed'],
            'urgency'         => $validated['urgency'],
            'min_condition'   => $input['min_condition'] ?? null,
            'latitude'        => $lat,
            'longitude'       => $lng,
            'radius_km'       => $radius,
            'needed_by'       => $neededBy,
            'status'          => $status,
        ]);

        $this->auditRepo->log(
            $userId,
            'requirement.created',
            'ngo_requirements',
            (int) $req['id'],
            'success',
            ['title' => $req['title'], 'quantity_needed' => $req['quantity_needed']]
        );

        return $this->formatRequirement($req);
    }

    public function getRequirement(int $userId, string $userRole, int $id): array
    {
        $req = $this->reqRepo->findById($id);
        if ($req === null) {
            throw new NotFoundException('Requirement not found');
        }

        if ($userRole !== 'admin' && (int) $req['ngo_owner_id'] !== $userId) {
            throw new NotFoundException('Requirement not found');
        }

        return $this->formatRequirement($req);
    }

    public function listNgoRequirements(int $userId, ?string $status, ?int $categoryId, int $limit, int $offset): array
    {
        $ngo = $this->ngoRepo->findNgoByUserId($userId);
        if ($ngo === null) {
            throw new NotFoundException('NGO organisation profile not found');
        }

        $items = $this->reqRepo->listForNgo((int) $ngo['id'], $status, $categoryId, $limit, $offset);
        return array_map([$this, 'formatRequirement'], $items);
    }

    public function countNgoRequirements(int $userId, ?string $status, ?int $categoryId): int
    {
        $ngo = $this->ngoRepo->findNgoByUserId($userId);
        if ($ngo === null) {
            return 0;
        }

        return $this->reqRepo->countForNgo((int) $ngo['id'], $status, $categoryId);
    }

    public function updateRequirement(int $userId, int $id, array $input): array
    {
        $ngo = $this->ngoRepo->findNgoByUserId($userId);
        if ($ngo === null) {
            throw new NotFoundException('NGO profile not found');
        }

        $req = $this->reqRepo->findById($id);
        if ($req === null || (int) $req['ngo_id'] !== (int) $ngo['id']) {
            throw new NotFoundException('Requirement not found');
        }

        if ($req['status'] === 'closed') {
            throw new ConflictException('Cannot edit a closed requirement', 'REQUIREMENT_CLOSED');
        }

        $fieldsToUpdate = [];

        if (isset($input['title'])) {
            $title = trim((string) $input['title']);
            if (strlen($title) < 3 || strlen($title) > 120) {
                throw new ValidationFailedException(['title' => 'Title must be between 3 and 120 characters']);
            }
            $fieldsToUpdate['title'] = $title;
        }

        if (isset($input['description'])) {
            $fieldsToUpdate['description'] = trim((string) $input['description']);
        }

        if (isset($input['urgency'])) {
            if (!in_array($input['urgency'], self::ALLOWED_URGENCIES, true)) {
                throw new ValidationFailedException(['urgency' => 'Invalid urgency value']);
            }
            $fieldsToUpdate['urgency'] = $input['urgency'];
        }

        if (array_key_exists('min_condition', $input)) {
            if (!empty($input['min_condition']) && !in_array($input['min_condition'], self::ALLOWED_CONDITIONS, true)) {
                throw new ValidationFailedException(['min_condition' => 'Invalid condition']);
            }
            $fieldsToUpdate['min_condition'] = !empty($input['min_condition']) ? $input['min_condition'] : null;
        }

        if (isset($input['radius_km'])) {
            $r = (float) $input['radius_km'];
            if ($r < 1 || $r > 500) {
                throw new ValidationFailedException(['radius_km' => 'Radius must be between 1 and 500']);
            }
            $fieldsToUpdate['radius_km'] = $r;
        }

        if (isset($input['quantity_needed'])) {
            $newNeeded = (int) $input['quantity_needed'];
            $allocated = (int) $req['quantity_allocated'];
            if ($newNeeded < $allocated) {
                throw new ConflictException("Cannot lower quantity needed ({$newNeeded}) below already allocated quantity ({$allocated})", 'QUANTITY_BELOW_ALLOCATED');
            }
            $fieldsToUpdate['quantity_needed'] = $newNeeded;
        }

        if (!empty($fieldsToUpdate)) {
            $this->reqRepo->update($id, $fieldsToUpdate);
            $this->auditRepo->log($userId, 'requirement.updated', 'ngo_requirements', $id, 'success', $fieldsToUpdate);
        }

        $updated = $this->reqRepo->findById($id);
        return $this->formatRequirement($updated);
    }

    public function closeRequirement(int $userId, int $id): array
    {
        $ngo = $this->ngoRepo->findNgoByUserId($userId);
        if ($ngo === null) {
            throw new NotFoundException('NGO profile not found');
        }

        $req = $this->reqRepo->findById($id);
        if ($req === null || (int) $req['ngo_id'] !== (int) $ngo['id']) {
            throw new NotFoundException('Requirement not found');
        }

        $this->reqRepo->update($id, ['status' => 'closed']);
        $this->auditRepo->log($userId, 'requirement.closed', 'ngo_requirements', $id, 'success');

        $updated = $this->reqRepo->findById($id);
        return $this->formatRequirement($updated);
    }

    public function deleteRequirement(int $userId, int $id): void
    {
        $ngo = $this->ngoRepo->findNgoByUserId($userId);
        if ($ngo === null) {
            throw new NotFoundException('NGO profile not found');
        }

        $req = $this->reqRepo->findById($id);
        if ($req === null || (int) $req['ngo_id'] !== (int) $ngo['id']) {
            throw new NotFoundException('Requirement not found');
        }

        if ($req['status'] === 'draft' && !$this->reqRepo->hasAllocations($id)) {
            $this->reqRepo->delete($id);
            $this->auditRepo->log($userId, 'requirement.deleted', 'ngo_requirements', $id, 'success');
        } else {
            // Soft close
            $this->closeRequirement($userId, $id);
        }
    }

    private function formatRequirement(array $r): array
    {
        $needed    = (int) $r['quantity_needed'];
        $allocated = (int) $r['quantity_allocated'];
        $fulfilled = (int) $r['quantity_fulfilled'];
        $outstanding = max(0, $needed - $allocated);

        return [
            'id'                 => (int) $r['id'],
            'ngo_id'             => (int) $r['ngo_id'],
            'organization_name'  => $r['organization_name'] ?? null,
            'category_id'        => (int) $r['category_id'],
            'category_name'      => $r['category_name'] ?? null,
            'title'              => $r['title'],
            'description'        => $r['description'] ?? '',
            'quantity_needed'    => $needed,
            'quantity_allocated' => $allocated,
            'quantity_fulfilled' => $fulfilled,
            'outstanding'        => $outstanding,
            'urgency'            => $r['urgency'],
            'min_condition'      => $r['min_condition'] ?? null,
            'latitude'           => (float) $r['latitude'],
            'longitude'          => (float) $r['longitude'],
            'radius_km'          => (float) $r['radius_km'],
            'needed_by'          => $r['needed_by'] ?? null,
            'status'             => $r['status'],
            'created_at'         => $r['created_at'],
            'updated_at'         => $r['updated_at'] ?? null,
        ];
    }
}
