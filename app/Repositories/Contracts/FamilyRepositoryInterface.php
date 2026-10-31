<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\FamilyRole;
use App\Models\Family;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Family>
 */
interface FamilyRepositoryInterface extends BaseRepositoryInterface
{
    public function addMember(Family $family, User $user, FamilyRole $role): void;

    /**
     * Families the user belongs to, with the membership pivot (role) loaded.
     *
     * @return Collection<int, Family>
     */
    public function forUser(User $user): Collection;

    /**
     * The family only if the user is a member of it (with membership loaded).
     */
    public function findForMember(int $familyId, User $user): ?Family;

    public function roleOf(Family $family, User $user): ?FamilyRole;

    public function setCurrentFamily(User $user, ?Family $family): void;

    public function slugExists(string $slug): bool;
}
