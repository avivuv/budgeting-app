<?php

declare(strict_types=1);

namespace App\Support\Receipt;

/**
 * BR-H02 "split per item": the paid total is shared out over the item
 * categories in proportion to their subtotals, so discount and tax are
 * spread proportionally. Rounding leftovers go to the largest subtotal and
 * the parts always add up to the total exactly (INV-09).
 */
final class ReceiptSplitter
{
    /**
     * @param  array<int, int>  $subtotals  category id => Σ item totals
     * @param  int  $fallbackKey  category used when no subtotal is positive
     * @return array<int, int> category id => amount, every amount > 0
     */
    public static function split(array $subtotals, int $total, int $fallbackKey): array
    {
        if ($total <= 0) {
            return [];
        }

        // Discount lines may make a group zero or negative; it then gets nothing.
        $positive = array_filter($subtotals, fn (int $subtotal): bool => $subtotal > 0);
        $sum = array_sum($positive);

        if ($sum <= 0) {
            return [$fallbackKey => $total];
        }

        $parts = [];
        foreach ($positive as $key => $subtotal) {
            // Exact integer maths; total × subtotal may exceed 64 bits.
            $parts[$key] = (int) bcdiv(bcmul((string) $total, (string) $subtotal), (string) $sum, 0);
        }

        $largest = self::largestKey($positive);
        $parts[$largest] += $total - array_sum($parts);

        // A tiny category can round down to 0: fold it into the largest one.
        foreach ($parts as $key => $amount) {
            if ($amount <= 0 && $key !== $largest) {
                $parts[$largest] += $amount;
                unset($parts[$key]);
            }
        }

        return $parts;
    }

    /**
     * Largest subtotal; ties go to the first key so the result is stable.
     *
     * @param  array<int, int>  $subtotals
     */
    private static function largestKey(array $subtotals): int
    {
        $largest = 0;
        $largestSubtotal = PHP_INT_MIN;

        foreach ($subtotals as $key => $subtotal) {
            if ($subtotal > $largestSubtotal) {
                $largest = $key;
                $largestSubtotal = $subtotal;
            }
        }

        return $largest;
    }
}
