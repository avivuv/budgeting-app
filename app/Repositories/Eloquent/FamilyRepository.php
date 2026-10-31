<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\FamilyRole;
use App\Models\Family;
use App\Models\User;
use App\Repositories\Contracts\FamilyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Family>
 */
class FamilyRepository extends BaseRepository implements FamilyRepositoryInterface
{
    public function __construct(Family $model)
    {
        parent::__construct($model);
    }

    public function addMember(Family $family, User $user, FamilyRole $role): void
    {
        $family->members()->attach($user->id, [
            'role' => $role->value,
            'joined_at' => now(),
        ]);
    }

    public function members(Family $family): Collection
    {
        return $family->members()
            ->orderByRaw("FIELD(family_user.role, 'owner', 'admin', 'member', 'viewer')")
            ->orderBy('users.name')
            ->get();
    }

    public function findMember(Family $family, int $userId): ?User
    {
        return $family->members()->whereKey($userId)->first();
    }

    public function updateRole(Family $family, User $user, FamilyRole $role): void
    {
        $family->members()->updateExistingPivot($user->id, ['role' => $role->value]);
    }

    public function removeMember(Family $family, User $user): void
    {
        $family->members()->detach($user->id);

        if ($user->current_family_id === $family->id) {
            $this->setCurrentFamily($user, null);
        }
    }

    public function isMember(Family $family, string $email): bool
    {
        return $family->members()->where('users.email', mb_strtolower($email))->exists();
    }

    public function forUser(User $user): Collection
    {
        return $user->families()->orderBy('families.name')->get();
    }

    public function findForMember(int $familyId, User $user): ?Family
    {
        return $user->families()->whereKey($familyId)->first();
    }

    public function roleOf(Family $family, User $user): ?FamilyRole
    {
        return $this->findForMember($family->id, $user)?->membership?->role;
    }

    public function setCurrentFamily(User $user, ?Family $family): void
    {
        $user->forceFill(['current_family_id' => $family?->id])->save();
    }

    public function slugExists(string $slug): bool
    {
        return $this->query()->withTrashed()->where('slug', $slug)->exists();
    }
}
