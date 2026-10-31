<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Category>
 */
interface CategoryRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Top-level categories with their children, ordered for display.
     *
     * @return Collection<int, Category>
     */
    public function tree(?CategoryType $type = null, bool $includeArchived = false): Collection;

    public function nameExists(CategoryType $type, ?int $parentId, string $name, ?int $ignoreId = null): bool;

    public function hasTransactions(Category $category): bool;

    public function hasChildren(Category $category): bool;

    /**
     * id => name for the given ids (archived/soft-deleted included), for display.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function namesByIds(array $ids): array;

    /**
     * Move all transactions (including soft-deleted) from one category to another (BR-E03).
     */
    public function reassignTransactions(Category $from, Category $to): int;
}
