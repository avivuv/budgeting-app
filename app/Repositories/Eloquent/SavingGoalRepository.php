<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\SavingGoal;
use App\Repositories\Contracts\SavingGoalRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<SavingGoal>
 */
class SavingGoalRepository extends BaseRepository implements SavingGoalRepositoryInterface
{
    public function __construct(SavingGoal $model)
    {
        parent::__construct($model);
    }

    public function listForFamily(): Collection
    {
        return $this->query()
            ->with('wallet:id,name,is_archived')
            ->orderByRaw('achieved_at IS NOT NULL')
            ->orderByRaw('deadline IS NULL')
            ->orderBy('deadline')
            ->orderBy('name')
            ->get();
    }
}
