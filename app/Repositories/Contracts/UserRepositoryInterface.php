<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\Appearance;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @extends BaseRepositoryInterface<User>
 */
interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function updateAppearance(User $user, Appearance $appearance): User;

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function apiTokens(User $user): Collection;

    /**
     * @param  list<string>  $abilities
     */
    public function createApiToken(User $user, string $name, array $abilities, \DateTimeInterface $expiresAt): NewAccessToken;

    public function revokeApiToken(User $user, int $tokenId): bool;
}
