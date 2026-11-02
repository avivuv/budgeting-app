<?php

declare(strict_types=1);

namespace App\Events\Wallet;

use App\Events\AffectsFamilyFinances;
use App\Models\Wallet;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A wallet was created, changed, archived or deleted.
 */
class WalletChanged implements AffectsFamilyFinances, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Wallet $wallet,
    ) {}

    public function familyId(): int
    {
        return $this->wallet->family_id;
    }
}
