<?php

declare(strict_types=1);

namespace Quiz\Model;

/**
 * Category Model
 *
 * Represents a quiz category.
 *
 * @package Quiz\Model
 */
final class Category
{
    public function __construct(
        public readonly int $id,
        public string $name,
        public ?string $description = null,
        public int $parentId = 0,
        public ?string $image = null,
        public int $createdAt = 0,
        public int $updatedAt = 0,
    ) {}

    /**
     * Get full category path (for breadcrumbs)
     *
     * @param array<int, Category> $allCategories All categories indexed by ID
     * @return array<int, string> Path from root to this category
     */
    public function getPath(array $allCategories): array
    {
        $path = [];
        $current = $this;

        while ($current) {
            array_unshift($path, $current->name);
            if ($current->parentId === 0) {
                break;
            }
            $current = $allCategories[$current->parentId] ?? null;
            if ($current === null) {
                break;
            }
        }

        return $path;
    }
}
