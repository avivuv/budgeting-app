<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

/**
 * BR-B09: members edit their own transactions, owner/admin edit all, viewers read only.
 */
class TransactionPolicy extends FamilyPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isMember($user);
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $this->isMember($user);
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $this->canManage($user)
            || ($this->canWrite($user) && $transaction->created_by === $user->id);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }
}
