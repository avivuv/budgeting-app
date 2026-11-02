<?php

declare(strict_types=1);

namespace App\Services\Budget;

use App\DTOs\Budget\BudgetItemData;
use App\DTOs\Transaction\TransactionFilterData;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Events\Budget\BudgetChanged;
use App\Exceptions\Budget\BudgetRuleException;
use App\Models\Budget;
use App\Models\Category;
use App\Notifications\BudgetThresholdNotification;
use App\Repositories\Contracts\BudgetRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\FamilyRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\Report\PeriodService;
use App\Services\Tenancy\TenantContext;
use App\Support\Budget\BudgetCalculator;
use App\Support\Math\Percentage;
use App\Support\Money\AmountParser;
use App\Support\Money\MoneyFormatter;
use App\Support\Period\Period;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Budgets per expense category and period (docs/11 §F). Shared by the web
 * page, the dashboard widget, the REST API and later the bot (/budget).
 */
class BudgetService
{
    public function __construct(
        private readonly BudgetRepositoryInterface $budgets,
        private readonly CategoryRepositoryInterface $categories,
        private readonly TransactionRepositoryInterface $transactions,
        private readonly FamilyRepositoryInterface $families,
        private readonly PeriodService $periods,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * Budgets of the period with their realisation, biggest budget first.
     *
     * @return list<BudgetItemData>
     */
    public function overview(Period $period): array
    {
        $spent = $this->spentPerCategory($period);
        $today = $this->periods->today();

        $items = [];
        foreach ($this->budgets->forPeriod($this->periodDate($period)) as $budget) {
            $items[] = BudgetItemData::fromBudget($budget, $spent[$budget->category_id] ?? 0, $period, $today);
        }

        return $items;
    }

    /**
     * Expense per category in the period: own transactions plus those of its
     * sub-categories (BR-F02, BR-E04). Keys exist for every category with spending.
     *
     * @return array<int, int>
     */
    public function spentPerCategory(Period $period): array
    {
        $direct = $this->transactions->sumByCategory(new TransactionFilterData(
            from: $period->start->toDateString(),
            to: $period->end->toDateString(),
            type: TransactionType::Expense,
        ));

        $spent = $direct;
        foreach ($this->categories->tree(CategoryType::Expense, includeArchived: true) as $parent) {
            foreach ($parent->children as $child) {
                $spent[$parent->id] = ($spent[$parent->id] ?? 0) + ($direct[$child->id] ?? 0);
            }
        }

        return $spent;
    }

    /**
     * Totals over the period's budgets. A sub-category budget is skipped when
     * its parent also has one, as the parent's spending already includes it.
     *
     * @param  list<BudgetItemData>  $items
     * @return array{amount: int, spent: int, remaining: int, percentage: float|null, status: string, amountFormatted: string, spentFormatted: string, remainingFormatted: string, dailyAllowanceFormatted: string}
     */
    public function totals(array $items, Period $period): array
    {
        $budgeted = array_column($items, 'categoryId');
        $parents = [];
        foreach ($this->categories->tree(CategoryType::Expense, includeArchived: true) as $parent) {
            foreach ($parent->children as $child) {
                $parents[$child->id] = $parent->id;
            }
        }

        $amount = $spent = 0;
        foreach ($items as $item) {
            if (in_array($parents[$item->categoryId] ?? null, $budgeted, true)) {
                continue;
            }

            $amount += $item->amount;
            $spent += $item->spent;
        }

        $remaining = BudgetCalculator::remaining($amount, $spent);

        return [
            'amount' => $amount,
            'spent' => $spent,
            'remaining' => $remaining,
            'percentage' => BudgetCalculator::percentage($amount, $spent),
            'status' => BudgetCalculator::status($amount, $spent)->value,
            'amountFormatted' => MoneyFormatter::format($amount),
            'spentFormatted' => MoneyFormatter::format($spent),
            'remainingFormatted' => MoneyFormatter::format($remaining),
            'dailyAllowanceFormatted' => MoneyFormatter::format(BudgetCalculator::dailyAllowance($amount, $spent, $period, $this->periods->today())),
        ];
    }

    /**
     * Budgets can be planned for the current period and the one after it.
     */
    public function plannablePeriod(?string $key): Period
    {
        $next = $this->periods->next($this->periods->current());
        $period = $this->periods->fromKey($key, allowFuture: true);

        return $period->key() > $next->key() ? $next : $period;
    }

    public function find(int $id): Budget
    {
        return $this->budgets->findOrFail($id);
    }

    /**
     * Create or change the budget of a category for a period (BR-F01, unique per
     * category and period). Thresholds are re-evaluated right away (BR-F05).
     */
    public function set(int $categoryId, Period $period, int $amount): Budget
    {
        if ($amount <= 0 || $amount > AmountParser::MAX) {
            throw BudgetRuleException::amountOutOfRange();
        }

        // Tenant-scoped: a category of another family is "not found" (BR-I06).
        $category = $this->categories->findOrFail($categoryId);

        if ($category->type !== CategoryType::Expense) {
            throw BudgetRuleException::expenseCategoryOnly();
        }

        $date = $this->periodDate($period);
        $existing = $this->budgets->findForCategory($category->id, $date);

        $budget = $existing !== null
            ? $this->budgets->update($existing, ['amount' => $amount])
            : $this->budgets->create(['category_id' => $category->id, 'period' => $date, 'amount' => $amount]);

        $this->checkThresholds($period);
        BudgetChanged::dispatch($this->tenant->id());

        return $budget;
    }

    public function delete(Budget $budget): void
    {
        $this->budgets->delete($budget);
        BudgetChanged::dispatch($this->tenant->id());
    }

    /**
     * BR-F07: copy last period's budgets, only for categories without a budget yet.
     * Returns the number of budgets created.
     */
    public function copyFromPrevious(Period $period): int
    {
        $target = $this->periodDate($period);
        $taken = $this->budgets->forPeriod($target)->pluck('category_id')->all();
        $source = $this->budgets->forPeriod($this->periodDate($this->periods->previous($period)));

        $created = DB::transaction(function () use ($source, $taken, $target): int {
            $count = 0;
            foreach ($source as $budget) {
                if (in_array($budget->category_id, $taken, true) || $budget->category?->is_archived) {
                    continue;
                }

                $this->budgets->create(['category_id' => $budget->category_id, 'period' => $target, 'amount' => $budget->amount]);
                $count++;
            }

            return $count;
        });

        if ($created > 0) {
            $this->checkThresholds($period);
            BudgetChanged::dispatch($this->tenant->id());
        }

        return $created;
    }

    /**
     * BR-F05: notify every member once when a budget reaches 80% and once at
     * 100% in a period. A flag is cleared when spending drops below its
     * threshold again (deleted/edited transaction), so it can fire again.
     * Returns the number of notifications sent.
     */
    public function checkThresholds(Period $period): int
    {
        $spent = $this->spentPerCategory($period);
        $sent = 0;

        foreach ($this->budgets->forPeriod($this->periodDate($period)) as $budget) {
            $amountSpent = $spent[$budget->category_id] ?? 0;
            $over = BudgetCalculator::reached($budget->amount, $amountSpent, BudgetCalculator::OVER_PERCENT);
            $warning = BudgetCalculator::reached($budget->amount, $amountSpent, BudgetCalculator::WARNING_PERCENT);

            $changes = [];
            $notifyAt = null;

            if ($over && $budget->alert_100_sent_at === null) {
                $changes['alert_100_sent_at'] = now();
                $notifyAt = BudgetCalculator::OVER_PERCENT;
            } elseif (! $over && $budget->alert_100_sent_at !== null) {
                $changes['alert_100_sent_at'] = null;
            }

            if ($warning && $budget->alert_80_sent_at === null) {
                // Jumping straight past 100% sends only the 100% alert.
                $changes['alert_80_sent_at'] = now();
                $notifyAt ??= BudgetCalculator::WARNING_PERCENT;
            } elseif (! $warning && $budget->alert_80_sent_at !== null) {
                $changes['alert_80_sent_at'] = null;
            }

            if ($changes !== []) {
                $this->budgets->update($budget, $changes);
            }

            if ($notifyAt !== null) {
                $this->notify($budget, $period, $notifyAt, $amountSpent);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Expense categories (active, with sub-categories) that have no budget yet.
     *
     * @return list<array{id: int, name: string, parentName: string|null}>
     */
    public function categoriesWithoutBudget(Period $period): array
    {
        $taken = $this->budgets->forPeriod($this->periodDate($period))->pluck('category_id')->all();
        $free = [];

        foreach ($this->categories->tree(CategoryType::Expense) as $parent) {
            foreach ([$parent, ...$parent->children] as $category) {
                /** @var Category $category */
                if (! in_array($category->id, $taken, true)) {
                    $free[] = [
                        'id' => $category->id,
                        'name' => $category->name,
                        'parentName' => $category->parent_id !== null ? $parent->name : null,
                    ];
                }
            }
        }

        return $free;
    }

    public function periodDate(Period $period): string
    {
        return $this->periods->labelMonth($period)->toDateString();
    }

    private function notify(Budget $budget, Period $period, int $threshold, int $spent): void
    {
        $family = $this->tenant->get();

        Notification::send($this->families->members($family), new BudgetThresholdNotification(
            familyId: $family->id,
            familyName: $family->name,
            budgetId: $budget->id,
            categoryName: $budget->category->name ?? 'Kategori',
            periodKey: $period->key(),
            periodLabel: $this->periods->labelMonth($period)->translatedFormat('F Y'),
            threshold: $threshold,
            spentFormatted: MoneyFormatter::format($spent),
            amountFormatted: MoneyFormatter::format($budget->amount),
            percentageText: number_format(Percentage::of($spent, $budget->amount) ?? 0, 1, ',', '.').'%',
        ));
    }
}
