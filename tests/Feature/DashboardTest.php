<?php

use App\Enums\FamilyRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\GoldenFamily;

it('redirects guests to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('sends users without a family to onboarding', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.create'));
});

it('shows the dashboard to family members', function () {
    $user = User::factory()->create();
    createFamily($user);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('renders the summary first and charts as deferred props [INV-03]', function () {
    $this->travelTo('2026-10-31 12:00:00');
    $golden = GoldenFamily::seed();

    $this->actingAs($golden->ayah)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Index')
            ->where('period.key', '2026-10')
            ->where('summary.income.amount', GoldenFamily::EXPECTED['income'])
            ->where('summary.expense.amount', GoldenFamily::EXPECTED['expense'])
            ->where('summary.balance.amount', GoldenFamily::EXPECTED['total_balance'])
            ->has('memberOptions', 2)
            ->missing('cashflow')
            ->missing('recent')
            ->loadDeferredProps('charts', fn (Assert $reload) => $reload
                ->has('cashflow', 6)
                ->where('categories.1.amount', GoldenFamily::EXPECTED['food_including_restaurant'])
                ->has('trend', 31)
                ->has('members', 2))
            ->loadDeferredProps('lists', fn (Assert $reload) => $reload
                ->has('wallets', 3)
                ->has('recent', 7)));
});

it('applies the wallet and member filters from the query string', function () {
    $this->travelTo('2026-10-31 12:00:00');
    $golden = GoldenFamily::seed();

    $this->actingAs($golden->ayah)
        ->get(route('dashboard', ['wallet' => $golden->wallets['BCA']->id, 'member' => $golden->ibu->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.wallet', $golden->wallets['BCA']->id)
            ->where('filters.member', $golden->ibu->id)
            ->where('summary.expense.amount', 400_000)
            ->where('summary.balance.amount', 15_100_000));
});

it('opens a past period', function () {
    $this->travelTo('2026-11-02 08:00:00');
    $golden = GoldenFamily::seed();

    $this->actingAs($golden->ayah)
        ->get(route('dashboard', ['period' => '2026-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('period.key', '2026-10')
            ->where('period.nextKey', '2026-11')
            ->where('summary.expense.amount', GoldenFamily::EXPECTED['expense']));
});

it('rejects a malformed period', function () {
    $user = User::factory()->create();
    createFamily($user);

    $this->actingAs($user)
        ->get(route('dashboard', ['period' => '2026-13']))
        ->assertSessionHasErrors('period');
});

it('lets viewers read the dashboard but not create transactions [BR-B09]', function () {
    $family = createFamily();
    $viewer = addMember($family, FamilyRole::Viewer);

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('canCreate', false));
});
