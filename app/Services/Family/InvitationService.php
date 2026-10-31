<?php

declare(strict_types=1);

namespace App\Services\Family;

use App\Enums\FamilyRole;
use App\Exceptions\Family\InvitationRuleException;
use App\Exceptions\Family\MembershipRuleException;
use App\Models\Family;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\FamilyInvitationNotification;
use App\Repositories\Contracts\FamilyRepositoryInterface;
use App\Repositories\Contracts\InvitationRepositoryInterface;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Inviting people into a family (BR-I02, BR-I03, BR-I07).
 */
class InvitationService
{
    public const int VALID_DAYS = 7;

    public function __construct(
        private readonly InvitationRepositoryInterface $invitations,
        private readonly FamilyRepositoryInterface $families,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return Collection<int, Invitation>
     */
    public function pending(): Collection
    {
        return $this->invitations->pending();
    }

    /**
     * Invite an email into the active family. A pending invitation for the same
     * email is replaced (new token, new expiry).
     */
    public function invite(User $inviter, string $email, FamilyRole $role): Invitation
    {
        $family = $this->tenant->get();
        $email = mb_strtolower(trim($email));
        $inviterRole = $this->families->roleOf($family, $inviter);

        if ($role === FamilyRole::Owner) {
            throw InvitationRuleException::invalidRole();
        }

        // BR-I03: admins cannot create other admins.
        if ($role === FamilyRole::Admin && $inviterRole !== FamilyRole::Owner) {
            throw MembershipRuleException::onlyOwnerManagesAdmins();
        }

        if ($this->families->isMember($family, $email)) {
            throw InvitationRuleException::alreadyMember();
        }

        $invitation = DB::transaction(function () use ($email, $role, $inviter): Invitation {
            $existing = $this->invitations->pendingForEmail($email);

            if ($existing !== null) {
                $this->invitations->delete($existing);
            }

            return $this->invitations->create([
                'email' => $email,
                'role' => $role,
                'token' => Str::random(64),
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays(self::VALID_DAYS),
            ]);
        });

        Notification::route('mail', $email)->notify(
            new FamilyInvitationNotification($invitation, $family->name, $inviter->name),
        );

        return $invitation;
    }

    public function revoke(Invitation $invitation): void
    {
        $this->invitations->delete($invitation);
    }

    /**
     * Tenant resolver (BR-I07): look the invitation up by token before any family is active.
     */
    public function findByToken(string $token): ?Invitation
    {
        return $this->invitations->resolveByToken($token);
    }

    /**
     * Join the family: valid 7 days, single use, only for the invited email (BR-I02).
     */
    public function accept(string $token, User $user): Family
    {
        $invitation = $this->invitations->resolveByToken($token)
            ?? throw InvitationRuleException::alreadyAccepted();

        $this->assertAcceptable($invitation, $user);

        /** @var Family $family */
        $family = $invitation->family;

        return DB::transaction(function () use ($invitation, $family, $user): Family {
            if ($this->families->findForMember($family->id, $user) === null) {
                $this->families->addMember($family, $user, $invitation->role);
            }

            $this->invitations->update($invitation, ['accepted_at' => now()]);
            $this->families->setCurrentFamily($user, $family);

            return $family;
        });
    }

    public function assertAcceptable(Invitation $invitation, User $user): void
    {
        if ($invitation->isAccepted()) {
            throw InvitationRuleException::alreadyAccepted();
        }

        if ($invitation->isExpired()) {
            throw InvitationRuleException::expired();
        }

        if (mb_strtolower($user->email) !== $invitation->email) {
            throw InvitationRuleException::emailMismatch();
        }
    }
}
