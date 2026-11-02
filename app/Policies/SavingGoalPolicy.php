<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SavingGoal;
use App\Models\User;

/**
 * Every member sees goals; owner/admin define them; anyone who can write
 * may add or take out money (BR-B09).
 */
class SavingGoalPolicy extends FamilyPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isMember($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, SavingGoal $goal): bool
    {
        return $this->canManage($user);
    }

    public function contribute(User $user, SavingGoal $goal): bool
    {
        return $this->canWrite($user);
    }

    public function delete(User $user, SavingGoal $goal): bool
    {
        return $this->canManage($user);
    }
}
