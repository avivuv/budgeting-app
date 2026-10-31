<?php

declare(strict_types=1);

namespace App\Support\Math;

/**
 * Integer-based percentages rounded to one decimal, half up (BR-A04, BR-A05).
 */
final class Percentage
{
    /**
     * part / whole × 100. Returns null when whole is 0.
     */
    public static function of(int $part, int $whole): ?float
    {
        if ($whole === 0) {
            return null;
        }

        return self::roundTenths($part * 1000, $whole);
    }

    /**
     * (current − previous) / previous × 100. Returns null ("Baru") when previous is 0.
     */
    public static function change(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return self::roundTenths(($current - $previous) * 1000, abs($previous));
    }

    /**
     * numerator / denominator, already scaled to tenths, rounded half away from zero.
     */
    private static function roundTenths(int $numerator, int $denominator): float
    {
        if ($denominator < 0) {
            $numerator = -$numerator;
            $denominator = -$denominator;
        }

        $sign = $numerator < 0 ? -1 : 1;
        $tenths = intdiv(abs($numerator) * 2 + $denominator, $denominator * 2);

        return $sign * $tenths / 10;
    }
}
