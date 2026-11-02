<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

/**
 * Every member sees budgets; owner/admin set them (family-wide limits).
 */
class BudgetPolicy extends FamilyPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isMember($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Budget $budget): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $this->canManage($user);
    }
}
