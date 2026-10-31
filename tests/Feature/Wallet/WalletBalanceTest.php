<?php

use App\DTOs\Transaction\TransactionData;
use App\Enums\TransactionType;
use App\Enums\WalletType;
use App\Exceptions\Wallet\WalletRuleException;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Transaction\TransactionService;
use App\Services\Wallet\WalletService;
use Tests\Fixtures\GoldenFamily;

beforeEach(fn () => $this->travelTo('2026-10-31 12:00:00'));

it('matches the hand-calculated golden balances [BR-C01] [BR-B08] [BR-B11]', function () {
    $golden = GoldenFamily::seed();
    $balances = app(WalletService::class)->balances($golden->wallets);

    foreach (GoldenFamily::EXPECTED['balances'] as $name => $expected) {
        expect($balances[$golden->wallets[$name]->id])->toBe($expected, "saldo {$name}");
    }
});

it('totals all active wallets [BR-C02] [INV-10]', function () {
    GoldenFamily::seed();

    // Opening 10.700.000 + net 5.712.500: transfers do not change the family total.
    expect(app(WalletService::class)->totalBalance())->toBe(GoldenFamily::EXPECTED['total_balance']);
});

it('computes the balance up to a given date [BR-C01]', function () {
    $golden = GoldenFamily::seed();
    $tunai = $golden->wallets['Tunai'];

    // Until 2 Oct: opening 500.000 + transfer in 1.000.000.
    expect(app(WalletService::class)->balances([$tunai], '2026-10-02')[$tunai->id])->toBe(1_500_000);
});

it('treats credit card spending as negative balance and bill payment as transfer [BR-C06] [INV-12]', function () {
    $family = createFamily();
    $owner = $family->members()->first();
    $bank = Wallet::factory()->for($family)->ofType(WalletType::Bank)->balance(5_000_000)->create();
    $card = Wallet::factory()->for($family)->ofType(WalletType::CreditCard)->create();
    $food = Category::query()->where('name', 'Makanan & Minuman')->firstOrFail();
    $service = app(TransactionService::class);

    $service->create(new TransactionData(TransactionType::Expense, 300_000, now()->toImmutable(), $card->id, categoryId: $food->id), $owner);
    expect(app(WalletService::class)->balances([$card])[$card->id])->toBe(-300_000);

    $service->create(new TransactionData(TransactionType::Transfer, 300_000, now()->toImmutable(), $bank->id, toWalletId: $card->id), $owner);
    $balances = app(WalletService::class)->balances([$bank, $card]);

    expect($balances[$card->id])->toBe(0)
        ->and($balances[$bank->id])->toBe(4_700_000)
        ->and(Transaction::query()->where('type', TransactionType::Expense)->sum('amount'))->toEqual(300_000);
});

it('excludes archived wallets from the family total [BR-C02]', function () {
    $family = createFamily();
    Wallet::factory()->for($family)->balance(1_000_000)->create();
    Wallet::factory()->for($family)->balance(999_000)->archived()->create();

    expect(app(WalletService::class)->totalBalance())->toBe(1_000_000);
});

it('refuses to delete a wallet that has transactions [BR-C05]', function () {
    $golden = GoldenFamily::seed();

    app(WalletService::class)->delete($golden->wallets['GoPay']);
})->throws(WalletRuleException::class);

it('deletes an unused wallet', function () {
    $family = createFamily();
    $wallet = Wallet::factory()->for($family)->create();

    app(WalletService::class)->delete($wallet);

    expect(Wallet::query()->find($wallet->id))->toBeNull();
});
