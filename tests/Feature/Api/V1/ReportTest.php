<?php

use App\Enums\FamilyRole;
use App\Exports\ArraySheet;
use App\Exports\ReportWorkbook;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Fixtures\GoldenFamily;

/*
| Scenarios: docs/test-cases/reports.md · Contract: docs/api-contracts/reports.md
|
| Golden family, October 2026: income 8.000.000, expense 2.287.500, total balance
| 16.412.500; one transfer and one deleted expense that must never be counted.
*/

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->golden = GoldenFamily::seed();
});

function reportAs(User $user, array $abilities = ['read']): void
{
    Sanctum::actingAs($user, $abilities);
}

describe('positive', function () {
    it('[REP-01] shows the October summary without transfers or deleted transactions [INV-02] [BR-B11] [BR-B08] [BR-A05]', function () {
        // GIVEN Ibu, a member, in the golden family
        reportAs($this->golden->ibu);

        // WHEN she opens the summary of October
        $response = $this->getJson('/api/v1/summary?period=2026-10');

        // THEN the cards show the hand-calculated numbers; September was empty, so "new"
        $response->assertOk()
            ->assertJsonPath('data.period.key', '2026-10')
            ->assertJsonPath('data.period.label', 'Oktober 2026')
            ->assertJsonPath('data.period.is_current', true)
            ->assertJsonPath('data.period.previous_key', '2026-09')
            ->assertJsonPath('data.summary.balance.amount', GoldenFamily::EXPECTED['total_balance'])
            ->assertJsonPath('data.summary.balance.formatted', 'Rp 16.412.500')
            ->assertJsonPath('data.summary.income.amount', 8_000_000)
            ->assertJsonPath('data.summary.expense.amount', 2_287_500)
            ->assertJsonPath('data.summary.expense.previous', 0)
            ->assertJsonPath('data.summary.expense.change', null)
            ->assertJsonPath('data.summary.net.amount', 5_712_500);

        $this->getJson('/api/v1/summary')->assertJsonPath('data.summary.expense.amount', 2_287_500);
    });

    it('[REP-02] filters the summary by wallet and by member', function () {
        reportAs($this->golden->ibu);

        $this->getJson('/api/v1/summary?wallet_id='.$this->golden->wallets['BCA']->id)
            ->assertOk()
            ->assertJsonPath('data.summary.expense.amount', 1_900_000)
            ->assertJsonPath('data.summary.balance.amount', 15_100_000);

        $this->getJson('/api/v1/summary?user_id='.$this->golden->ibu->id)
            ->assertJsonPath('data.summary.expense.amount', GoldenFamily::EXPECTED['by_member']['Ibu']);
    });

    it('[REP-03] compares November with October [BR-A05]', function () {
        $this->travelTo('2026-11-05 09:00:00');
        Transaction::factory()->on('2026-11-04')
            ->expense(100_000, $this->golden->wallets['Tunai'], $this->golden->categories['Transportasi'])
            ->create(['created_by' => $this->golden->ayah->id]);
        reportAs($this->golden->ayah);

        $this->getJson('/api/v1/summary')
            ->assertOk()
            ->assertJsonPath('data.period.key', '2026-11')
            ->assertJsonPath('data.summary.expense.amount', 100_000)
            ->assertJsonPath('data.summary.expense.previous', 2_287_500)
            ->assertJsonPath('data.summary.expense.change', -95.6);
    });

    it('[REP-04] splits expense per parent category; the slices add up to the total [INV-04] [BR-E04]', function () {
        reportAs($this->golden->ibu);

        $slices = $this->getJson('/api/v1/dashboard/categories?period=2026-10')
            ->assertOk()
            ->assertJsonPath('meta.period.key', '2026-10')
            ->assertJsonPath('data.0.name', 'Tagihan & Utilitas')
            ->assertJsonPath('data.0.amount', 1_500_000)
            ->json('data');

        expect(collect($slices)->firstWhere('name', 'Makanan & Minuman'))->toMatchArray(['amount' => 650_000, 'formatted' => 'Rp 650.000', 'category_id' => $this->golden->categories['Makanan']->id])
            ->and(collect($slices)->pluck('name'))->not->toContain('Restoran')
            ->and(collect($slices)->sum('amount'))->toBe(GoldenFamily::EXPECTED['expense']);
    });

    it('[REP-05] splits expense per member; it adds up to the total [INV-05]', function () {
        reportAs($this->golden->ibu);

        $members = collect($this->getJson('/api/v1/dashboard/members')->assertOk()->json('data'));

        expect($members->pluck('amount', 'name')->all())->toEqual(GoldenFamily::EXPECTED['by_member'])
            ->and($members->first())->toHaveKeys(['user_id', 'name', 'amount', 'formatted', 'count', 'percentage'])
            ->and($members->sum('amount'))->toBe(GoldenFamily::EXPECTED['expense']);
    });

    it('[REP-06] serves every other dashboard widget with the same numbers [INV-02]', function () {
        Budget::factory()->forCategory($this->golden->categories['Makanan'], '2026-10-01', 600_000)->create();
        reportAs($this->golden->ibu);

        $cashflow = $this->getJson('/api/v1/dashboard/cashflow')->assertOk()->json('data');
        expect($cashflow)->toHaveCount(6)
            ->and(end($cashflow))->toMatchArray(['key' => '2026-10', 'income' => 8_000_000, 'expense' => 2_287_500, 'net' => 5_712_500]);

        $trend = $this->getJson('/api/v1/dashboard/trend')->assertOk()->json('data');
        expect($trend)->toHaveCount(31)
            ->and(end($trend)['cumulative'])->toBe(GoldenFamily::EXPECTED['expense']);

        $wallets = collect($this->getJson('/api/v1/dashboard/wallets')->assertOk()->json('data'));
        expect($wallets->pluck('balance', 'name')->all())->toEqual(GoldenFamily::EXPECTED['balances'])
            ->and($wallets->first())->toHaveKeys(['type_label', 'balance_formatted', 'is_archived']);

        $this->getJson('/api/v1/dashboard/budgets')->assertOk()
            ->assertJsonPath('data.0.category.name', 'Makanan & Minuman')
            ->assertJsonPath('data.0.spent', 650_000);

        $this->getJson('/api/v1/dashboard/goals')->assertOk()->assertJsonPath('data', []);

        $this->getJson('/api/v1/dashboard/recent')->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('data.0.date', '2026-10-20')
            ->assertJsonPath('data.0.amount', 400_000)
            ->assertJsonPath('data.0.category.name', 'Restoran');

        expect($this->getJson('/api/v1/dashboard/insights')->assertOk()->json('data'))
            ->toBeArray()->not->toBeEmpty()
            ->toContain('Budget terlampaui: Makanan & Minuman.');
    });

    it('[REP-07] reports the October cash flow from opening to closing balance [INV-02] [BR-C01]', function () {
        reportAs($this->golden->ibu);

        $this->getJson('/api/v1/reports/cashflow?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonPath('data.tab', 'cashflow')
            ->assertJsonPath('data.title', 'Arus kas')
            ->assertJsonPath('data.range_label', '1 Okt 2026 – 31 Okt 2026')
            ->assertJsonPath('data.totals.expense.amount', 2_287_500)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.income.amount', 8_000_000)
            ->assertJsonPath('data.rows.0.expense.amount', 2_287_500)
            ->assertJsonPath('data.rows.0.opening.amount', 10_700_000)
            ->assertJsonPath('data.rows.0.closing.amount', GoldenFamily::EXPECTED['total_balance'])
            ->assertJsonPath('data.rows.0.closing.formatted', 'Rp 16.412.500');
    });

    it('[REP-08] reports categories with their sub-categories, for expense and income [BR-E04] [INV-04]', function () {
        reportAs($this->golden->ibu);

        $rows = collect($this->getJson('/api/v1/reports/categories?from=2026-10-01&to=2026-10-31')->assertOk()->json('data.rows'));
        $food = $rows->firstWhere('name', 'Makanan & Minuman');

        expect($food['amount']['amount'])->toBe(650_000)
            ->and($food['children'][0])->toMatchArray(['name' => 'Restoran', 'amount' => ['amount' => 400_000, 'formatted' => 'Rp 400.000']])
            ->and($rows->sum('amount.amount'))->toBe(GoldenFamily::EXPECTED['expense']);

        $this->getJson('/api/v1/reports/categories?type=income&from=2026-10-01&to=2026-10-31')
            ->assertJsonPath('data.type', 'income')
            ->assertJsonPath('data.rows.0.name', 'Gaji')
            ->assertJsonPath('data.rows.0.amount.amount', 8_000_000);
    });

    it('[REP-09] reports members, wallets, merchants, tags, budget and the year', function () {
        Budget::factory()->forCategory($this->golden->categories['Makanan'], '2026-10-01', 800_000)->create();
        reportAs($this->golden->ibu);
        $range = 'from=2026-10-01&to=2026-10-31';

        $members = collect($this->getJson("/api/v1/reports/members?{$range}")->assertOk()->json('data.rows'));
        expect($members->firstWhere('name', 'Ayah'))->toMatchArray(['income' => ['amount' => 8_000_000, 'formatted' => 'Rp 8.000.000']])
            ->and($members->sum('expense.amount'))->toBe(GoldenFamily::EXPECTED['expense'])
            ->and($members->first())->toHaveKey('expense_share');

        $tunai = collect($this->getJson("/api/v1/reports/wallets?{$range}")->assertOk()->json('data.rows'))->firstWhere('name', 'Tunai');
        expect($tunai['transfer_in']['amount'])->toBe(1_000_000)
            ->and($tunai['opening']['amount'])->toBe(500_000)
            ->and($tunai['closing']['amount'])->toBe(1_200_000);

        $this->getJson("/api/v1/reports/merchants?{$range}")->assertOk();
        $this->getJson("/api/v1/reports/tags?{$range}")->assertOk()->assertJsonPath('data.rows', []);

        $this->getJson("/api/v1/reports/budget?{$range}")->assertOk()
            ->assertJsonPath('data.rows.0.spent', 650_000)
            ->assertJsonPath('data.budget_totals.amount', 800_000);

        $this->getJson('/api/v1/reports/yearly?year=2026')->assertOk()
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.rows.peak_month', 'Okt')
            ->assertJsonPath('data.rows.total.amount', GoldenFamily::EXPECTED['expense'])
            ->assertJsonCount(12, 'data.rows.month_totals');
    });

    it('[REP-10] uses the current period when no range is given [BR-D01]', function () {
        reportAs($this->golden->ibu);

        $this->getJson('/api/v1/reports/cashflow')
            ->assertOk()
            ->assertJsonPath('data.from', '2026-10-01')
            ->assertJsonPath('data.to', '2026-10-31');
    });

    it('[REP-11] exports the categories report to Excel with the same numbers [INV-03]', function () {
        Excel::fake();
        reportAs($this->golden->ibu);

        $this->get('/api/v1/reports/categories/export?from=2026-10-01&to=2026-10-31&format=xlsx')->assertOk();

        Excel::assertDownloaded('laporan-kategori-2026-10-01_2026-10-31.xlsx', function (ReportWorkbook $workbook) {
            $sheets = collect($workbook->sheets())->keyBy(fn (ArraySheet $sheet) => $sheet->title());
            $summary = collect($sheets['Ringkasan']->array())->pluck(1, 0);

            return $sheets->keys()->all() === ['Ringkasan', 'Kategori', 'Transaksi', 'Budget']
                && $summary['Pengeluaran'] === GoldenFamily::EXPECTED['expense']
                && collect($sheets['Kategori']->array())->firstWhere(0, 'Makanan & Minuman')[1] === 650_000
                && count($sheets['Transaksi']->array()) === 7;
        });
    });

    it('[REP-12] exports the wallets report as a PDF', function () {
        reportAs($this->golden->ibu);

        $response = $this->get('/api/v1/reports/wallets/export?format=pdf')->assertOk();

        expect($response->headers->get('content-type'))->toBe('application/pdf')
            ->and($response->headers->get('content-disposition'))->toContain('laporan-dompet-2026-10-01_2026-10-31.pdf')
            ->and(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
    });

    it('[REP-13] lets a viewer read and export reports', function () {
        Excel::fake();
        reportAs(addMember($this->golden->family, FamilyRole::Viewer));

        $this->getJson('/api/v1/summary')->assertOk()->assertJsonPath('data.summary.expense.amount', 2_287_500);
        $this->getJson('/api/v1/reports/members')->assertOk();
        $this->get('/api/v1/reports/members/export')->assertOk();
    });
});

describe('negative', function () {
    it('[REP-21] refuses a period that has not started or is malformed', function () {
        reportAs($this->golden->ibu);

        $this->getJson('/api/v1/summary?period=2026-12')
            ->assertUnprocessable()
            ->assertJsonPath('errors.period.0', 'Periode tersebut belum dimulai.');
        $this->getJson('/api/v1/dashboard/categories?period=2026-11')->assertUnprocessable()->assertJsonValidationErrors('period');

        $this->getJson('/api/v1/summary?period=2026-13')
            ->assertUnprocessable()
            ->assertJsonPath('errors.period.0', 'Format periode tidak valid.');
    });

    it('[REP-22] refuses a reversed, too long or half range [BR-D04]', function () {
        reportAs($this->golden->ibu);

        $this->getJson('/api/v1/reports/cashflow?from=2026-10-10&to=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/api/v1/reports/cashflow?from=2025-01-01&to=2026-10-31')
            ->assertUnprocessable()
            ->assertJsonPath('errors.to.0', 'Rentang laporan maksimal satu tahun.');
        $this->getJson('/api/v1/reports/cashflow?from=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->get('/api/v1/reports/cashflow/export?to=2026-10-01', ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('from');
    });

    it('[REP-23] answers 404 for an unknown report, widget or export', function () {
        reportAs($this->golden->ibu);

        $this->getJson('/api/v1/reports/unknown')->assertNotFound()->assertJsonPath('message', 'Data tidak ditemukan.');
        $this->getJson('/api/v1/dashboard/unknown')->assertNotFound();
        $this->getJson('/api/v1/reports/unknown/export')->assertNotFound();
    });

    it('[REP-24] refuses an unknown export format or category type', function () {
        reportAs($this->golden->ibu);

        $this->getJson('/api/v1/reports/cashflow/export?format=docx')->assertUnprocessable()->assertJsonValidationErrors('format');
        $this->getJson('/api/v1/reports/categories?type=transfer')->assertUnprocessable()->assertJsonValidationErrors('type');
    });

    it('[REP-25] shows zero for a wallet of another family instead of leaking it [BR-I06]', function () {
        $neighbour = User::factory()->create();
        $other = createFamily($neighbour, 'Tetangga');
        $foreignWallet = Wallet::query()->withoutGlobalScopes()->where('family_id', $other->id)->firstOrFail();
        reportAs($this->golden->ibu);

        $this->getJson('/api/v1/summary?wallet_id='.$foreignWallet->id)
            ->assertOk()
            ->assertJsonPath('data.summary.expense.amount', 0)
            ->assertJsonPath('data.summary.balance.amount', 0);
        $this->getJson('/api/v1/dashboard/wallets?wallet_id='.$foreignWallet->id)->assertOk()->assertJsonPath('data', []);
    });

    it('[REP-26] gives a neighbour only the numbers of their own family [BR-I06]', function () {
        $neighbour = User::factory()->create();
        createFamily($neighbour, 'Tetangga');
        reportAs($neighbour);

        $this->getJson('/api/v1/summary')->assertOk()->assertJsonPath('data.summary.expense.amount', 0);
        $this->getJson('/api/v1/reports/categories')->assertOk()->assertJsonPath('data.totals.expense.amount', 0)->assertJsonPath('data.rows', []);
    });

    it('[REP-27] requires signing in', function () {
        $this->getJson('/api/v1/summary')->assertUnauthorized();
        $this->getJson('/api/v1/reports/cashflow')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard/trend')->assertUnauthorized();
    });
});
