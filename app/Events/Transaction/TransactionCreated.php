<?php

declare(strict_types=1);

namespace App\Events\Transaction;

use App\Events\AffectsFamilyFinances;
use App\Models\Transaction;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A transaction was created (dispatched by TransactionService).
 */
class TransactionCreated implements AffectsFamilyFinances, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Transaction $transaction,
    ) {}

    public function familyId(): int
    {
        return $this->transaction->family_id;
    }
}
