<?php

declare(strict_types=1);

namespace App\DTOs\Savings;

use App\Models\SavingGoal;
use App\Support\Money\MoneyFormatter;
use App\Support\Savings\SavingGoalCalculator;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A savings goal with its progress (BR-K01, BR-K03, BR-K04), formatted.
 */
#[TypeScript]
class SavingGoalItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $icon,
        public ?string $color,
        public int $targetAmount,
        public string $targetFormatted,
        public int $progress,
        public string $progressFormatted,
        public int $remaining,
        public string $remainingFormatted,
        public ?float $percentage,
        public bool $achieved,
        public ?int $walletId,
        public ?string $walletName,
        public ?string $deadline,
        public ?string $deadlineFormatted,
        public ?int $monthlyNeeded,
        public ?string $monthlyNeededFormatted,
    ) {}

    public static function fromGoal(SavingGoal $goal, int $progress, ?int $monthlyNeeded): self
    {
        $remaining = SavingGoalCalculator::remaining($goal->target_amount, $progress);

        return new self(
            id: $goal->id,
            name: $goal->name,
            icon: $goal->icon,
            color: $goal->color,
            targetAmount: $goal->target_amount,
            targetFormatted: MoneyFormatter::format($goal->target_amount),
            progress: $progress,
            progressFormatted: MoneyFormatter::format($progress),
            remaining: $remaining,
            remainingFormatted: MoneyFormatter::format($remaining),
            percentage: SavingGoalCalculator::percentage($goal->target_amount, $progress),
            achieved: SavingGoalCalculator::achieved($goal->target_amount, $progress),
            walletId: $goal->wallet_id,
            walletName: $goal->wallet?->name,
            deadline: $goal->deadline?->toDateString(),
            deadlineFormatted: $goal->deadline?->translatedFormat('j M Y'),
            monthlyNeeded: $monthlyNeeded,
            monthlyNeededFormatted: $monthlyNeeded !== null ? MoneyFormatter::format($monthlyNeeded) : null,
        );
    }
}
