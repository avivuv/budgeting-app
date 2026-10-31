<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;

/**
 * Every member sees wallets; only owner/admin manage them.
 */
class WalletPolicy extends FamilyPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isMember($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Wallet $wallet): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Wallet $wallet): bool
    {
        return $this->canManage($user);
    }
}
