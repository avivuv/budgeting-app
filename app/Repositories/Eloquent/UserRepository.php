<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\Appearance;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

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

    public function apiTokens(User $user): Collection
    {
        /** @var Collection<int, PersonalAccessToken> $tokens */
        $tokens = $user->tokens()->latest('id')->get();

        return $tokens;
    }

    public function createApiToken(User $user, string $name, array $abilities, \DateTimeInterface $expiresAt): NewAccessToken
    {
        return $user->createToken($name, $abilities, $expiresAt);
    }

    public function revokeApiToken(User $user, int $tokenId): bool
    {
        return $user->tokens()->whereKey($tokenId)->delete() > 0;
    }
}
