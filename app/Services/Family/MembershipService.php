<?php

declare(strict_types=1);

namespace App\Services\Family;

use App\Enums\FamilyRole;
use App\Exceptions\Family\InvitationRuleException;
use App\Exceptions\Family\MembershipRuleException;
use App\Models\User;
use App\Repositories\Contracts\FamilyRepositoryInterface;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Members and roles of the active family (BR-I01, BR-I03, BR-I04).
 */
class MembershipService
{
    public function __construct(
        private readonly FamilyRepositoryInterface $families,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        return $this->families->members($this->tenant->get());
    }

    public function findMember(int $userId): User
    {
        return $this->families->findMember($this->tenant->get(), $userId)
            ?? throw (new ModelNotFoundException)->setModel(User::class, [$userId]);
    }

    public function changeRole(User $actor, User $member, FamilyRole $role): void
    {
        $this->assertCanManage($actor, $member, $role);

        $this->families->updateRole($this->tenant->get(), $member, $role);
    }

    /**
     * Their past transactions stay, still attributed to them (BR-I04).
     */
    public function remove(User $actor, User $member): void
    {
        $this->assertCanManage($actor, $member);

        $this->families->removeMember($this->tenant->get(), $member);
    }

    /**
     * Leave a family by id (REST API), without it being the active one.
     * Returns false when the user is not a member (BR-I06).
     */
    public function leaveFamily(User $user, int $familyId): bool
    {
        $family = $this->families->findForMember($familyId, $user);

        if ($family === null) {
            return false;
        }

        $this->tenant->run($family, fn () => $this->leave($user));

        return true;
    }

    public function leave(User $user): void
    {
        if ($this->families->roleOf($this->tenant->get(), $user) === FamilyRole::Owner) {
            throw MembershipRuleException::ownerCannotLeave();
        }

        $this->families->removeMember($this->tenant->get(), $user);
    }

    /**
     * BR-I01: exactly one owner, never changed or removed here.
     * BR-I03: only the owner manages admins; admins manage members and viewers.
     */
    private function assertCanManage(User $actor, User $member, ?FamilyRole $newRole = null): void
    {
        $family = $this->tenant->get();
        $actorRole = $this->families->roleOf($family, $actor);
        $memberRole = $this->families->roleOf($family, $member);

        if ($memberRole === FamilyRole::Owner || $newRole === FamilyRole::Owner) {
            throw $newRole === FamilyRole::Owner ? InvitationRuleException::invalidRole() : MembershipRuleException::ownerIsFixed();
        }

        $touchesAdmin = $memberRole === FamilyRole::Admin || $newRole === FamilyRole::Admin;

        if ($touchesAdmin && $actorRole !== FamilyRole::Owner) {
            throw MembershipRuleException::onlyOwnerManagesAdmins();
        }
    }
}
