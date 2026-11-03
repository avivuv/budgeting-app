<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\DTOs\Budget\BudgetItemData;
use App\DTOs\Report\CashflowRowData;
use App\DTOs\Report\CategoryRowData;
use App\DTOs\Report\MemberRowData;
use App\DTOs\Report\MoneyData;
use App\DTOs\Report\RankedRowData;
use App\DTOs\Report\WalletMovementData;
use App\DTOs\Report\YearlyReportData;
use App\DTOs\Transaction\TransactionFilterData;
use App\Enums\ReportTab;
use App\Enums\TransactionType;
use App\Exports\ArraySheet;
use App\Exports\ReportWorkbook;
use App\Models\Tag;
use App\Models\Transaction;
use App\Services\Tenancy\TenantContext;
use App\Services\Transaction\TransactionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Excel and PDF exports of a report (docs/07 §5.1). Both render the same
 * table built here from ReportService, so exports match the page (INV-03).
 */
class ExportService
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly TransactionService $transactions,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * Sheets: Ringkasan, the chosen report, Transaksi, Kategori, Budget.
     */
    public function excel(ReportTab $tab, CarbonImmutable $from, CarbonImmutable $to, ?int $year = null, TransactionType $type = TransactionType::Expense): BinaryFileResponse
    {
        $report = $this->reports->build($tab, $from, $to, $year, $type);
        $table = $this->table($tab, $report);

        $sheets = [
            new ArraySheet('Ringkasan', ['Keterangan', 'Nilai'], $this->summaryRows($report)),
            new ArraySheet($tab->label(), $table['headings'], $table['rows']),
            new ArraySheet('Transaksi', ['Tanggal', 'Jenis', 'Kategori', 'Dompet', 'Dompet tujuan', 'Nominal', 'Catatan', 'Merchant', 'Tag', 'Dicatat oleh', 'Sumber'], $this->transactionRows($from, $to)),
        ];

        foreach ([ReportTab::Categories, ReportTab::Budget] as $extra) {
            if ($extra !== $tab) {
                $extraTable = $this->table($extra, $this->reports->build($extra, $from, $to, $year));
                $sheets[] = new ArraySheet($extra->label(), $extraTable['headings'], $extraTable['rows']);
            }
        }

        return Excel::download(new ReportWorkbook($sheets), $this->filename($tab, $report, 'xlsx'));
    }

    public function pdf(ReportTab $tab, CarbonImmutable $from, CarbonImmutable $to, ?int $year = null, TransactionType $type = TransactionType::Expense): Response
    {
        $report = $this->reports->build($tab, $from, $to, $year, $type);

        return Pdf::loadView('reports.pdf', [
            'family' => $this->tenant->get()->name,
            'report' => $report,
            'table' => $this->table($tab, $report),
            'generatedAt' => now($this->tenant->get()->timezone)->translatedFormat('j M Y H:i'),
        ])->setPaper('a4', $tab === ReportTab::Yearly ? 'landscape' : 'portrait')
            ->download($this->filename($tab, $report, 'pdf'));
    }

    /**
     * Headings and rows of a report as plain values; amounts stay integers.
     *
     * @param  array<string, mixed>  $report
     * @return array{headings: list<string>, rows: list<list<string|int|float|null>>}
     */
    public function table(ReportTab $tab, array $report): array
    {
        /** @var list<mixed> $rows */
        $rows = $report['rows'] instanceof YearlyReportData ? [] : $report['rows'];

        return match ($tab) {
            ReportTab::Cashflow => [
                'headings' => ['Periode', 'Dari', 'Sampai', 'Saldo awal', 'Pemasukan', 'Pengeluaran', 'Selisih', 'Saldo akhir'],
                'rows' => array_map(fn (CashflowRowData $r): array => [$r->label, $r->start, $r->end, $r->opening->amount, $r->income->amount, $r->expense->amount, $r->net->amount, $r->closing->amount], $rows),
            ],
            ReportTab::Categories => [
                'headings' => ['Kategori', 'Nominal', 'Porsi (%)', 'Periode lalu', 'Perubahan (%)', 'Transaksi'],
                // Parent rows include their sub-categories; children are listed indented below.
                'rows' => array_merge([], ...array_map(fn (CategoryRowData $r): array => [
                    [$r->name, $r->amount->amount, $r->share, $r->previous->amount, $r->change, $r->count],
                    ...array_map(fn (CategoryRowData $c): array => ['  ↳ '.$c->name, $c->amount->amount, $c->share, $c->previous->amount, $c->change, $c->count], $r->children),
                ], $rows)),
            ],
            ReportTab::Members => [
                'headings' => ['Anggota', 'Pemasukan', 'Pengeluaran', 'Porsi pengeluaran (%)', 'Transaksi'],
                'rows' => array_map(fn (MemberRowData $r): array => [$r->name, $r->income->amount, $r->expense->amount, $r->expenseShare, $r->count], $rows),
            ],
            ReportTab::Wallets => [
                'headings' => ['Dompet', 'Jenis', 'Saldo awal', 'Pemasukan', 'Pengeluaran', 'Transfer masuk', 'Transfer keluar', 'Saldo akhir'],
                'rows' => array_map(fn (WalletMovementData $r): array => [$r->name.($r->isArchived ? ' (diarsipkan)' : ''), $r->typeLabel, $r->opening->amount, $r->income->amount, $r->expense->amount, $r->transferIn->amount, $r->transferOut->amount, $r->closing->amount], $rows),
            ],
            ReportTab::Budget => [
                'headings' => ['Kategori', 'Budget', 'Realisasi', 'Sisa', 'Terpakai (%)', 'Status'],
                'rows' => array_map(fn (BudgetItemData $r): array => [$r->categoryName, $r->amount, $r->spent, $r->remaining, $r->percentage, $r->statusLabel], $rows),
            ],
            ReportTab::Yearly => $this->yearlyTable($report['rows']),
            ReportTab::Merchants, ReportTab::Tags => [
                'headings' => [$tab === ReportTab::Tags ? 'Tag' : 'Merchant / catatan', 'Nominal', 'Porsi (%)', 'Transaksi'],
                'rows' => array_map(fn (RankedRowData $r): array => [$r->name, $r->amount->amount, $r->share, $r->count], $rows),
            ],
        };
    }

    /**
     * @return array{headings: list<string>, rows: list<list<string|int|float|null>>}
     */
    private function yearlyTable(mixed $year): array
    {
        if (! $year instanceof YearlyReportData) {
            return ['headings' => [], 'rows' => []];
        }

        $rows = array_map(fn (array $c): array => [$c['name'], ...$c['values'], $c['total']->amount], $year->categories);
        $rows[] = ['Total', ...array_map(fn (MoneyData $m): int => $m->amount, $year->monthTotals), $year->total->amount];

        return ['headings' => ['Kategori', ...$year->months, 'Total'], 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int>>
     */
    private function summaryRows(array $report): array
    {
        /** @var array{income: MoneyData, expense: MoneyData, net: MoneyData} $totals */
        $totals = $report['totals'];

        return [
            ['Keluarga', $this->tenant->get()->name],
            ['Laporan', (string) $report['title']],
            ['Rentang', (string) $report['rangeLabel']],
            ['Pemasukan', $totals['income']->amount],
            ['Pengeluaran', $totals['expense']->amount],
            ['Selisih', $totals['net']->amount],
        ];
    }

    /**
     * @return list<list<string|int|null>>
     */
    private function transactionRows(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $filter = new TransactionFilterData(from: $from->toDateString(), to: $to->toDateString());

        return array_values($this->transactions->all($filter)->map(fn (Transaction $t): array => [
            $t->date->toDateString(),
            $t->type->label(),
            $t->type === TransactionType::Transfer ? null : $t->category?->name,
            $t->wallet->name ?? null,
            $t->toWallet?->name,
            $t->amount,
            $t->note,
            $t->merchant,
            $t->tags->map(fn (Tag $tag): string => $tag->name)->implode(', '),
            $t->creator->name ?? 'Mantan anggota',
            $t->source->label(),
        ])->all());
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function filename(ReportTab $tab, array $report, string $extension): string
    {
        $range = $tab === ReportTab::Yearly ? (string) $report['year'] : $report['from'].'_'.$report['to'];

        return 'laporan-'.Str::slug($tab->label()).'-'.$range.'.'.$extension;
    }
}
