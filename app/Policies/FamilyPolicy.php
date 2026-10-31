<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\FamilyRole;
use App\Models\User;
use App\Services\Family\FamilyService;
use App\Services\Tenancy\TenantContext;

/**
 * Base for policies of family-owned models: the user's role in the active family
 * decides (docs/03-multi-tenancy.md §4). Records of other families never reach a
 * policy — the tenant scope turns them into 404s (BR-I06).
 */
abstract class FamilyPolicy
{
    public function __construct(
        private readonly FamilyService $families,
        private readonly TenantContext $tenant,
    ) {}

    protected function role(User $user): ?FamilyRole
    {
        if (! $this->tenant->has()) {
            return null;
        }

        return $this->families->roleOf($this->tenant->get(), $user);
    }

    protected function isMember(User $user): bool
    {
        return $this->role($user) !== null;
    }

    protected function canWrite(User $user): bool
    {
        return $this->role($user)?->canWrite() ?? false;
    }

    protected function canManage(User $user): bool
    {
        return $this->role($user)?->canManage() ?? false;
    }
}
