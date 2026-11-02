<?php

declare(strict_types=1);

namespace App\Events\Transaction;

use App\Events\AffectsFamilyFinances;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A transaction was updated (dispatched by TransactionService).
 */
class TransactionUpdated implements AffectsFamilyFinances, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  CarbonImmutable|null  $previousDate  date before the edit, so its period is re-checked too
     */
    public function __construct(
        public readonly Transaction $transaction,
        public readonly ?CarbonImmutable $previousDate = null,
    ) {}

    public function familyId(): int
    {
        return $this->transaction->family_id;
    }
}
