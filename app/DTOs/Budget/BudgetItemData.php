<?php

declare(strict_types=1);

namespace App\DTOs\Budget;

use App\Enums\BudgetStatus;
use App\Models\Budget;
use App\Support\Budget\BudgetCalculator;
use App\Support\Money\MoneyFormatter;
use App\Support\Period\Period;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A budget with its realisation (BR-F02..F04, BR-F06), formatted for display.
 */
#[TypeScript]
class BudgetItemData extends Data
{
    public function __construct(
        public int $id,
        public int $categoryId,
        public string $categoryName,
        public ?string $categoryIcon,
        public ?string $categoryColor,
        public bool $isSubCategory,
        public int $amount,
        public string $amountFormatted,
        public string $amountShort,
        public int $spent,
        public string $spentFormatted,
        public string $spentShort,
        public int $remaining,
        public string $remainingFormatted,
        public ?float $percentage,
        public BudgetStatus $status,
        public string $statusLabel,
        public int $dailyAllowance,
        public string $dailyAllowanceFormatted,
    ) {}

    public static function fromBudget(Budget $budget, int $spent, Period $period, CarbonImmutable $today): self
    {
        $remaining = BudgetCalculator::remaining($budget->amount, $spent);
        $status = BudgetCalculator::status($budget->amount, $spent);
        $daily = BudgetCalculator::dailyAllowance($budget->amount, $spent, $period, $today);

        return new self(
            id: $budget->id,
            categoryId: $budget->category_id,
            categoryName: $budget->category->name ?? 'Kategori terhapus',
            categoryIcon: $budget->category?->icon,
            categoryColor: $budget->category?->color,
            isSubCategory: $budget->category?->parent_id !== null,
            amount: $budget->amount,
            amountFormatted: MoneyFormatter::format($budget->amount),
            amountShort: MoneyFormatter::short($budget->amount),
            spent: $spent,
            spentFormatted: MoneyFormatter::format($spent),
            spentShort: MoneyFormatter::short($spent),
            remaining: $remaining,
            remainingFormatted: MoneyFormatter::format($remaining),
            percentage: BudgetCalculator::percentage($budget->amount, $spent),
            status: $status,
            statusLabel: $status->label(),
            dailyAllowance: $daily,
            dailyAllowanceFormatted: MoneyFormatter::format($daily),
        );
    }
}
