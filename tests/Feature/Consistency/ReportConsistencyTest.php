<?php

use App\DTOs\Report\CategoryRowData;
use App\DTOs\Transaction\TransactionFilterData;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\Report\DashboardService;
use App\Services\Report\ReportService;
use App\Services\Wallet\WalletService;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\GoldenFamily;

/*
| Every report must agree with the hand-calculated golden dataset and with
| the dashboard, the wallet page and the REST API (docs/12 §2.4).
*/

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->golden = GoldenFamily::seed();
    $this->from = CarbonImmutable::parse('2026-10-01');
    $this->to = CarbonImmutable::parse('2026-10-31');
});

function reports(): ReportService
{
    return app(ReportService::class);
}

it('matches income and expense across dashboard, report and API [INV-03] [BR-B08] [BR-B11]', function () {
    $report = reports()->totals($this->from, $this->to);
    $dashboard = app(DashboardService::class)->summary(app(DashboardService::class)->currentPeriod(), new TransactionFilterData);

    Sanctum::actingAs($this->golden->ayah, ['read']);
    $api = $this->getJson('/api/v1/transactions?from=2026-10-01&to=2026-10-31')->json('meta.totals');

    expect($report['income']->amount)->toBe(GoldenFamily::EXPECTED['income'])
        ->and($report['expense']->amount)->toBe(GoldenFamily::EXPECTED['expense'])
        ->and($report['net']->amount)->toBe(GoldenFamily::EXPECTED['net'])
        ->and($dashboard->income->amount)->toBe($report['income']->amount)
        ->and($dashboard->expense->amount)->toBe($report['expense']->amount)
        ->and($api)->toBe(['income' => $report['income']->amount, 'expense' => $report['expense']->amount]);
});

it('carries the closing balance of one period into the next [INV-07] [BR-C02]', function () {
    $rows = reports()->cashflow(CarbonImmutable::parse('2026-09-01'), $this->to);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->key)->toBe('2026-09')
        ->and($rows[0]->opening->amount)->toBe(10_700_000)
        ->and($rows[0]->closing->amount)->toBe(10_700_000)
        ->and($rows[1]->opening->amount)->toBe($rows[0]->closing->amount)
        ->and($rows[1]->income->amount)->toBe(8_000_000)
        ->and($rows[1]->expense->amount)->toBe(2_287_500)
        ->and($rows[1]->closing->amount)->toBe(GoldenFamily::EXPECTED['total_balance'])
        ->and($rows[1]->closing->amount)->toBe($rows[1]->opening->amount + $rows[1]->net->amount);
});

it('clips cash-flow rows to the chosen range [BR-D04]', function () {
    $rows = reports()->cashflow(CarbonImmutable::parse('2026-10-03'), CarbonImmutable::parse('2026-10-10'));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->start)->toBe('2026-10-03')
        ->and($rows[0]->end)->toBe('2026-10-10')
        ->and($rows[0]->expense->amount)->toBe(250_000 + 1_500_000 + 87_500);
});

it('adds categories up to the expense total, sub-categories inside their parent [INV-04] [BR-E04]', function () {
    $rows = reports()->categories($this->from, $this->to);
    $food = collect($rows)->firstWhere('name', 'Makanan & Minuman');

    expect(collect($rows)->sum(fn (CategoryRowData $row) => $row->amount->amount))->toBe(GoldenFamily::EXPECTED['expense'])
        ->and($food->amount->amount)->toBe(650_000)
        ->and($food->share)->toBe(28.4)
        ->and($food->count)->toBe(2)
        ->and($food->children)->toHaveCount(1)
        ->and($food->children[0]->name)->toBe('Restoran')
        ->and($food->children[0]->amount->amount)->toBe(400_000)
        ->and($food->change)->toBeNull()
        ->and($rows[0]->name)->toBe('Tagihan & Utilitas');
});

it('reports income categories separately', function () {
    $rows = reports()->categories($this->from, $this->to, TransactionType::Income);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->name)->toBe('Gaji')
        ->and($rows[0]->amount->amount)->toBe(8_000_000);
});

it('splits expenses per member and they add up [INV-05] [BR-I04]', function () {
    $rows = collect(reports()->members($this->from, $this->to))->keyBy('name');

    expect($rows['Ibu']->expense->amount)->toBe(737_500)
        ->and($rows['Ayah']->expense->amount)->toBe(1_550_000)
        ->and($rows['Ayah']->income->amount)->toBe(8_000_000)
        ->and($rows->sum(fn ($row) => $row->expense->amount))->toBe(GoldenFamily::EXPECTED['expense'])
        ->and($rows['Ayah']->count)->toBe(3);
});

it('balances every wallet: closing = opening + in − out, matching the wallet page [INV-02] [BR-C01]', function () {
    $rows = collect(reports()->wallets($this->from, $this->to))->keyBy('name');
    $walletPage = app(WalletService::class)->balances($this->golden->wallets);

    foreach (GoldenFamily::EXPECTED['balances'] as $name => $expected) {
        $row = $rows[$name];

        expect($row->closing->amount)->toBe($expected)
            ->and($row->closing->amount)->toBe($walletPage[$this->golden->wallets[$name]->id])
            ->and($row->closing->amount)->toBe(
                $row->opening->amount + $row->income->amount - $row->expense->amount + $row->transferIn->amount - $row->transferOut->amount,
            );
    }

    expect($rows['BCA']->transferOut->amount)->toBe(1_000_000)
        ->and($rows['Tunai']->transferIn->amount)->toBe(1_000_000)
        ->and($rows->sum(fn ($row) => $row->closing->amount))->toBe(GoldenFamily::EXPECTED['total_balance']);
});

it('puts October in the yearly heatmap with the peak month [INV-03]', function () {
    $year = reports()->yearly(2026);

    expect($year->months)->toHaveCount(12)
        ->and($year->monthTotals[9]->amount)->toBe(GoldenFamily::EXPECTED['expense'])
        ->and($year->total->amount)->toBe(GoldenFamily::EXPECTED['expense'])
        ->and($year->income->amount)->toBe(8_000_000)
        ->and($year->peakMonth)->toBe('Okt')
        ->and(collect($year->categories)->sum(fn ($c) => $c['total']->amount))->toBe(GoldenFamily::EXPECTED['expense']);
});

it('ranks merchants (or notes) and tags', function () {
    $golden = $this->golden;
    Transaction::factory()->on('2026-10-30')->expense(60_000, $golden->wallets['Tunai'], $golden->categories['Makanan'])
        ->create(['merchant' => 'Warung Sate', 'created_by' => $golden->ibu->id])
        ->tags()->create(['name' => 'liburan', 'family_id' => $golden->family->id]);
    Transaction::factory()->on('2026-10-31')->expense(40_000, $golden->wallets['Tunai'], $golden->categories['Makanan'])
        ->create(['note' => 'warung sate', 'created_by' => $golden->ibu->id]);

    $merchants = collect(reports()->merchants($this->from, $this->to))->keyBy(fn ($row) => mb_strtolower($row->name));
    $tags = reports()->tags($this->from, $this->to);

    expect($merchants['warung sate']->amount->amount)->toBe(100_000)
        ->and($merchants['warung sate']->count)->toBe(2)
        ->and($tags)->toHaveCount(1)
        ->and($tags[0]->name)->toBe('liburan')
        ->and($tags[0]->amount->amount)->toBe(60_000);
});
