<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\DTOs\Dashboard\CashflowPointData;
use App\DTOs\Dashboard\CategorySliceData;
use App\DTOs\Dashboard\DashboardSummaryData;
use App\DTOs\Dashboard\MemberTotalData;
use App\DTOs\Dashboard\PeriodData;
use App\DTOs\Dashboard\StatData;
use App\DTOs\Dashboard\TrendPointData;
use App\DTOs\Transaction\TransactionFilterData;
use App\DTOs\Wallet\WalletItemData;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Services\Tenancy\TenantContext;
use App\Support\Math\Percentage;
use App\Support\Money\MoneyFormatter;
use App\Support\Period\Period;
use App\Support\Period\PeriodResolver;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * Numbers for the dashboard (docs/07-dashboard-reports.md §3). Each widget is
 * cached per (family, period, filter) for 5 minutes; every change to the
 * family's transactions, wallets or categories invalidates it (BR-C04).
 *
 * The scope filter only carries wallet and member ids; the period decides the dates.
 */
class DashboardService
{
    public const int CACHE_TTL_SECONDS = 300;

    public const int CASHFLOW_PERIODS = 6;

    public const int RECENT_LIMIT = 10;

    /** Category change (in %) worth mentioning as an insight. */
    public const int INSIGHT_MIN_CHANGE = 10;

    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
        private readonly WalletRepositoryInterface $wallets,
        private readonly CategoryRepositoryInterface $categories,
        private readonly UserRepositoryInterface $users,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * Today in the family's timezone (BR-D03).
     */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->tenant->get()->timezone)->startOfDay();
    }

    public function currentPeriod(): Period
    {
        return PeriodResolver::containing($this->today(), $this->tenant->get()->period_start_day);
    }

    /**
     * The period for a "YYYY-MM" key; missing, invalid or future keys give the current period.
     */
    public function period(?string $key): Period
    {
        $current = $this->currentPeriod();

        if ($key === null || preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $key, $m) !== 1) {
            return $current;
        }

        $period = PeriodResolver::forMonth((int) $m[1], (int) $m[2], $this->tenant->get()->period_start_day);

        return $period->key() > $current->key() ? $current : $period;
    }

    public function periodData(Period $period): PeriodData
    {
        return PeriodData::fromPeriod($period, $this->currentPeriod());
    }

    public function summary(Period $period, TransactionFilterData $scope): DashboardSummaryData
    {
        return $this->rememberData('summary', $period, $scope, DashboardSummaryData::class, function () use ($period, $scope): DashboardSummaryData {
            $previous = $this->previous($period);
            $now = $this->transactions->totals($this->scoped($scope, $period));
            $before = $this->transactions->totals($this->scoped($scope, $previous));

            return new DashboardSummaryData(
                balance: StatData::compare($this->balance($scope, $period), $this->balance($scope, $previous)),
                income: StatData::compare($now['income'], $before['income']),
                expense: StatData::compare($now['expense'], $before['expense']),
                net: StatData::compare($now['income'] - $now['expense'], $before['income'] - $before['expense']),
            );
        });
    }

    /**
     * Income vs expense of the last CASHFLOW_PERIODS periods, ending with $period.
     *
     * @return list<CashflowPointData>
     */
    public function cashflow(Period $period, TransactionFilterData $scope): array
    {
        return $this->rememberList('cashflow', $period, $scope, CashflowPointData::class, function () use ($period, $scope): array {
            $periods = [$period];
            for ($i = 1; $i < self::CASHFLOW_PERIODS; $i++) {
                array_unshift($periods, $this->previous($periods[0]));
            }

            $daily = $this->transactions->dailyTotals($scope->between($periods[0]->start->toDateString(), $period->end->toDateString()));

            return array_map(function (Period $p) use ($daily): CashflowPointData {
                $income = $expense = 0;
                foreach ($daily as $date => $totals) {
                    if ($date >= $p->start->toDateString() && $date <= $p->end->toDateString()) {
                        $income += $totals['income'];
                        $expense += $totals['expense'];
                    }
                }

                $label = CarbonImmutable::create($p->year, $p->month, 1)->translatedFormat('M');

                return new CashflowPointData($p->key(), $label, $income, $expense, $income - $expense);
            }, $periods);
        });
    }

    /**
     * Expense per top-level category, sub-categories rolled into their parent (BR-E04).
     * The slices add up to the period's expense (INV-04).
     *
     * @return list<CategorySliceData>
     */
    public function categories(Period $period, TransactionFilterData $scope): array
    {
        return $this->rememberList('categories', $period, $scope, CategorySliceData::class, function () use ($period, $scope): array {
            $totals = $this->rollUp($this->transactions->sumByCategory($this->expenses($scope, $period)));
            $total = array_sum($totals);
            $roots = $this->rootCategories();

            arsort($totals);

            $slices = [];
            foreach ($totals as $id => $amount) {
                $category = $roots[$id] ?? null;

                $slices[] = new CategorySliceData(
                    categoryId: $id,
                    name: $category->name ?? 'Kategori terhapus',
                    color: $category?->color,
                    icon: $category?->icon,
                    amount: $amount,
                    formatted: MoneyFormatter::format($amount),
                    percentage: Percentage::of($amount, $total),
                );
            }

            return $slices;
        });
    }

    /**
     * Cumulative expense per day of the period against the previous period's daily average.
     *
     * @return list<TrendPointData>
     */
    public function trend(Period $period, TransactionFilterData $scope): array
    {
        return $this->rememberList('trend', $period, $scope, TrendPointData::class, function () use ($period, $scope): array {
            $previous = $this->previous($period);
            $daily = $this->transactions->dailyTotals($this->expenses($scope, $period));
            $previousExpense = $this->transactions->totals($this->scoped($scope, $previous))['expense'];
            $previousDays = (int) $previous->start->diffInDays($previous->end) + 1;
            $today = $this->today();

            $points = [];
            $cumulative = 0;
            $day = 0;
            for ($date = $period->start; $date->lessThanOrEqualTo($period->end); $date = $date->addDay()) {
                $day++;
                $cumulative += $daily[$date->toDateString()]['expense'] ?? 0;

                $points[] = new TrendPointData(
                    date: $date->toDateString(),
                    label: $date->translatedFormat('j M'),
                    cumulative: $date->greaterThan($today) ? null : $cumulative,
                    pace: intdiv($previousExpense * $day, $previousDays),
                );
            }

            return $points;
        });
    }

    /**
     * Expense per recorder; adds up to the period's expense (INV-05).
     *
     * @return list<MemberTotalData>
     */
    public function members(Period $period, TransactionFilterData $scope): array
    {
        return $this->rememberList('members', $period, $scope, MemberTotalData::class, function () use ($period, $scope): array {
            $sums = $this->transactions->sumByCreator($this->expenses($scope, $period));
            $names = $this->users->namesByIds(array_values(array_filter(array_keys($sums))));
            $total = array_sum(array_column($sums, 'amount'));

            uasort($sums, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

            $members = [];
            foreach ($sums as $userId => $sum) {
                $members[] = new MemberTotalData(
                    userId: $userId === 0 ? null : $userId,
                    name: $names[$userId] ?? 'Mantan anggota',
                    amount: $sum['amount'],
                    formatted: MoneyFormatter::format($sum['amount']),
                    count: $sum['count'],
                    percentage: Percentage::of($sum['amount'], $total),
                );
            }

            return $members;
        });
    }

    /**
     * Balance of each wallet at the end of the period (or today), BR-C01.
     *
     * @return list<WalletItemData>
     */
    public function walletBalances(Period $period, TransactionFilterData $scope): array
    {
        return $this->rememberList('wallets', $period, $scope, WalletItemData::class, function () use ($period, $scope): array {
            $wallets = $this->walletsInScope($scope);
            $balances = $this->wallets->balances($this->ids($wallets), $this->balanceDate($period));

            return array_values($wallets
                ->map(fn (Wallet $wallet): WalletItemData => WalletItemData::fromWallet($wallet, $balances[$wallet->id] ?? 0))
                ->all());
        });
    }

    /**
     * Newest transactions up to the end of the period. Not cached: the list is
     * cheap and carries per-user edit permissions.
     *
     * @return Collection<int, Transaction>
     */
    public function recent(Period $period, TransactionFilterData $scope): Collection
    {
        return $this->transactions->latestFiltered($scope->between(null, $period->end->toDateString()), self::RECENT_LIMIT);
    }

    /**
     * Short, plain-language observations about the period.
     *
     * @return list<string>
     */
    public function insights(Period $period, TransactionFilterData $scope): array
    {
        return $this->remember('insights', $period, $scope, function () use ($period, $scope): array {
            $insights = [];
            $summary = $this->summary($period, $scope);
            $isCurrent = $period->key() === $this->currentPeriod()->key();

            if ($summary->expense->change !== null && $summary->expense->amount > 0) {
                $insights[] = sprintf(
                    'Pengeluaran %s %s%% dibanding periode lalu.',
                    $summary->expense->change >= 0 ? 'naik' : 'turun',
                    $this->percent(abs($summary->expense->change)),
                );
            }

            if (($category = $this->biggestCategoryIncrease($period, $scope)) !== null) {
                $insights[] = sprintf('Pengeluaran %s naik %s%% dari periode lalu.', $category['name'], $this->percent($category['change']));
            }

            if ($isCurrent && $summary->expense->amount > 0) {
                $days = (int) $period->start->diffInDays($this->today()) + 1;
                $insights[] = 'Rata-rata pengeluaran harian: '.MoneyFormatter::format(intdiv($summary->expense->amount, $days)).'.';
            }

            return $insights;
        });
    }

    /**
     * Drop every cached widget of the family (BR-C04).
     */
    public function invalidate(int $familyId): void
    {
        Cache::forever($this->versionKey($familyId), Str::random(12));
    }

    /**
     * @return array{name: string, change: float}|null
     */
    private function biggestCategoryIncrease(Period $period, TransactionFilterData $scope): ?array
    {
        $now = $this->rollUp($this->transactions->sumByCategory($this->expenses($scope, $period)));
        $before = $this->rollUp($this->transactions->sumByCategory($this->expenses($scope, $this->previous($period))));
        $roots = $this->rootCategories();

        $best = null;
        foreach ($now as $id => $amount) {
            $previous = $before[$id] ?? 0;
            $change = Percentage::change($amount, $previous);

            if ($change === null || $change < self::INSIGHT_MIN_CHANGE || ! isset($roots[$id])) {
                continue;
            }

            if ($best === null || $amount - $previous > $best['increase']) {
                $best = ['name' => $roots[$id]->name, 'change' => $change, 'increase' => $amount - $previous];
            }
        }

        return $best === null ? null : ['name' => $best['name'], 'change' => $best['change']];
    }

    /**
     * Sums per category id → sums per top-level category id (BR-E04).
     *
     * @param  array<int, int>  $sums
     * @return array<int, int>
     */
    private function rollUp(array $sums): array
    {
        $parents = [];
        foreach ($this->rootCategories() as $root) {
            foreach ($root->children as $child) {
                $parents[$child->id] = $root->id;
            }
        }

        $totals = [];
        foreach ($sums as $id => $amount) {
            $rootId = $parents[$id] ?? $id;
            $totals[$rootId] = ($totals[$rootId] ?? 0) + $amount;
        }

        return $totals;
    }

    /**
     * Top-level expense categories (archived included) keyed by id, children loaded.
     *
     * @return array<int, Category>
     */
    private function rootCategories(): array
    {
        return $this->categories->tree(CategoryType::Expense, includeArchived: true)->keyBy('id')->all();
    }

    /**
     * Total balance at the end of the period (BR-C02), limited to the scoped wallets.
     */
    private function balance(TransactionFilterData $scope, Period $period): int
    {
        return array_sum($this->wallets->balances($this->ids($this->walletsInScope($scope)), $this->balanceDate($period)));
    }

    /**
     * @param  Collection<int, Wallet>  $wallets
     * @return list<int>
     */
    private function ids(Collection $wallets): array
    {
        return array_values($wallets->map(fn (Wallet $wallet): int => $wallet->id)->all());
    }

    /**
     * Non-archived wallets, or exactly the wallets picked in the filter.
     *
     * @return Collection<int, Wallet>
     */
    private function walletsInScope(TransactionFilterData $scope): Collection
    {
        if ($scope->walletIds === []) {
            return $this->wallets->listForFamily();
        }

        return $this->wallets->listForFamily(includeArchived: true)
            ->filter(fn (Wallet $wallet): bool => in_array($wallet->id, $scope->walletIds, true))
            ->values();
    }

    private function balanceDate(Period $period): string
    {
        return min($period->end, $this->today())->toDateString();
    }

    private function previous(Period $period): Period
    {
        $label = CarbonImmutable::create($period->year, $period->month, 1)->subMonthNoOverflow();

        return PeriodResolver::forMonth($label->year, $label->month, $this->tenant->get()->period_start_day);
    }

    private function scoped(TransactionFilterData $scope, Period $period): TransactionFilterData
    {
        return $scope->between($period->start->toDateString(), $period->end->toDateString());
    }

    private function expenses(TransactionFilterData $scope, Period $period): TransactionFilterData
    {
        $filter = $this->scoped($scope, $period);
        $filter->type = TransactionType::Expense;

        return $filter;
    }

    private function percent(float $value): string
    {
        return number_format($value, 1, ',', '.');
    }

    /**
     * Only plain arrays go into the cache: objects are not unserialized from it
     * (config cache.serializable_classes), so DTOs are rebuilt on the way out.
     *
     * @template T of Data
     *
     * @param  class-string<T>  $class
     * @param  Closure(): T  $callback
     * @return T
     */
    private function rememberData(string $widget, Period $period, TransactionFilterData $scope, string $class, Closure $callback): Data
    {
        return $class::from($this->remember($widget, $period, $scope, fn (): array => $callback()->toArray()));
    }

    /**
     * @template T of Data
     *
     * @param  class-string<T>  $class
     * @param  Closure(): list<T>  $callback
     * @return list<T>
     */
    private function rememberList(string $widget, Period $period, TransactionFilterData $scope, string $class, Closure $callback): array
    {
        $rows = $this->remember($widget, $period, $scope, fn (): array => array_map(fn (Data $item): array => $item->toArray(), $callback()));

        return array_map(fn (array $row): Data => $class::from($row), $rows);
    }

    /**
     * @template T of array
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function remember(string $widget, Period $period, TransactionFilterData $scope, Closure $callback): array
    {
        $familyId = $this->tenant->id();
        $version = Cache::rememberForever($this->versionKey($familyId), fn (): string => Str::random(12));
        // The date is part of the key: "today" moves the trend line and averages.
        $key = sprintf('family:%d:dashboard:%s:%s:%s:%s:%s', $familyId, $version, $widget, $period->key(), $this->today()->toDateString(), $scope->cacheKey());

        return Cache::remember($key, self::CACHE_TTL_SECONDS, $callback);
    }

    private function versionKey(int $familyId): string
    {
        return "family:{$familyId}:dashboard:version";
    }
}
