<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\SavingGoal;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<SavingGoal>
 */
interface SavingGoalRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Unfinished goals first (nearest deadline), then achieved ones; wallet loaded.
     *
     * @return Collection<int, SavingGoal>
     */
    public function listForFamily(): Collection;
}
