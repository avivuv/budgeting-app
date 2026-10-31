<?php

use App\Enums\FamilyRole;
use App\Models\User;
use App\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\GoldenFamily;

beforeEach(fn () => $this->travelTo('2026-10-31 12:00:00'));

it('lists wallets with server-calculated balances [BR-C01] [INV-02]', function () {
    $golden = GoldenFamily::seed();

    $this->actingAs($golden->ayah)
        ->get(route('wallets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('wallets/Index')
            ->where('totalBalance', GoldenFamily::EXPECTED['total_balance'])
            ->where('totalBalanceFormatted', 'Rp 16.412.500')
            ->where('wallets', fn ($wallets) => collect($wallets)->pluck('balance', 'name')->sortKeys()->all()
                === collect(GoldenFamily::EXPECTED['balances'])->sortKeys()->all()));
});

it('creates a wallet from a free-form amount [BR-A03]', function () {
    $owner = User::factory()->create();
    createFamily($owner);

    $this->actingAs($owner)
        ->post(route('wallets.store'), ['name' => 'BCA', 'type' => 'bank', 'initial_balance' => '1,5jt'])
        ->assertRedirect(route('wallets.index'));

    expect(Wallet::query()->where('name', 'BCA')->sole()->initial_balance)->toBe(1_500_000);
});

it('lets only owners and admins manage wallets', function (FamilyRole $role, bool $allowed) {
    $family = createFamily();
    $user = addMember($family, $role);

    $response = $this->actingAs($user)->post(route('wallets.store'), ['name' => 'OVO', 'type' => 'ewallet']);

    $allowed ? $response->assertRedirect(route('wallets.index')) : $response->assertForbidden();
})->with([
    [FamilyRole::Admin, true],
    [FamilyRole::Member, false],
    [FamilyRole::Viewer, false],
]);

it('returns 404 for wallets of another family [BR-I06]', function () {
    $owner = User::factory()->create();
    createFamily($owner);
    createFamily(name: 'Tetangga');
    $foreign = Wallet::query()->firstOrFail();

    $this->actingAs($owner)
        ->put(route('wallets.update', $foreign->id), ['name' => 'Diambil', 'type' => 'cash'])
        ->assertNotFound();

    $this->actingAs($owner)->delete(route('wallets.destroy', $foreign->id))->assertNotFound();
});

it('shows the business rule when deleting a used wallet [BR-C05]', function () {
    $golden = GoldenFamily::seed();

    $this->actingAs($golden->ayah)
        ->from(route('wallets.index'))
        ->delete(route('wallets.destroy', $golden->wallets['BCA']->id))
        ->assertRedirect(route('wallets.index'))
        ->assertSessionHasErrors('general');

    useFamily($golden->family);
    expect(Wallet::query()->find($golden->wallets['BCA']->id))->not->toBeNull();
});
