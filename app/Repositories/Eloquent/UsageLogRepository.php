<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\UsageLog;
use App\Repositories\Contracts\UsageLogRepositoryInterface;

/**
 * @extends BaseRepository<UsageLog>
 */
class UsageLogRepository extends BaseRepository implements UsageLogRepositoryInterface
{
    public function __construct(UsageLog $model)
    {
        parent::__construct($model);
    }

    public function countFor(string $feature, string $period): int
    {
        return (int) $this->query()
            ->where('feature', $feature)
            ->whereDate('period', $period)
            ->value('count');
    }

    public function incrementFor(string $feature, string $period): void
    {
        $log = $this->query()->firstOrCreate(['feature' => $feature, 'period' => $period], ['count' => 0]);

        $this->query()->whereKey($log->id)->increment('count');
    }

    public function decrementFor(string $feature, string $period): void
    {
        $this->query()
            ->where('feature', $feature)
            ->whereDate('period', $period)
            ->where('count', '>', 0)
            ->decrement('count');
    }
}
