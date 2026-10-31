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
