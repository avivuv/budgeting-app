<?php

declare(strict_types=1);

namespace App\Support\Money;

/**
 * Parses Indonesian rupiah input into whole rupiah (BR-A03).
 *
 *   "50000", "50.000", "Rp 50.000"  => 50000
 *   "50rb", "50k", "50 ribu"        => 50000
 *   "1,5jt", "1.5 juta", "2jt300"   => 1500000, 1500000, 2300000
 *   "1m", "1 miliar"                => 1000000000
 *
 * Returns null when the input is not a valid amount (e.g. "50,5" — rupiah has no cents).
 */
final class AmountParser
{
    public const int MAX = 999_999_999_999;

    private const array MULTIPLIERS = [
        'rb' => 1_000,
        'ribu' => 1_000,
        'k' => 1_000,
        'jt' => 1_000_000,
        'juta' => 1_000_000,
        'm' => 1_000_000_000,
        'miliar' => 1_000_000_000,
        'milyar' => 1_000_000_000,
    ];

    public static function parse(?string $input): ?int
    {
        if ($input === null) {
            return null;
        }

        $value = strtolower(trim($input));
        $value = (string) preg_replace('/^rp\.?\s*/', '', $value);
        $value = (string) preg_replace('/\s+/', '', $value);

        if ($value === '') {
            return null;
        }

        $amount = preg_match('/^(\d+(?:[.,]\d+)*)(rb|ribu|k|jt|juta|m|miliar|milyar)(\d+)?$/', $value, $m)
            ? self::withSuffix($m[1], self::MULTIPLIERS[$m[2]], $m[3] ?? '')
            : self::plain($value);

        if ($amount === null || $amount <= 0 || $amount > self::MAX) {
            return null;
        }

        return $amount;
    }

    /**
     * "50.000", "1.250.000", "1.500.000,00" — dots/commas as thousand separators only.
     */
    private static function plain(string $value): ?int
    {
        $value = (string) preg_replace('/[.,]00$/', '', $value);

        if (preg_match('/^\d+$/', $value)) {
            return self::toInt($value);
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $value) || preg_match('/^\d{1,3}(,\d{3})+$/', $value)) {
            return self::toInt(str_replace(['.', ','], '', $value));
        }

        return null;
    }

    /**
     * "1,5" + jt => 1.500.000; "2" + jt + "300" => 2.300.000; "1.250" + rb => 1.250.000.
     */
    private static function withSuffix(string $number, int $multiplier, string $trailing): ?int
    {
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $number) && $trailing === '') {
            $whole = self::toInt(str_replace('.', '', $number));

            return $whole === null ? null : $whole * $multiplier;
        }

        if ($trailing !== '') {
            if (! ctype_digit($number)) {
                return null;
            }
            $integer = $number;
            $fraction = $trailing;
        } elseif (preg_match('/^(\d+)(?:[.,](\d+))?$/', $number, $parts)) {
            $integer = $parts[1];
            $fraction = $parts[2] ?? '';
        } else {
            return null;
        }

        $whole = self::toInt($integer);
        if ($whole === null) {
            return null;
        }

        $amount = $whole * $multiplier;

        if ($fraction !== '') {
            $scale = 10 ** strlen($fraction);
            $fractionValue = (int) $fraction * $multiplier;

            // Only whole rupiah are allowed (e.g. "1,2345rb" is rejected).
            if ($fractionValue % $scale !== 0) {
                return null;
            }

            $amount += intdiv($fractionValue, $scale);
        }

        return $amount;
    }

    private static function toInt(string $digits): ?int
    {
        $digits = ltrim($digits, '0') ?: '0';

        return strlen($digits) > 13 ? null : (int) $digits;
    }
}
