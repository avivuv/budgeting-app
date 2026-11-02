<?php

declare(strict_types=1);

namespace App\Support\Budget;

use App\Enums\BudgetStatus;
use App\Support\Math\Percentage;
use App\Support\Period\Period;
use Carbon\CarbonImmutable;

/**
 * Pure budget maths (BR-F03, BR-F04, BR-F06). Thresholds are compared on
 * integers, so 79,95% is still "safe" even though it displays as 80,0%.
 */
final class BudgetCalculator
{
    public const int WARNING_PERCENT = 80;

    public const int OVER_PERCENT = 100;

    /** BR-F03: may be negative. */
    public static function remaining(int $budget, int $spent): int
    {
        return $budget - $spent;
    }

    /** BR-F03: spent / budget × 100, one decimal (BR-A04). */
    public static function percentage(int $budget, int $spent): ?float
    {
        return Percentage::of($spent, $budget);
    }

    public static function reached(int $budget, int $spent, int $percent): bool
    {
        return $budget > 0 && $spent * 100 >= $budget * $percent;
    }

    /** BR-F04 */
    public static function status(int $budget, int $spent): BudgetStatus
    {
        return match (true) {
            self::reached($budget, $spent, self::OVER_PERCENT) => BudgetStatus::Over,
            self::reached($budget, $spent, self::WARNING_PERCENT) => BudgetStatus::Warning,
            default => BudgetStatus::Safe,
        };
    }

    /**
     * BR-F06: max(remaining, 0) / days left in the period including today,
     * rounded down. 0 once the period is over.
     */
    public static function dailyAllowance(int $budget, int $spent, Period $period, CarbonImmutable $today): int
    {
        $days = $period->daysRemaining($today);

        return $days === 0 ? 0 : intdiv(max(self::remaining($budget, $spent), 0), $days);
    }
}
