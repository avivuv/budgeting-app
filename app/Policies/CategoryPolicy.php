<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Every member sees categories; only owner/admin manage them.
 */
class CategoryPolicy extends FamilyPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isMember($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->canManage($user);
    }
}
