<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\DTOs\Report\CashflowRowData;
use App\DTOs\Report\CategoryRowData;
use App\DTOs\Report\MemberRowData;
use App\DTOs\Report\MoneyData;
use App\DTOs\Report\RankedRowData;
use App\DTOs\Report\WalletMovementData;
use App\DTOs\Report\YearlyReportData;
use App\DTOs\Transaction\TransactionFilterData;
use App\Enums\CategoryType;
use App\Enums\ReportTab;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Wallet;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Services\Budget\BudgetService;
use App\Support\Math\Percentage;
use App\Support\Period\Period;
use Carbon\CarbonImmutable;

/**
 * Reports (docs/07 §5). The same DTOs feed the web page, the exports, the
 * REST API and later the bot, so every channel shows the same numbers
 * (INV-03 … INV-07). Dates are inclusive (BR-D04); transfers never count as
 * income or expense (BR-B11); deleted transactions never count (BR-B08).
 */
class ReportService
{
    public const int MERCHANT_LIMIT = 20;

    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
        private readonly WalletRepositoryInterface $wallets,
        private readonly CategoryRepositoryInterface $categories,
        private readonly UserRepositoryInterface $users,
        private readonly PeriodService $periods,
        private readonly BudgetService $budgets,
    ) {}

    /**
     * The current period, used when no range is chosen.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function defaultRange(): array
    {
        $period = $this->periods->current();

        return [$period->start, $period->end];
    }

    /**
     * One report with the summary of its range — the payload shared by the web
     * page, the Excel/PDF export and the REST API.
     *
     * @return array<string, mixed>
     */
    public function build(ReportTab $tab, CarbonImmutable $from, CarbonImmutable $to, ?int $year = null, TransactionType $type = TransactionType::Expense): array
    {
        $budgetPeriod = $this->periods->containing($from);

        $rows = match ($tab) {
            ReportTab::Cashflow => $this->cashflow($from, $to),
            ReportTab::Categories => $this->categories($from, $to, $type),
            ReportTab::Members => $this->members($from, $to),
            ReportTab::Wallets => $this->wallets($from, $to),
            ReportTab::Budget => $this->budgets->overview($budgetPeriod),
            ReportTab::Yearly => $this->yearly($year ?? $to->year),
            ReportTab::Merchants => $this->merchants($from, $to),
            ReportTab::Tags => $this->tags($from, $to),
        };

        return [
            'tab' => $tab->value,
            'title' => $tab->label(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rangeLabel' => $from->translatedFormat('j M Y').' – '.$to->translatedFormat('j M Y'),
            'year' => $year ?? $to->year,
            'type' => $type->value,
            'totals' => $this->totals($from, $to),
            'rows' => $rows,
            'budgetTotals' => $tab === ReportTab::Budget ? $this->budgets->totals($rows, $budgetPeriod) : null,
        ];
    }

    /**
     * Income vs expense per period touching the range, each clipped to it,
     * with the total balance (BR-C02) at its start and end.
     *
     * @return list<CashflowRowData>
     */
    public function cashflow(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $daily = $this->transactions->dailyTotals($this->range($from, $to));
        $walletIds = $this->activeWalletIds();
        $rows = [];

        for ($period = $this->periods->containing($from); $period->start->lessThanOrEqualTo($to); $period = $this->periods->next($period)) {
            $start = $period->start->max($from);
            $end = $period->end->min($to);

            $income = $expense = 0;
            foreach ($daily as $date => $totals) {
                if ($date >= $start->toDateString() && $date <= $end->toDateString()) {
                    $income += $totals['income'];
                    $expense += $totals['expense'];
                }
            }

            $rows[] = new CashflowRowData(
                key: $period->key(),
                label: $this->periods->labelMonth($period)->translatedFormat('M Y'),
                start: $start->toDateString(),
                end: $end->toDateString(),
                income: MoneyData::of($income),
                expense: MoneyData::of($expense),
                net: MoneyData::of($income - $expense),
                opening: MoneyData::of(array_sum($this->wallets->balances($walletIds, $start->subDay()->toDateString()))),
                closing: MoneyData::of(array_sum($this->wallets->balances($walletIds, $end->toDateString()))),
            );
        }

        return $rows;
    }

    /**
     * Per category of the given type: parents include their sub-categories
     * (BR-E04) and add up to the type's total (INV-04). Compared with the
     * previous range of the same length.
     *
     * @return list<CategoryRowData>
     */
    public function categories(CarbonImmutable $from, CarbonImmutable $to, TransactionType $type = TransactionType::Expense): array
    {
        $days = (int) $from->diffInDays($to) + 1;
        $current = $this->transactions->sumByCategory($this->range($from, $to, $type));
        $previous = $this->transactions->sumByCategory($this->range($from->subDays($days), $from->subDay(), $type));
        $counts = $this->transactions->countByCategory($this->range($from, $to, $type));
        $total = array_sum($current);

        $rows = [];
        foreach ($this->categories->tree($type->categoryType() ?? CategoryType::Expense, includeArchived: true) as $parent) {
            $children = [];
            foreach ($parent->children as $child) {
                if (($current[$child->id] ?? 0) > 0 || ($previous[$child->id] ?? 0) > 0) {
                    $children[] = $this->categoryRow($child, $current[$child->id] ?? 0, $previous[$child->id] ?? 0, $counts[$child->id] ?? 0, $total, []);
                }
            }

            $ids = [$parent->id, ...$parent->children->modelKeys()];
            $amount = array_sum(array_intersect_key($current, array_flip($ids)));
            $before = array_sum(array_intersect_key($previous, array_flip($ids)));

            if ($amount > 0 || $before > 0) {
                $count = array_sum(array_intersect_key($counts, array_flip($ids)));
                $rows[] = $this->categoryRow($parent, $amount, $before, $count, $total, $children);
            }
        }

        usort($rows, fn (CategoryRowData $a, CategoryRowData $b): int => $b->amount->amount <=> $a->amount->amount);

        return $rows;
    }

    /**
     * Income and expense recorded by each member (INV-05).
     *
     * @return list<MemberRowData>
     */
    public function members(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $expense = $this->transactions->sumByCreator($this->range($from, $to, TransactionType::Expense));
        $income = $this->transactions->sumByCreator($this->range($from, $to, TransactionType::Income));
        $ids = array_values(array_unique([...array_keys($expense), ...array_keys($income)]));
        $names = $this->users->namesByIds(array_values(array_filter($ids)));
        $totalExpense = array_sum(array_column($expense, 'amount'));

        $rows = array_map(fn (int $id): MemberRowData => new MemberRowData(
            userId: $id === 0 ? null : $id,
            name: $names[$id] ?? 'Mantan anggota',
            income: MoneyData::of($income[$id]['amount'] ?? 0),
            expense: MoneyData::of($expense[$id]['amount'] ?? 0),
            expenseShare: Percentage::of($expense[$id]['amount'] ?? 0, $totalExpense),
            count: ($income[$id]['count'] ?? 0) + ($expense[$id]['count'] ?? 0),
        ), $ids);

        usort($rows, fn (MemberRowData $a, MemberRowData $b): int => $b->expense->amount <=> $a->expense->amount);

        return $rows;
    }

    /**
     * Opening balance, money in/out and closing balance per wallet (BR-C01).
     * Archived wallets appear only when they moved in the range.
     *
     * @return list<WalletMovementData>
     */
    public function wallets(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $wallets = $this->wallets->listForFamily(includeArchived: true);
        $ids = array_values($wallets->map(fn (Wallet $wallet): int => $wallet->id)->all());
        $opening = $this->wallets->balances($ids, $from->subDay()->toDateString());
        $closing = $this->wallets->balances($ids, $to->toDateString());
        $moves = $this->wallets->movements($ids, $from->toDateString(), $to->toDateString());

        $rows = [];
        foreach ($wallets as $wallet) {
            $move = $moves[$wallet->id] ?? ['income' => 0, 'expense' => 0, 'transferIn' => 0, 'transferOut' => 0];

            if ($wallet->is_archived && array_sum($move) === 0) {
                continue;
            }

            $rows[] = new WalletMovementData(
                id: $wallet->id,
                name: $wallet->name,
                typeLabel: $wallet->type->label(),
                isArchived: $wallet->is_archived,
                opening: MoneyData::of($opening[$wallet->id] ?? 0),
                income: MoneyData::of($move['income']),
                expense: MoneyData::of($move['expense']),
                transferIn: MoneyData::of($move['transferIn']),
                transferOut: MoneyData::of($move['transferOut']),
                closing: MoneyData::of($closing[$wallet->id] ?? 0),
            );
        }

        return $rows;
    }

    /**
     * Expense per top-level category for the twelve periods labelled in $year.
     */
    public function yearly(int $year): YearlyReportData
    {
        $periods = array_map(fn (int $month): Period => $this->periods->forMonth($year, $month), range(1, 12));
        $roots = $this->categories->tree(CategoryType::Expense, includeArchived: true);

        $values = [];
        $monthTotals = [];
        $income = 0;
        foreach ($periods as $index => $period) {
            $sums = $this->transactions->sumByCategory($this->range($period->start, $period->end, TransactionType::Expense));
            $income += $this->transactions->totals($this->range($period->start, $period->end))['income'];
            $monthTotals[$index] = array_sum($sums);

            foreach ($roots as $root) {
                $ids = [$root->id, ...$root->children->modelKeys()];
                $values[$root->id][$index] = array_sum(array_intersect_key($sums, array_flip($ids)));
            }
        }

        $categories = [];
        foreach ($roots as $root) {
            $total = array_sum($values[$root->id] ?? []);
            if ($total > 0) {
                $categories[] = ['id' => $root->id, 'name' => $root->name, 'values' => array_values($values[$root->id]), 'total' => MoneyData::of($total)];
            }
        }
        usort($categories, fn (array $a, array $b): int => $b['total']->amount <=> $a['total']->amount);

        $months = array_map(fn (int $month): string => CarbonImmutable::create($year, $month, 1)->translatedFormat('M'), range(1, 12));
        $peak = max($monthTotals);

        return new YearlyReportData(
            year: $year,
            months: $months,
            categories: $categories,
            monthTotals: array_map(fn (int $amount): MoneyData => MoneyData::of($amount), $monthTotals),
            total: MoneyData::of(array_sum($monthTotals)),
            income: MoneyData::of($income),
            peakMonth: $peak > 0 ? $months[array_search($peak, $monthTotals, true)] : null,
        );
    }

    /**
     * Biggest expense merchants (from receipts, or the note when typed by hand).
     *
     * @return list<RankedRowData>
     */
    public function merchants(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $filter = $this->range($from, $to, TransactionType::Expense);

        return $this->ranked($this->transactions->sumByMerchant($filter, self::MERCHANT_LIMIT), $this->transactions->totals($filter)['expense']);
    }

    /**
     * Expense per tag, e.g. the cost of a holiday.
     *
     * @return list<RankedRowData>
     */
    public function tags(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $filter = $this->range($from, $to, TransactionType::Expense);

        return $this->ranked($this->transactions->sumByTag($filter), $this->transactions->totals($filter)['expense']);
    }

    /**
     * Income/expense totals of the range (the summary row of every report).
     *
     * @return array{income: MoneyData, expense: MoneyData, net: MoneyData}
     */
    public function totals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $totals = $this->transactions->totals($this->range($from, $to));

        return [
            'income' => MoneyData::of($totals['income']),
            'expense' => MoneyData::of($totals['expense']),
            'net' => MoneyData::of($totals['income'] - $totals['expense']),
        ];
    }

    private function range(CarbonImmutable $from, CarbonImmutable $to, ?TransactionType $type = null): TransactionFilterData
    {
        return new TransactionFilterData(from: $from->toDateString(), to: $to->toDateString(), type: $type);
    }

    /**
     * @param  list<CategoryRowData>  $children
     */
    private function categoryRow(Category $category, int $amount, int $previous, int $count, int $total, array $children): CategoryRowData
    {
        return new CategoryRowData(
            id: $category->id,
            name: $category->name,
            color: $category->color,
            amount: MoneyData::of($amount),
            share: Percentage::of($amount, $total),
            previous: MoneyData::of($previous),
            change: Percentage::change($amount, $previous),
            count: $count,
            children: $children,
        );
    }

    /**
     * @param  list<array{name: string, amount: int, count: int}>  $rows
     * @return list<RankedRowData>
     */
    private function ranked(array $rows, int $total): array
    {
        return array_map(fn (array $row): RankedRowData => new RankedRowData(
            name: $row['name'],
            amount: MoneyData::of($row['amount']),
            count: $row['count'],
            share: Percentage::of($row['amount'], $total),
        ), $rows);
    }

    /**
     * Wallets that make up the family total (BR-C02).
     *
     * @return list<int>
     */
    private function activeWalletIds(): array
    {
        return array_values($this->wallets->listForFamily()->map(fn (Wallet $wallet): int => $wallet->id)->all());
    }
}
