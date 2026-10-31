<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\Appearance;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function updateAppearance(User $user, Appearance $appearance): User
    {
        $user->forceFill(['appearance' => $appearance])->save();

        return $user;
    }
}
