<?php

declare(strict_types=1);

namespace App\Support\Receipt;

use Carbon\CarbonImmutable;

/**
 * Sanity checks on what the AI read (docs/06 §5.6).
 */
final class ReceiptChecker
{
    /** Confidence is multiplied by this when the total does not match the items. */
    public const float MISMATCH_PENALTY = 0.5;

    /**
     * BR-H03: Σ items − discount + tax differs from the total by more than the
     * tolerance (2%). Without items there is nothing to compare.
     *
     * @param  list<int>  $itemTotals
     */
    public static function totalMismatch(array $itemTotals, int $discount, int $tax, int $total, float $tolerance = 0.02): bool
    {
        if ($itemTotals === [] || $total <= 0) {
            return false;
        }

        $expected = array_sum($itemTotals) - $discount + $tax;

        return abs($expected - $total) > $total * $tolerance;
    }

    public static function lowerConfidence(?float $confidence): float
    {
        return round(($confidence ?? 1.0) * self::MISMATCH_PENALTY, 2);
    }

    /**
     * BR-H06: an unreadable date, a date in the future or more than one year
     * back is replaced by today. Returns the date to use and whether it was replaced.
     *
     * @return array{0: CarbonImmutable, 1: bool}
     */
    public static function date(?string $read, CarbonImmutable $today): array
    {
        $today = $today->startOfDay();

        if ($read === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $read) !== 1) {
            return [$today, true];
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $read, $today->getTimezone());

        if ($date === null || $date->format('Y-m-d') !== $read || $date->greaterThan($today) || $date->lessThan($today->subYear())) {
            return [$today, true];
        }

        return [$date, false];
    }
}
