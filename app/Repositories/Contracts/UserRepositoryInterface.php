<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\Appearance;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @extends BaseRepositoryInterface<User>
 */
interface UserRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Case-insensitive lookup for login (emails are stored lowercase).
     */
    public function findByEmail(string $email): ?User;

    public function updateAppearance(User $user, Appearance $appearance): User;

    /**
     * id => name for the given ids (archived/soft-deleted included), for display.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function namesByIds(array $ids): array;

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function apiTokens(User $user): Collection;

    /**
     * @param  list<string>  $abilities
     */
    public function createApiToken(User $user, string $name, array $abilities, \DateTimeInterface $expiresAt): NewAccessToken;

    public function revokeApiToken(User $user, int $tokenId): bool;

    public function unreadNotificationCount(User $user): int;

    /**
     * Newest notifications of the user.
     *
     * @return Collection<int, DatabaseNotification>
     */
    public function notifications(User $user, int $limit): Collection;

    /**
     * Mark one notification (or all when $id is null) as read.
     */
    public function markNotificationsRead(User $user, ?string $id = null): void;

    public function ownsNotification(User $user, string $id): bool;
}
