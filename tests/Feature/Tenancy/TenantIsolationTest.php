<?php

use App\Exceptions\Tenancy\TenantNotResolvedException;
use App\Models\Category;
use App\Models\Wallet;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Services\Tenancy\TenantContext;

it('only returns wallets of the active family [BR-I06]', function () {
    $a = createFamily(name: 'Keluarga A');
    $walletA = Wallet::factory()->for($a)->create(['name' => 'Dompet A']);

    $b = createFamily(name: 'Keluarga B');
    Wallet::factory()->for($b)->create(['name' => 'Dompet B']);

    useFamily($a);
    $names = app(WalletRepositoryInterface::class)->listForFamily()->pluck('name');

    expect($names)->toContain('Dompet A')->not->toContain('Dompet B')
        ->and(Wallet::query()->find($walletA->id))->not->toBeNull();

    useFamily($b);
    expect(Wallet::query()->find($walletA->id))->toBeNull();
});

it('only returns categories of the active family [BR-I06]', function () {
    $a = createFamily(name: 'Keluarga A');
    $b = createFamily(name: 'Keluarga B');

    useFamily($a);
    $idsA = app(CategoryRepositoryInterface::class)->tree()->pluck('id');

    useFamily($b);
    $idsB = app(CategoryRepositoryInterface::class)->tree()->pluck('id');

    expect($idsA)->not->toBeEmpty()
        ->and($idsA->intersect($idsB))->toBeEmpty()
        ->and(Category::query()->whereIn('id', $idsA)->count())->toBe(0);
});

it('stamps new tenant records with the active family', function () {
    $family = createFamily();

    $wallet = Wallet::query()->create(['name' => 'BCA', 'type' => 'bank']);

    expect($wallet->family_id)->toBe($family->id);
});

it('fails closed when no family is active', function () {
    createFamily();
    app(TenantContext::class)->forget();

    Wallet::query()->count();
})->throws(TenantNotResolvedException::class);

it('refuses to create tenant records without an active family', function () {
    app(TenantContext::class)->forget();

    Wallet::query()->create(['name' => 'Tunai', 'type' => 'cash']);
})->throws(TenantNotResolvedException::class);
