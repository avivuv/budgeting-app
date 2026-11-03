<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Budget;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Budget>
 */
interface BudgetRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Budgets of one period (first day of the label month), category loaded.
     *
     * @return Collection<int, Budget>
     */
    public function forPeriod(string $period): Collection;

    public function findForCategory(int $categoryId, string $period): ?Budget;

    /**
     * Budgets of a category in every period (BR-E03 merge).
     *
     * @return Collection<int, Budget>
     */
    public function forCategory(int $categoryId): Collection;
}
