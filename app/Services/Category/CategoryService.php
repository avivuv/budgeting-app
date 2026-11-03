<?php

declare(strict_types=1);

namespace App\Services\Category;

use App\DTOs\Category\CategoryData;
use App\Enums\CategoryType;
use App\Events\Category\CategoryChanged;
use App\Exceptions\Category\CategoryRuleException;
use App\Models\Category;
use App\Repositories\Contracts\BudgetRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
        private readonly BudgetRepositoryInterface $budgets,
    ) {}

    /**
     * @return Collection<int, Category>
     */
    public function tree(?CategoryType $type = null, bool $includeArchived = false): Collection
    {
        return $this->categories->tree($type, $includeArchived);
    }

    public function find(int $id): Category
    {
        return $this->categories->findOrFail($id);
    }

    public function create(CategoryData $data): Category
    {
        $attributes = $data->toModelArray();
        $this->assertValid($data->type, $data->parentId, $attributes['name']);

        return $this->categories->create($attributes);
    }

    public function update(Category $category, CategoryData $data): Category
    {
        $attributes = $data->toModelArray();

        // BR-E06: the type of a category with transactions or children cannot change.
        if ($data->type !== $category->type
            && ($this->categories->hasTransactions($category) || $this->categories->hasChildren($category))) {
            throw CategoryRuleException::typeLocked();
        }

        if ($data->parentId !== null && $this->categories->hasChildren($category)) {
            throw CategoryRuleException::parentTooDeep();
        }

        $this->assertValid($data->type, $data->parentId, $attributes['name'], $category->id);

        $category = $this->categories->update($category, $attributes);
        CategoryChanged::dispatch($category);

        return $category;
    }

    public function setArchived(Category $category, bool $archived): Category
    {
        $category = $this->categories->update($category, ['is_archived' => $archived]);
        CategoryChanged::dispatch($category);

        return $category;
    }

    /**
     * Unused categories without children can be deleted; used ones must be
     * archived or merged (BR-E03).
     */
    public function delete(Category $category): void
    {
        if ($this->categories->hasChildren($category)) {
            throw CategoryRuleException::hasChildren();
        }

        if ($this->categories->hasTransactions($category)) {
            throw CategoryRuleException::inUse();
        }

        $this->categories->delete($category);
        CategoryChanged::dispatch($category);
    }

    /**
     * Move every transaction and budget of $from into $to, then delete $from (BR-E03).
     * A budget for a period $to already has is added to it.
     */
    public function merge(Category $from, Category $to): int
    {
        if ($from->is($to)) {
            throw CategoryRuleException::mergeIntoItself();
        }

        if ($from->type !== $to->type) {
            throw CategoryRuleException::mergeTypeMismatch();
        }

        if ($to->is_archived) {
            throw CategoryRuleException::mergeIntoArchived();
        }

        if ($this->categories->hasChildren($from)) {
            throw CategoryRuleException::hasChildren();
        }

        return DB::transaction(function () use ($from, $to): int {
            $moved = $this->categories->reassignTransactions($from, $to);
            $this->moveBudgets($from, $to);
            $this->categories->delete($from);
            CategoryChanged::dispatch($to);

            return $moved;
        });
    }

    private function moveBudgets(Category $from, Category $to): void
    {
        foreach ($this->budgets->forCategory($from->id) as $budget) {
            $existing = $this->budgets->findForCategory($to->id, $budget->period->toDateString());

            if ($existing === null) {
                $this->budgets->update($budget, ['category_id' => $to->id]);

                continue;
            }

            $this->budgets->update($existing, ['amount' => $existing->amount + $budget->amount]);
            $this->budgets->delete($budget);
        }
    }

    /**
     * BR-E01 (max two levels, same type as parent) and BR-E02 (unique name).
     */
    private function assertValid(CategoryType $type, ?int $parentId, string $name, ?int $ignoreId = null): void
    {
        if ($parentId !== null) {
            // Scoped lookup: a parent from another family is "not found" (BR-I06).
            $parent = $this->categories->findOrFail($parentId);

            if ($parent->parent_id !== null || $parent->id === $ignoreId) {
                throw CategoryRuleException::parentTooDeep();
            }

            if ($parent->type !== $type) {
                throw CategoryRuleException::parentTypeMismatch();
            }
        }

        if ($this->categories->nameExists($type, $parentId, $name, $ignoreId)) {
            throw CategoryRuleException::nameTaken();
        }
    }
}
