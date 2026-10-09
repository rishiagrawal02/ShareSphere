<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\ConflictException;
use App\Http\Exceptions\NotFoundException;
use App\Http\Exceptions\ValidationFailedException;
use App\Repositories\AuditRepository;
use App\Repositories\CategoryRepository;
use App\Support\Validator;

class CategoryService
{
    private CategoryRepository $categoryRepo;
    private AuditRepository $auditRepo;

    public function __construct(
        ?CategoryRepository $categoryRepo = null,
        ?AuditRepository $auditRepo = null
    ) {
        $this->categoryRepo = $categoryRepo ?? new CategoryRepository();
        $this->auditRepo = $auditRepo ?? new AuditRepository();
    }

    public function listPublicCategories(): array
    {
        return $this->categoryRepo->listActive();
    }

    public function listAdminCategories(): array
    {
        $categories = $this->categoryRepo->listAll();
        foreach ($categories as &$cat) {
            $cat['compatibilities'] = $this->categoryRepo->getCompatibility((int) $cat['id']);
        }
        return $categories;
    }

    public function createCategory(int $adminUserId, array $input): array
    {
        $validated = Validator::validate($input, [
            'name'        => 'required|string|min:2|max:128',
            'description' => 'string|max:500',
        ]);

        $existing = $this->categoryRepo->findByName($validated['name']);
        if ($existing !== null) {
            throw new ConflictException("A category named '{$validated['name']}' already exists", 'CATEGORY_EXISTS');
        }

        $isActive = isset($input['is_active']) ? (bool) $input['is_active'] : true;
        $category = $this->categoryRepo->create($validated['name'], $validated['description'] ?? null, $isActive);

        $this->auditRepo->log(
            $adminUserId,
            'category.created',
            'categories',
            (int) $category['id'],
            'success',
            ['name' => $category['name']]
        );

        return $category;
    }

    public function updateCategory(int $adminUserId, int $id, array $input): array
    {
        $category = $this->categoryRepo->findById($id);
        if ($category === null) {
            throw new NotFoundException('Category not found');
        }

        $fieldsToUpdate = [];

        if (isset($input['name'])) {
            $name = trim((string) $input['name']);
            if (strlen($name) < 2 || strlen($name) > 128) {
                throw new ValidationFailedException(['name' => 'Name must be between 2 and 128 characters']);
            }
            if (strtolower($name) !== strtolower($category['name'])) {
                $existing = $this->categoryRepo->findByName($name);
                if ($existing !== null && (int) $existing['id'] !== $id) {
                    throw new ConflictException("A category named '{$name}' already exists", 'CATEGORY_EXISTS');
                }
            }
            $fieldsToUpdate['name'] = $name;
        }

        if (array_key_exists('description', $input)) {
            $fieldsToUpdate['description'] = !empty($input['description']) ? trim((string) $input['description']) : null;
        }

        if (isset($input['is_active'])) {
            $isActive = (bool) $input['is_active'];
            if (!$isActive && (bool) $category['is_active']) {
                // Deactivation check: ensure no active donations/requirements currently use it
                $usage = $this->categoryRepo->countActiveUsage($id);
                if ($usage['total_active'] > 0) {
                    throw new ConflictException(
                        "Cannot deactivate category: currently referenced by {$usage['active_donations']} active donation(s) and {$usage['active_requirements']} active requirement(s)",
                        'CATEGORY_IN_USE'
                    );
                }
            }
            $fieldsToUpdate['is_active'] = $isActive;
        }

        if (!empty($fieldsToUpdate)) {
            $this->categoryRepo->update($id, $fieldsToUpdate);

            $this->auditRepo->log(
                $adminUserId,
                'category.updated',
                'categories',
                $id,
                'success',
                $fieldsToUpdate
            );
        }

        return $this->categoryRepo->findById($id);
    }

    public function setCompatibility(int $adminUserId, int $categoryId, array $compatibilities): array
    {
        $category = $this->categoryRepo->findById($categoryId);
        if ($category === null) {
            throw new NotFoundException('Category not found');
        }

        // Validate compatibility entries
        $validatedList = [];
        foreach ($compatibilities as $idx => $entry) {
            $compId = (int) ($entry['compatible_category_id'] ?? 0);
            $score  = (float) ($entry['score_factor'] ?? 0);

            if ($compId <= 0 || $compId === $categoryId) {
                throw new ValidationFailedException(["compatibility.{$idx}.compatible_category_id" => 'Invalid compatible category ID (cannot be self or <= 0)']);
            }

            if ($score <= 0 || $score > 100) {
                throw new ValidationFailedException(["compatibility.{$idx}.score_factor" => 'Score factor must be between 1 and 100']);
            }

            $targetCat = $this->categoryRepo->findById($compId);
            if ($targetCat === null) {
                throw new NotFoundException("Compatible category ID {$compId} does not exist");
            }

            $validatedList[] = [
                'compatible_category_id' => $compId,
                'score_factor'           => $score,
            ];
        }

        $this->categoryRepo->setCompatibility($categoryId, $validatedList);

        $this->auditRepo->log(
            $adminUserId,
            'category.compatibility.updated',
            'categories',
            $categoryId,
            'success',
            ['mappings_count' => count($validatedList)]
        );

        return $this->categoryRepo->getCompatibility($categoryId);
    }
}
