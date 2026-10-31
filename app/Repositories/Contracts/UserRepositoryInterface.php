<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\Appearance;
use App\Models\User;

/**
 * @extends BaseRepositoryInterface<User>
 */
interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function updateAppearance(User $user, Appearance $appearance): User;
}
