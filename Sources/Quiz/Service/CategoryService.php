<?php

declare(strict_types=1);

namespace Quiz\Service;

use Quiz\Model\Category;
use Quiz\Repository\CategoryRepository;
use Quiz\Traits\HasErrorHandling;

/**
 * CategoryService
 *
 * Business logic for category CRUD operations.
 *
 * @package Quiz\Service
 */
final class CategoryService
{
    use HasErrorHandling;

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    /**
     * Get a category by ID
     *
     * @param int $id Category ID
     * @return Category|null
     */
    public function getById(int $id): ?Category
    {
        try {
            return $this->categoryRepository->findById($id);
        } catch (\Throwable $e) {
            $this->handleException($e, 'load', 'Unable to load category.');
            return null;
        }
    }

    /**
     * Get all categories
     *
     * @return array<int, Category> Categories indexed by ID
     */
    public function getAll(): array
    {
        try {
            return $this->categoryRepository->findAll();
        } catch (\Throwable $e) {
            $this->handleException($e, 'load', 'Unable to load categories.');
            return [];
        }
    }

    /**
     * Get child categories for a parent
     *
     * @param int $parentId Parent category ID
     * @return array<int, Category>
     */
    public function getChildren(int $parentId): array
    {
        try {
            return $this->categoryRepository->findChildren($parentId);
        } catch (\Throwable $e) {
            $this->handleException($e, 'load', 'Unable to load child categories.');
            return [];
        }
    }

    /**
     * Create or update a category
     *
     * @param array<string, mixed> $data Category data from form input
     * @return int|false Saved category ID on success, false on failure
     */
    public function save(array $data): int|false
    {
        try {
            $name = trim((string)($data['name'] ?? ''));
            if ($name === '') {
                $this->addError('name', 'Category name cannot be empty.');
                return false;
            }

            $category = new Category(
                id: (int)($data['id_category'] ?? 0),
                name: $name,
                description: trim((string)($data['description'] ?? '')) ?: null,
                parentId: max(0, (int)($data['parent_id'] ?? 0)),
                image: trim((string)($data['image'] ?? '')) ?: null,
            );

            return $this->categoryRepository->save($category);
        } catch (\Throwable $e) {
            $this->handleException($e, 'save', 'Unable to save category.');
            return false;
        }
    }

    /**
     * Delete a category
     *
     * @param int $id Category ID
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $this->categoryRepository->delete($id);
            return true;
        } catch (\Throwable $e) {
            $this->handleException($e, 'delete', 'Unable to delete category.');
            return false;
        }
    }

    /**
     * Build a flat list suitable for a drop-down select element
     *
     * @return array<int, string> [id => name]
     */
    public function getSelectList(): array
    {
        $categories = $this->getAll();
        $list = [];
        foreach ($categories as $cat) {
            $list[$cat->id] = $cat->name;
        }
        return $list;
    }
}
