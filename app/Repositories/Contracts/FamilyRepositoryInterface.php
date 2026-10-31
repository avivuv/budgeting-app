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
     * Members with their membership pivot (role, joined_at), owner first.
     *
     * @return Collection<int, User>
     */
    public function members(Family $family): Collection;

    public function findMember(Family $family, int $userId): ?User;

    public function updateRole(Family $family, User $user, FamilyRole $role): void;

    public function removeMember(Family $family, User $user): void;

    public function isMember(Family $family, string $email): bool;

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
