<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Personal access tokens for the REST API (docs/13-rest-api.md).
 * Abilities: "read" (GET) and "write" (create/update/delete).
 */
class ApiTokenService
{
    public const string READ = 'read';

    public const string WRITE = 'write';

    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function list(User $user): Collection
    {
        return $this->users->apiTokens($user);
    }

    /**
     * The plain-text token is only available here, right after creation.
     */
    public function create(User $user, string $name, bool $canWrite): NewAccessToken
    {
        $abilities = $canWrite ? [self::READ, self::WRITE] : [self::READ];
        $expiresAt = now()->addMinutes((int) config('sanctum.expiration'));

        return $this->users->createApiToken($user, $name, $abilities, $expiresAt);
    }

    public function revoke(User $user, int $tokenId): bool
    {
        return $this->users->revokeApiToken($user, $tokenId);
    }
}
