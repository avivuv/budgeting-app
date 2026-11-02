<?php

use App\DTOs\Transaction\TransactionData;
use App\DTOs\Transaction\TransactionFilterData;
use App\DTOs\Wallet\WalletData;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Enums\WalletType;
use App\Models\Transaction;
use App\Services\Report\DashboardService;
use App\Services\Transaction\TransactionService;
use App\Services\Wallet\WalletService;
use Carbon\CarbonImmutable;
use Tests\Fixtures\GoldenFamily;

beforeEach(fn () => $this->travelTo('2026-10-31 12:00:00'));

function dashboard(): DashboardService
{
    return app(DashboardService::class);
}

function noScope(): TransactionFilterData
{
    return new TransactionFilterData;
}

it('summarises the golden period [BR-B08] [BR-B11] [INV-02] [INV-03]', function () {
    GoldenFamily::seed();
    $summary = dashboard()->summary(dashboard()->currentPeriod(), noScope());

    expect($summary->income->amount)->toBe(GoldenFamily::EXPECTED['income'])
        ->and($summary->expense->amount)->toBe(GoldenFamily::EXPECTED['expense'])
        ->and($summary->net->amount)->toBe(GoldenFamily::EXPECTED['net'])
        ->and($summary->balance->amount)->toBe(GoldenFamily::EXPECTED['total_balance'])
        ->and($summary->balance->formatted)->toBe('Rp 16.412.500');
});

it('compares with the previous period and shows "Baru" when it was empty [BR-A05] [INV-07]', function () {
    GoldenFamily::seed();
    $summary = dashboard()->summary(dashboard()->currentPeriod(), noScope());

    // September had no transactions: its closing balance is the opening balance.
    expect($summary->expense->previous)->toBe(0)
        ->and($summary->expense->change)->toBeNull()
        ->and($summary->balance->previous)->toBe(10_700_000)
        ->and($summary->balance->change)->toBe(53.4);
});

it('rolls sub-categories into their parent and adds up to the expense [BR-E04] [INV-04]', function () {
    $golden = GoldenFamily::seed();
    $slices = collect(dashboard()->categories(dashboard()->currentPeriod(), noScope()));

    $food = $slices->firstWhere('categoryId', $golden->categories['Makanan']->id);

    expect($food->amount)->toBe(GoldenFamily::EXPECTED['food_including_restaurant'])
        ->and($food->percentage)->toBe(28.4)
        ->and($slices->firstWhere('categoryId', $golden->categories['Restoran']->id))->toBeNull()
        ->and($slices->sum('amount'))->toBe(GoldenFamily::EXPECTED['expense'])
        ->and($slices->first()->name)->toBe('Tagihan & Utilitas');
});

it('splits the expense per member [INV-05]', function () {
    GoldenFamily::seed();
    $members = collect(dashboard()->members(dashboard()->currentPeriod(), noScope()))->keyBy('name');

    expect($members['Ibu']->amount)->toBe(GoldenFamily::EXPECTED['by_member']['Ibu'])
        ->and($members['Ayah']->amount)->toBe(GoldenFamily::EXPECTED['by_member']['Ayah'])
        ->and($members->sum('amount'))->toBe(GoldenFamily::EXPECTED['expense']);
});

it('shows six periods of cash flow ending with the selected one', function () {
    GoldenFamily::seed();
    $points = dashboard()->cashflow(dashboard()->currentPeriod(), noScope());

    expect($points)->toHaveCount(DashboardService::CASHFLOW_PERIODS)
        ->and($points[0]->key)->toBe('2026-05')
        ->and(end($points)->key)->toBe('2026-10')
        ->and(end($points)->income)->toBe(GoldenFamily::EXPECTED['income'])
        ->and(end($points)->expense)->toBe(GoldenFamily::EXPECTED['expense'])
        ->and(end($points)->net)->toBe(GoldenFamily::EXPECTED['net']);
});

it('accumulates the daily expense trend up to today', function () {
    GoldenFamily::seed();
    $points = dashboard()->trend(dashboard()->currentPeriod(), noScope());

    expect($points)->toHaveCount(31)
        ->and($points[2]->cumulative)->toBe(250_000)
        ->and($points[30]->cumulative)->toBe(GoldenFamily::EXPECTED['expense'])
        ->and($points[30]->pace)->toBe(0);
});

it('leaves days after today empty in the trend [BR-D03]', function () {
    GoldenFamily::seed();
    $this->travelTo('2026-10-15 23:30:00');
    $points = dashboard()->trend(dashboard()->currentPeriod(), noScope());

    expect($points[14]->cumulative)->not->toBeNull()
        ->and($points[15]->cumulative)->toBeNull();
});

it('limits every number to the chosen wallet', function () {
    $golden = GoldenFamily::seed();
    $scope = new TransactionFilterData(walletIds: [$golden->wallets['BCA']->id]);
    $period = dashboard()->currentPeriod();

    expect(dashboard()->summary($period, $scope)->balance->amount)->toBe(15_100_000)
        ->and(dashboard()->summary($period, $scope)->expense->amount)->toBe(1_900_000)
        ->and(dashboard()->walletBalances($period, $scope))->toHaveCount(1);
});

it('limits totals to the chosen member', function () {
    $golden = GoldenFamily::seed();
    $scope = new TransactionFilterData(userIds: [$golden->ibu->id]);

    expect(dashboard()->summary(dashboard()->currentPeriod(), $scope)->expense->amount)->toBe(737_500);
});

it('shows balances at the end of a past period [BR-C01]', function () {
    GoldenFamily::seed();
    $this->travelTo('2026-11-02 08:00:00');
    $period = dashboard()->period('2026-10');

    expect(dashboard()->summary($period, noScope())->balance->amount)->toBe(GoldenFamily::EXPECTED['total_balance'])
        ->and(dashboard()->periodData($period)->nextKey)->toBe('2026-11')
        ->and(dashboard()->periodData(dashboard()->currentPeriod())->nextKey)->toBeNull();
});

it('falls back to the current period for invalid or future keys', function () {
    GoldenFamily::seed();

    expect(dashboard()->period('2027-01')->key())->toBe('2026-10')
        ->and(dashboard()->period('oops')->key())->toBe('2026-10')
        ->and(dashboard()->period(null)->key())->toBe('2026-10');
});

it('follows the family period start day [BR-D01] [BR-D02]', function () {
    $family = createFamily(periodStartDay: 25);
    $this->travelTo('2026-10-26 09:00:00');

    $data = dashboard()->periodData(dashboard()->currentPeriod());

    expect($data->key)->toBe('2026-11')
        ->and($data->start)->toBe('2026-10-25')
        ->and($data->end)->toBe('2026-11-24')
        ->and($family->period_start_day)->toBe(25);
});

it('lists the newest transactions first, without deleted ones [BR-B08]', function () {
    GoldenFamily::seed();
    $recent = dashboard()->recent(dashboard()->currentPeriod(), noScope());

    expect($recent)->toHaveCount(7)
        ->and($recent->first()->date->toDateString())->toBe('2026-10-20');
});

it('describes the period in plain words', function () {
    $golden = GoldenFamily::seed();
    Transaction::factory()->on('2026-09-10')->expense(200_000, $golden->wallets['Tunai'], $golden->categories['Makanan'])->create();
    dashboard()->invalidate($golden->family->id);

    $insights = dashboard()->insights(dashboard()->currentPeriod(), noScope());

    expect($insights)->toContain('Pengeluaran naik 1.043,8% dibanding periode lalu.')
        ->and($insights)->toContain('Pengeluaran Makanan & Minuman naik 225,0% dari periode lalu.')
        ->and($insights)->toContain('Rata-rata pengeluaran harian: Rp 73.790.');
});

it('refreshes cached numbers right after a transaction changes [BR-C04] [INV-08]', function () {
    $golden = GoldenFamily::seed();
    $period = dashboard()->currentPeriod();
    $service = app(TransactionService::class);

    expect(dashboard()->summary($period, noScope())->expense->amount)->toBe(2_287_500);

    $transaction = $service->create(new TransactionData(
        type: TransactionType::Expense,
        amount: 12_500,
        date: CarbonImmutable::parse('2026-10-31'),
        walletId: $golden->wallets['Tunai']->id,
        categoryId: $golden->categories['Makanan']->id,
        source: TransactionSource::Web,
    ), $golden->ibu);

    expect(dashboard()->summary($period, noScope())->expense->amount)->toBe(2_300_000)
        ->and(dashboard()->summary($period, noScope())->balance->amount)->toBe(16_400_000);

    $service->delete($transaction);

    expect(dashboard()->summary($period, noScope())->expense->amount)->toBe(2_287_500);
});

it('reads widgets back from a serializing cache store', function () {
    // The array store used in tests keeps objects as-is; real stores serialize
    // and refuse to unserialize classes (cache.serializable_classes = false).
    config(['cache.default' => 'database']);
    GoldenFamily::seed();
    $period = dashboard()->currentPeriod();

    foreach ([1, 2] as $_) {
        expect(dashboard()->summary($period, noScope())->expense->amount)->toBe(GoldenFamily::EXPECTED['expense'])
            ->and(dashboard()->categories($period, noScope())[0]->name)->toBe('Tagihan & Utilitas')
            ->and(dashboard()->walletBalances($period, noScope()))->toHaveCount(3)
            ->and(dashboard()->insights($period, noScope()))->toBeArray();
    }
});

it('refreshes cached balances after a wallet changes [BR-C04]', function () {
    $golden = GoldenFamily::seed();
    $period = dashboard()->currentPeriod();

    expect(dashboard()->summary($period, noScope())->balance->amount)->toBe(16_412_500);

    app(WalletService::class)->update($golden->wallets['GoPay'], new WalletData(
        name: 'GoPay',
        type: WalletType::Ewallet,
        initialBalance: 300_000,
    ));

    expect(dashboard()->summary($period, noScope())->balance->amount)->toBe(16_512_500);
});

it('never mixes in another family [BR-I06]', function () {
    $golden = GoldenFamily::seed();
    createFamily(name: 'Keluarga Lain');

    expect(dashboard()->summary(dashboard()->currentPeriod(), noScope())->expense->amount)->toBe(0);

    useFamily($golden->family);

    expect(dashboard()->summary(dashboard()->currentPeriod(), noScope())->expense->amount)->toBe(GoldenFamily::EXPECTED['expense']);
});
