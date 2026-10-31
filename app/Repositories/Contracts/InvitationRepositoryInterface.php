<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Invitation;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Invitation>
 */
interface InvitationRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Not yet accepted invitations of the active family (expired ones included).
     *
     * @return Collection<int, Invitation>
     */
    public function pending(): Collection;

    public function pendingForEmail(string $email): ?Invitation;

    /**
     * Tenant resolver (BR-I07): finds an invitation by token BEFORE the family is
     * known — the invitee is not a member yet. Bypasses the family scope on purpose.
     */
    public function resolveByToken(string $token): ?Invitation;
}
