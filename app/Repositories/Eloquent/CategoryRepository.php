<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Transaction;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Category>
 */
class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    public function __construct(Category $model)
    {
        parent::__construct($model);
    }

    public function tree(?CategoryType $type = null, bool $includeArchived = false): Collection
    {
        $ordered = fn ($q) => $q
            ->when(! $includeArchived, fn ($q) => $q->where('is_archived', false))
            ->orderBy('sort_order')
            ->orderBy('name');

        return $this->query()
            ->whereNull('parent_id')
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->tap($ordered)
            ->with(['children' => $ordered])
            ->orderBy('type')
            ->get();
    }

    public function nameExists(CategoryType $type, ?int $parentId, string $name, ?int $ignoreId = null): bool
    {
        return $this->query()
            ->where('type', $type)
            ->where('parent_id', $parentId)
            ->where('name', $name)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    public function hasTransactions(Category $category): bool
    {
        return Transaction::withTrashed()->where('category_id', $category->id)->exists();
    }

    public function hasChildren(Category $category): bool
    {
        return $category->children()->exists();
    }

    public function namesByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = $this->query()->whereIn('id', $ids)->pluck('name', 'id')->all();

        return $names;
    }

    public function reassignTransactions(Category $from, Category $to): int
    {
        return Transaction::withTrashed()
            ->where('category_id', $from->id)
            ->update(['category_id' => $to->id]);
    }
}
