<?php

declare(strict_types=1);

namespace App\Events\Budget;

use App\Events\AffectsFamilyFinances;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Budgets of a family were set, copied or deleted (dashboard insights use them).
 */
class BudgetChanged implements AffectsFamilyFinances, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $familyIdValue,
    ) {}

    public function familyId(): int
    {
        return $this->familyIdValue;
    }
}
