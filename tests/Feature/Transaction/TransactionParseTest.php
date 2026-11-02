<?php

use App\Enums\FamilyRole;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionParser;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\GoldenFamily;

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->golden = GoldenFamily::seed();
});

it('reads quick entry text against the family wallets and categories [BR-A03] [BR-E05]', function () {
    $this->actingAs($this->golden->ibu)
        ->postJson(route('transactions.parse'), ['text' => "bensin 50rb @gopay kemarin\n+ gaji 8jt bca"])
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.type', 'expense')
        ->assertJsonPath('data.0.amount', 50_000)
        ->assertJsonPath('data.0.amountFormatted', 'Rp 50.000')
        ->assertJsonPath('data.0.date', '2026-10-30')
        ->assertJsonPath('data.0.walletId', $this->golden->wallets['GoPay']->id)
        ->assertJsonPath('data.0.categoryId', $this->golden->categories['Transportasi']->id)
        ->assertJsonPath('data.0.categorySource', 'keyword')
        ->assertJsonPath('data.0.complete', true)
        ->assertJsonPath('data.1.type', 'income')
        ->assertJsonPath('data.1.walletName', 'BCA')
        ->assertJsonPath('data.1.categoryName', 'Gaji');

    expect(Transaction::query()->count())->toBe(7);
});

it('uses the first wallet in display order when none is named', function () {
    $this->actingAs($this->golden->ibu)
        ->postJson(route('transactions.parse'), ['text' => 'parkir 5rb'])
        ->assertJsonPath('data.0.walletName', 'BCA');
});

it('guesses the category from the family history when no keyword matches [BR-E05]', function () {
    Transaction::factory()->on('2026-10-28')
        ->expense(25_000, $this->golden->wallets['Tunai'], $this->golden->categories['Makanan'])
        ->create(['note' => 'Nasi Padang', 'created_by' => $this->golden->ibu->id]);

    $this->actingAs($this->golden->ibu)
        ->postJson(route('transactions.parse'), ['text' => 'nasi padang 30rb'])
        ->assertJsonPath('data.0.categoryId', $this->golden->categories['Makanan']->id)
        ->assertJsonPath('data.0.categorySource', 'history')
        ->assertJsonPath('data.0.missing', []);
});

it('lists what is still missing', function () {
    $this->actingAs($this->golden->ibu)
        ->postJson(route('transactions.parse'), ['text' => 'sesuatu yang aneh'])
        ->assertJsonPath('data.0.missing', ['amount', 'category_id'])
        ->assertJsonPath('data.0.complete', false);
});

it('never matches wallets or categories of another family [BR-I06]', function () {
    $neighbour = User::factory()->create();
    createFamily($neighbour, 'Tetangga');

    expect(app(TransactionParser::class)->parse('bensin 50rb @bca')[0]->walletName)->toBe('Tunai');

    useFamily($this->golden->family);

    expect(app(TransactionParser::class)->parse('bensin 50rb @bca')[0]->walletName)->toBe('BCA');
});

it('validates the text', function () {
    $this->actingAs($this->golden->ibu)
        ->postJson(route('transactions.parse'), ['text' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('text');
});

it('does not let viewers use quick entry [BR-B09]', function () {
    $viewer = addMember($this->golden->family, FamilyRole::Viewer);

    $this->actingAs($viewer)
        ->postJson(route('transactions.parse'), ['text' => 'bensin 50rb'])
        ->assertForbidden();
});

it('parses the same way through the REST API [BR-B12]', function () {
    Sanctum::actingAs($this->golden->ibu, ['read', 'write']);

    $this->postJson('/api/v1/transactions/parse', ['text' => 'tf 500rb bca ke gopay'])
        ->assertOk()
        ->assertJsonPath('data.0.type', 'transfer')
        ->assertJsonPath('data.0.walletName', 'BCA')
        ->assertJsonPath('data.0.toWalletName', 'GoPay')
        ->assertJsonPath('data.0.missing', []);
});
