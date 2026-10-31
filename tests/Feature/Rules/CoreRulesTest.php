<?php

use App\Enums\TransactionType;
use App\Enums\WalletType;
use App\Models\Category;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Schema;
use Inertia\Support\SessionKey;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->owner = User::factory()->create();
    $this->family = createFamily($this->owner);
    $this->tunai = Wallet::query()->where('name', 'Tunai')->firstOrFail();
    $this->category = Category::query()->where('name', 'Transportasi')->firstOrFail();
});

it('stores money as whole-rupiah integers [BR-A01]', function () {
    expect(Schema::getColumnType('transactions', 'amount'))->toBe('bigint')
        ->and(Schema::getColumnType('wallets', 'initial_balance'))->toBe('bigint');

    $wallet = Wallet::factory()->for($this->family)->balance(1_250_000)->create();

    expect($wallet->fresh()->initial_balance)->toBeInt()->toBe(1_250_000);
});

it('knows exactly three transaction types [BR-B01]', function () {
    expect(array_map(fn (TransactionType $type) => $type->value, TransactionType::cases()))
        ->toBe(['income', 'expense', 'transfer']);

    $this->actingAs($this->owner)
        ->post(route('transactions.store'), [
            'type' => 'refund', 'amount' => 10_000, 'date' => '2026-10-31',
            'wallet_id' => $this->tunai->id, 'category_id' => $this->category->id,
        ])
        ->assertSessionHasErrors('type');
});

it('saves but warns when a wallet goes below zero [BR-C03]', function () {
    $this->actingAs($this->owner)
        ->post(route('transactions.store'), [
            'type' => 'expense', 'amount' => '75rb', 'date' => '2026-10-31',
            'wallet_id' => $this->tunai->id, 'category_id' => $this->category->id,
        ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas(SessionKey::FLASH_DATA.'.toast', [
            'type' => 'warning',
            'message' => 'Transaksi tersimpan. Saldo Tunai menjadi minus (-Rp 75.000).',
        ]);

    Sanctum::actingAs($this->owner, ['read', 'write']);
    $this->postJson('/api/v1/transactions', [
        'type' => 'expense', 'amount' => 5_000, 'date' => '2026-10-31',
        'wallet_id' => $this->tunai->id, 'category_id' => $this->category->id,
    ])->assertCreated()->assertJsonPath('meta.warnings', ['Saldo Tunai menjadi minus (-Rp 80.000).']);
});

it('does not warn for credit cards or positive balances [BR-C03]', function () {
    $card = Wallet::factory()->for($this->family)->ofType(WalletType::CreditCard)->create(['name' => 'Kartu']);

    $this->actingAs($this->owner)
        ->post(route('transactions.store'), [
            'type' => 'expense', 'amount' => '75rb', 'date' => '2026-10-31',
            'wallet_id' => $card->id, 'category_id' => $this->category->id,
        ])
        ->assertSessionHas(SessionKey::FLASH_DATA.'.toast.type', 'success');
});

it('blocks suspended families on web and API, except super admins [BR-I05]', function () {
    $this->family->forceFill(['suspended_at' => now()])->save();

    $this->actingAs($this->owner)->get(route('transactions.index'))->assertForbidden();

    Sanctum::actingAs($this->owner, ['read']);
    $this->getJson('/api/v1/transactions')->assertForbidden();

    $this->owner->forceFill(['is_super_admin' => true])->save();
    $this->actingAs($this->owner->fresh())->get(route('transactions.index'))->assertOk();
});
