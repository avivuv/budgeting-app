<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AffectsFamilyFinances;
use App\Services\Report\DashboardService;

/**
 * BR-C04: balances and totals are recalculated right after any change to the
 * family's transactions, wallets or categories (INV-08).
 */
class InvalidateDashboardCache
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function handle(AffectsFamilyFinances $event): void
    {
        $this->dashboard->invalidate($event->familyId());
    }
}
