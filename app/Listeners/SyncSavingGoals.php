<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AffectsFamilyFinances;
use App\Services\Savings\SavingGoalService;
use App\Services\Tenancy\TenantContext;

/**
 * BR-K03: wallet-linked goals follow the wallet balance, so "achieved" is
 * re-evaluated whenever the family's money changes.
 */
class SyncSavingGoals
{
    public function __construct(
        private readonly SavingGoalService $goals,
        private readonly TenantContext $tenant,
    ) {}

    public function handle(AffectsFamilyFinances $event): void
    {
        if ($this->tenant->has() && $this->tenant->id() === $event->familyId()) {
            $this->goals->syncAll();
        }
    }
}
