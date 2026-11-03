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

    public function findByEmail(string $email): ?User
    {
        /** @var User|null $user */
        $user = $this->query()->where('email', mb_strtolower(trim($email)))->first();

        return $user;
    }

    public function updateAppearance(User $user, Appearance $appearance): User
    {
        $user->forceFill(['appearance' => $appearance])->save();

        return $user;
    }

    public function namesByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = $this->query()->whereIn('id', $ids)->pluck('name', 'id')->all();

        return $names;
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

    public function unreadNotificationCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function notifications(User $user, int $limit): Collection
    {
        return $user->notifications()->latest()->limit($limit)->get();
    }

    public function markNotificationsRead(User $user, ?string $id = null): void
    {
        $user->unreadNotifications()
            ->when($id !== null, fn ($query) => $query->whereKey($id))
            ->update(['read_at' => now()]);
    }

    public function ownsNotification(User $user, string $id): bool
    {
        return $user->notifications()->whereKey($id)->exists();
    }
}
