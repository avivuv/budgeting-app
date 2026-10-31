<?php

use App\DTOs\Family\FamilyData;
use App\Enums\FamilyRole;
use App\Models\Family;
use App\Models\User;
use App\Repositories\Contracts\FamilyRepositoryInterface;
use App\Services\Family\FamilyService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests hit the MySQL test databases (budgeting_app_test and
| budgeting_app_log_test). Unit tests stay database-free.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Architecture');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a family through FamilyService (default wallet + categories) owned by $owner,
 * and make it the active tenant.
 */
function createFamily(?User $owner = null, string $name = 'Keluarga Uji', int $periodStartDay = 1): Family
{
    $owner ??= User::factory()->create();

    $family = app(FamilyService::class)->create(
        new FamilyData(name: $name, periodStartDay: $periodStartDay),
        $owner,
    );

    app(TenantContext::class)->set($family);

    return $family;
}

/**
 * Add a user to a family with the given role.
 */
function addMember(Family $family, FamilyRole $role, ?User $user = null): User
{
    $user ??= User::factory()->create();
    app(FamilyRepositoryInterface::class)->addMember($family, $user, $role);

    return $user;
}

function useFamily(Family $family): void
{
    app(TenantContext::class)->set($family);
}
