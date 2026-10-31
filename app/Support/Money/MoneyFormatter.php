<?php

declare(strict_types=1);

namespace App\Support\Money;

/**
 * Display formatting for whole-rupiah integers (BR-A06). Formatting only —
 * amounts are always stored and calculated as int.
 */
final class MoneyFormatter
{
    /**
     * 1250000 => "Rp 1.250.000", -50000 => "-Rp 50.000".
     */
    public static function format(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';

        return $sign.'Rp '.number_format(abs($amount), 0, ',', '.');
    }

    /**
     * Compact form for charts: 1250000 => "1,25 jt", 850000 => "850 rb", 2500000000 => "2,5 M".
     */
    public static function short(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $abs = abs($amount);

        [$divisor, $unit] = match (true) {
            $abs >= 1_000_000_000 => [1_000_000_000, ' M'],
            $abs >= 1_000_000 => [1_000_000, ' jt'],
            $abs >= 1_000 => [1_000, ' rb'],
            default => [1, ''],
        };

        if ($divisor === 1) {
            return $sign.(string) $abs;
        }

        // Two decimals, half up, trailing zeros removed: 1.250.000 => 1,25.
        $hundredths = intdiv($abs * 100 * 2 + $divisor, $divisor * 2);
        $whole = intdiv($hundredths, 100);
        $decimals = rtrim(str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT), '0');

        return $sign.$whole.($decimals !== '' ? ','.$decimals : '').$unit;
    }
}
