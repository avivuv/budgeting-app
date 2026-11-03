<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Budget;
use App\Repositories\Contracts\BudgetRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Budget>
 */
class BudgetRepository extends BaseRepository implements BudgetRepositoryInterface
{
    public function __construct(Budget $model)
    {
        parent::__construct($model);
    }

    public function forPeriod(string $period): Collection
    {
        return $this->query()
            ->with('category:id,name,icon,color,parent_id,is_archived')
            ->where('period', $period)
            ->orderByDesc('amount')
            ->get();
    }

    public function forCategory(int $categoryId): Collection
    {
        return $this->query()->where('category_id', $categoryId)->orderBy('period')->get();
    }

    public function findForCategory(int $categoryId, string $period): ?Budget
    {
        return $this->query()
            ->where('category_id', $categoryId)
            ->where('period', $period)
            ->first();
    }
}
