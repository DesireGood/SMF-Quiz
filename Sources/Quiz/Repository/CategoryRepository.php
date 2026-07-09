<?php

declare(strict_types=1);

namespace Quiz\Repository;

use Quiz\Model\Category;

/**
 * Category Repository
 *
 * Handles all database operations for categories.
 *
 * @package Quiz\Repository
 */
final class CategoryRepository extends BaseRepository
{
    /**
     * Find category by ID
     *
     * @param int $id Category ID
     * @return Category|null
     */
    public function findById(int $id): ?Category
    {
        $result = $this->query(
            'SELECT id_category, name, description, parent_id, image, created, updated '
            . 'FROM {db_prefix}quiz_category WHERE id_category = ?',
            ['int' => $id]
        );

        if (!$row = $this->fetchRow($result)) {
            return null;
        }

        $this->freeResult($result);
        return $this->rowToCategory($row);
    }

    /**
     * Find all categories
     *
     * @return array<int, Category>
     */
    public function findAll(): array
    {
        $result = $this->query(
            'SELECT id_category, name, description, parent_id, image, created, updated '
            . 'FROM {db_prefix}quiz_category ORDER BY name ASC'
        );

        $categories = [];
        foreach ($this->fetchAll($result) as $row) {
            $categories[$row['id_category']] = $this->rowToCategory($row);
        }
        $this->freeResult($result);

        return $categories;
    }

    /**
     * Find child categories
     *
     * @param int $parentId Parent category ID
     * @return array<int, Category>
     */
    public function findChildren(int $parentId): array
    {
        $result = $this->query(
            'SELECT id_category, name, description, parent_id, image, created, updated '
            . 'FROM {db_prefix}quiz_category WHERE parent_id = ? ORDER BY name ASC',
            ['int' => $parentId]
        );

        $categories = [];
        foreach ($this->fetchAll($result) as $row) {
            $categories[$row['id_category']] = $this->rowToCategory($row);
        }
        $this->freeResult($result);

        return $categories;
    }

    /**
     * Save category
     *
     * @param Category $category
     * @return int Category ID
     */
    public function save(Category $category): int
    {
        if ($category->id === 0) {
            return $this->create($category);
        }
        return $this->update($category);
    }

    /**
     * Create category
     *
     * @param Category $category
     * @return int Inserted ID
     */
    private function create(Category $category): int
    {
        $this->query(
            'INSERT INTO {db_prefix}quiz_category (name, description, parent_id, image, created, updated) '
            . 'VALUES (?, ?, ?, ?, ?, ?)',
            [
                'string' => [$category->name, $category->description ?? '', $category->image ?? ''],
                'int' => [$category->parentId, (int)time(), (int)time()],
            ]
        );

        return $this->getInsertId();
    }

    /**
     * Update category
     *
     * @param Category $category
     * @return int Category ID
     */
    private function update(Category $category): int
    {
        $this->query(
            'UPDATE {db_prefix}quiz_category SET name = ?, description = ?, parent_id = ?, image = ?, updated = ? WHERE id_category = ?',
            [
                'string' => [$category->name, $category->description ?? '', $category->image ?? ''],
                'int' => [$category->parentId, (int)time(), $category->id],
            ]
        );

        return $category->id;
    }

    /**
     * Delete category
     *
     * @param int $id Category ID
     * @return bool
     */
    public function delete(int $id): bool
    {
        $this->query(
            'DELETE FROM {db_prefix}quiz_category WHERE id_category = ?',
            ['int' => $id]
        );

        return true;
    }

    /**
     * Convert database row to Category model
     *
     * @param array<string, mixed> $row
     * @return Category
     */
    private function rowToCategory(array $row): Category
    {
        return new Category(
            id: (int)$row['id_category'],
            name: $row['name'],
            description: $row['description'] ?: null,
            parentId: (int)$row['parent_id'],
            image: $row['image'] ?: null,
            createdAt: (int)$row['created'],
            updatedAt: (int)$row['updated'],
        );
    }
}
