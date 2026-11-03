<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth\Concerns;

use App\Http\Resources\MeResource;
use App\Models\User;
use App\Services\Family\FamilyService;
use App\Services\Tenancy\TenantContext;

/**
 * Auth endpoints answer with the same `Me` object as GET /me, so the client
 * knows right away whether to show the app or onboarding.
 */
trait RespondsWithMe
{
    /**
     * @param  bool  $stored  use the stored active family, not the request's (after switch/leave/accept)
     */
    protected function me(User $user, bool $stored = false): MeResource
    {
        $families = app(FamilyService::class);
        $tenant = app(TenantContext::class);

        // X-Family-Id (already checked by the family middleware) wins over the stored active family.
        $currentId = $tenant->has() && ! $stored ? $tenant->id() : $families->resolveActive($user->refresh())?->id;

        return new MeResource($user, $families->familiesOf($user), $currentId);
    }
}
