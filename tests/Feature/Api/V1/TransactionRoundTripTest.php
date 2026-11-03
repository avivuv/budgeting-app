<?php

use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\GoldenFamily;

/*
| API integration ("round-trip") tests: write through the API, read back
| through the API, and check that the stored values and every derived
| number (wallet balances, filtered totals) match the input exactly.
| Starts from the hand-calculated golden dataset (docs/12 §3).
*/

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->golden = GoldenFamily::seed();
    Sanctum::actingAs($this->golden->ayah, ['read', 'write']);
});

/**
 * @return array<string, int>
 */
function apiBalances(): array
{
    return collect(test()->getJson('/api/v1/wallets')->assertOk()->json('data'))
        ->pluck('balance', 'name')
        ->all();
}

/**
 * @return array{income: int, expense: int}
 */
function apiTotals(string $query = ''): array
{
    return test()->getJson('/api/v1/transactions?'.$query)->assertOk()->json('meta.totals');
}

it('reads back exactly what was created, and balances and totals follow [BR-A01] [BR-C01] [INV-02] [INV-03]', function () {
    $golden = $this->golden;

    expect(apiBalances())->toEqual(GoldenFamily::EXPECTED['balances'])
        ->and(apiTotals())->toBe(['income' => 8_000_000, 'expense' => 2_287_500]);

    $id = $this->postJson('/api/v1/transactions', [
        'type' => 'expense',
        'amount' => '12.500',
        'date' => '2026-10-30',
        'wallet_id' => $golden->wallets['GoPay']->id,
        'category_id' => $golden->categories['Makanan']->id,
        'note' => 'kopi susu',
        'tags' => ['#Kantor'],
    ])->assertCreated()->json('data.id');

    $this->getJson("/api/v1/transactions/{$id}")
        ->assertOk()
        ->assertJsonPath('data.type', 'expense')
        ->assertJsonPath('data.amount', 12_500)
        ->assertJsonPath('data.amount_formatted', 'Rp 12.500')
        ->assertJsonPath('data.date', '2026-10-30')
        ->assertJsonPath('data.wallet.name', 'GoPay')
        ->assertJsonPath('data.category.name', 'Makanan & Minuman')
        ->assertJsonPath('data.note', 'kopi susu')
        ->assertJsonPath('data.tags', ['kantor'])
        ->assertJsonPath('data.source', 'api')
        ->assertJsonPath('data.created_by.name', 'Ayah');

    expect(apiBalances())->toEqual([...GoldenFamily::EXPECTED['balances'], 'GoPay' => 112_500 - 12_500])
        ->and(apiTotals())->toBe(['income' => 8_000_000, 'expense' => 2_300_000])
        ->and(apiTotals('category_id[]='.$golden->categories['Makanan']->id)['expense'])->toBe(662_500);
});

it('moves the amount between wallets when an edit changes wallet and amount [BR-C04] [INV-08]', function () {
    $golden = $this->golden;
    $id = $this->postJson('/api/v1/transactions', [
        'type' => 'expense', 'amount' => 50_000, 'date' => '2026-10-31',
        'wallet_id' => $golden->wallets['Tunai']->id, 'category_id' => $golden->categories['Transportasi']->id,
    ])->assertCreated()->json('data.id');

    $this->putJson("/api/v1/transactions/{$id}", [
        'type' => 'expense', 'amount' => '75rb', 'date' => '2026-10-31',
        'wallet_id' => $golden->wallets['BCA']->id, 'category_id' => $golden->categories['Transportasi']->id,
    ])->assertOk()->assertJsonPath('data.amount', 75_000)->assertJsonPath('data.wallet.name', 'BCA');

    $this->getJson("/api/v1/transactions/{$id}")->assertJsonPath('data.amount', 75_000);

    expect(apiBalances())->toEqual([
        'BCA' => 15_100_000 - 75_000,
        'GoPay' => 112_500,
        'Tunai' => 1_200_000,
    ])->and(apiTotals()['expense'])->toBe(2_287_500 + 75_000);
});

it('drops a deleted transaction from reads, balances and totals [BR-B08] [INV-08]', function () {
    $golden = $this->golden;
    $id = $this->postJson('/api/v1/transactions', [
        'type' => 'expense', 'amount' => 99_000, 'date' => '2026-10-31',
        'wallet_id' => $golden->wallets['Tunai']->id, 'category_id' => $golden->categories['Makanan']->id,
    ])->assertCreated()->json('data.id');

    $this->deleteJson("/api/v1/transactions/{$id}")->assertNoContent();

    $this->getJson("/api/v1/transactions/{$id}")->assertNotFound();
    expect(apiBalances())->toEqual(GoldenFamily::EXPECTED['balances'])
        ->and(apiTotals())->toBe(['income' => 8_000_000, 'expense' => 2_287_500]);
});

it('keeps income and expense unchanged for a transfer and the family total too [BR-B11] [INV-10]', function () {
    $golden = $this->golden;

    $this->postJson('/api/v1/transactions', [
        'type' => 'transfer', 'amount' => '250rb', 'date' => '2026-10-31',
        'wallet_id' => $golden->wallets['BCA']->id, 'to_wallet_id' => $golden->wallets['GoPay']->id,
    ])->assertCreated()->assertJsonPath('data.to_wallet.name', 'GoPay')->assertJsonPath('data.category', null);

    $balances = apiBalances();

    expect($balances['BCA'])->toBe(15_100_000 - 250_000)
        ->and($balances['GoPay'])->toBe(112_500 + 250_000)
        ->and(array_sum($balances))->toBe(GoldenFamily::EXPECTED['total_balance'])
        ->and(apiTotals())->toBe(['income' => 8_000_000, 'expense' => 2_287_500]);
});

it('reads back what the free-text parser previewed, unchanged after saving [BR-B12]', function () {
    $preview = $this->postJson('/api/v1/transactions/parse', ['text' => 'parkir 5rb @gopay kemarin'])
        ->assertOk()
        ->json('data.0');

    $id = $this->postJson('/api/v1/transactions', [
        'type' => $preview['type'],
        'amount' => $preview['amount'],
        'date' => $preview['date'],
        'wallet_id' => $preview['wallet_id'],
        'category_id' => $preview['category_id'],
        'note' => $preview['note'],
    ])->assertCreated()->json('data.id');

    $this->getJson("/api/v1/transactions/{$id}")
        ->assertJsonPath('data.amount', 5_000)
        ->assertJsonPath('data.date', '2026-10-30')
        ->assertJsonPath('data.wallet.name', 'GoPay')
        ->assertJsonPath('data.category.name', 'Transportasi')
        ->assertJsonPath('data.note', 'parkir');
});
