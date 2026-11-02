<?php

use App\Enums\FamilyRole;
use App\Exports\ArraySheet;
use App\Exports\ReportWorkbook;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Fixtures\GoldenFamily;

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->golden = GoldenFamily::seed();
});

it('shows the cash-flow report of the current period by default', function () {
    $this->actingAs($this->golden->ibu)
        ->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->where('report.tab', 'cashflow')
            ->where('report.from', '2026-10-01')
            ->where('report.to', '2026-10-31')
            ->where('report.totals.expense.amount', GoldenFamily::EXPECTED['expense'])
            ->where('report.rows.0.closing.amount', GoldenFamily::EXPECTED['total_balance'])
            ->has('tabs', 8)
            ->has('presets', 4));
});

it('opens every report tab', function (string $tab) {
    $this->actingAs($this->golden->ayah)
        ->get(route('reports.index', ['tab' => $tab, 'from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('report.tab', $tab));
})->with(['cashflow', 'categories', 'members', 'wallets', 'budget', 'yearly', 'merchants', 'tags']);

it('validates the range [BR-D04]', function () {
    $this->actingAs($this->golden->ayah)
        ->get(route('reports.index', ['from' => '2026-10-10', 'to' => '2026-10-01']))
        ->assertSessionHasErrors('to');

    $this->actingAs($this->golden->ayah)
        ->get(route('reports.index', ['from' => '2025-01-01', 'to' => '2026-10-31']))
        ->assertSessionHasErrors('to');

    $this->actingAs($this->golden->ayah)
        ->get(route('reports.index', ['tab' => 'nope']))
        ->assertSessionHasErrors('tab');
});

it('exports the same numbers to Excel as the page shows [INV-03]', function () {
    Excel::fake();

    $this->actingAs($this->golden->ayah)
        ->get(route('reports.export', ['tab' => 'categories', 'from' => '2026-10-01', 'to' => '2026-10-31', 'format' => 'xlsx']))
        ->assertOk();

    Excel::assertDownloaded('laporan-kategori-2026-10-01_2026-10-31.xlsx', function (ReportWorkbook $workbook) {
        $sheets = collect($workbook->sheets())->keyBy(fn (ArraySheet $sheet) => $sheet->title());

        $summary = collect($sheets['Ringkasan']->array())->pluck(1, 0);
        $categories = collect($sheets['Kategori']->array());
        $transactions = $sheets['Transaksi']->array();

        return $sheets->keys()->all() === ['Ringkasan', 'Kategori', 'Transaksi', 'Budget']
            && $summary['Pemasukan'] === GoldenFamily::EXPECTED['income']
            && $summary['Pengeluaran'] === GoldenFamily::EXPECTED['expense']
            && $categories->firstWhere(0, 'Makanan & Minuman')[1] === 650_000
            && $categories->firstWhere(0, '  ↳ Restoran')[1] === 400_000
            // Parent rows add up to the expense total (INV-04).
            && $categories->reject(fn ($row) => str_starts_with($row[0], '  ↳'))->sum(1) === GoldenFamily::EXPECTED['expense']
            // 7 live transactions; the deleted one is not exported (BR-B08).
            && count($transactions) === 7;
    });
});

it('exports a PDF', function () {
    $response = $this->actingAs($this->golden->ayah)
        ->get(route('reports.export', ['tab' => 'wallets', 'format' => 'pdf']))
        ->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('laporan-dompet-2026-10-01_2026-10-31.pdf')
        ->and(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
});

it('lets viewers read and export reports but not other families [BR-I06]', function () {
    $viewer = addMember($this->golden->family, FamilyRole::Viewer);
    $this->actingAs($viewer)->get(route('reports.index'))->assertOk();

    $stranger = User::factory()->create();
    createFamily($stranger, 'Tetangga');

    $this->actingAs($stranger)
        ->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page->where('report.totals.expense.amount', 0));
});
