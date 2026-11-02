<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\UsageLog;

/**
 * @extends BaseRepositoryInterface<UsageLog>
 */
interface UsageLogRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @param  string  $period  first day of the label month (Y-m-d)
     */
    public function countFor(string $feature, string $period): int;

    public function incrementFor(string $feature, string $period): void;

    /** Never below zero. */
    public function decrementFor(string $feature, string $period): void;
}
