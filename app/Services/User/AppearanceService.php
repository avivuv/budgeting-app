<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\Appearance;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class AppearanceService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Light/dark preference: the signed-in user's saved choice (synced across
     * devices), otherwise the guest's cookie, otherwise follow the OS setting.
     *
     * @param  mixed  $cookieValue  raw, untrusted cookie input (may be an array)
     */
    public function resolve(?User $user, mixed $cookieValue): Appearance
    {
        if ($user !== null) {
            return $user->appearance;
        }

        if (! is_string($cookieValue)) {
            return Appearance::System;
        }

        return Appearance::tryFrom($cookieValue) ?? Appearance::System;
    }

    public function update(User $user, Appearance $appearance): User
    {
        return $this->users->updateAppearance($user, $appearance);
    }
}
