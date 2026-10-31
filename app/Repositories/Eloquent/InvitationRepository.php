<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Invitation;
use App\Models\Scopes\FamilyScope;
use App\Repositories\Contracts\InvitationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Invitation>
 */
class InvitationRepository extends BaseRepository implements InvitationRepositoryInterface
{
    public function __construct(Invitation $model)
    {
        parent::__construct($model);
    }

    public function pending(): Collection
    {
        return $this->query()
            ->whereNull('accepted_at')
            ->with('inviter:id,name')
            ->latest('id')
            ->get();
    }

    public function pendingForEmail(string $email): ?Invitation
    {
        return $this->query()
            ->whereNull('accepted_at')
            ->where('email', mb_strtolower($email))
            ->first();
    }

    public function resolveByToken(string $token): ?Invitation
    {
        // BR-I07 tenant resolver: the family is derived from this row.
        return $this->model->newQuery()
            ->withoutGlobalScope(FamilyScope::class)
            ->with('family')
            ->where('token', $token)
            ->first();
    }
}
