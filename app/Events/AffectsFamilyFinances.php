<?php

declare(strict_types=1);

namespace App\Events;

/**
 * Any change that can alter a family's balances, totals or groupings.
 * Listened to by InvalidateDashboardCache (BR-C04).
 */
interface AffectsFamilyFinances
{
    public function familyId(): int;
}
