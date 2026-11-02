<?php

declare(strict_types=1);

namespace App\Support\Savings;

use App\Support\Math\Percentage;
use App\Support\Period\Period;

/**
 * Pure savings-goal maths (BR-K03, BR-K04).
 */
final class SavingGoalCalculator
{
    public static function remaining(int $target, int $progress): int
    {
        return max($target - $progress, 0);
    }

    public static function percentage(int $target, int $progress): ?float
    {
        return Percentage::of(max($progress, 0), $target);
    }

    public static function achieved(int $target, int $progress): bool
    {
        return $target > 0 && $progress >= $target;
    }

    /**
     * BR-K04: remaining / periods left up to the deadline's period (including
     * the current one), rounded up. Null without deadline, after it, or when done.
     */
    public static function monthlyNeeded(int $target, int $progress, Period $current, ?Period $deadline): ?int
    {
        $remaining = self::remaining($target, $progress);

        if ($remaining === 0 || $deadline === null || $deadline->key() < $current->key()) {
            return null;
        }

        $periods = ($deadline->year - $current->year) * 12 + ($deadline->month - $current->month) + 1;

        return intdiv($remaining + $periods - 1, $periods);
    }
}
