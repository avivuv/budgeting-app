/*
 * Display-only formatting of whole-rupiah integers, mirroring
 * App\Support\Money\MoneyFormatter (BR-A06). Amounts and totals always come
 * from the server; this only renders them (e.g. chart axes and tooltips).
 */

/** 1250000 => "Rp 1.250.000", -50000 => "-Rp 50.000". */
export function formatMoney(amount: number): string {
    const sign = amount < 0 ? '-' : '';

    return `${sign}Rp ${Math.abs(Math.trunc(amount))
        .toString()
        .replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
}

/** Compact form for charts: 1250000 => "1,25 jt", 850000 => "850 rb", 2500000000 => "2,5 M". */
export function formatMoneyShort(amount: number): string {
    const sign = amount < 0 ? '-' : '';
    const abs = Math.abs(Math.trunc(amount));

    const [divisor, unit] =
        abs >= 1_000_000_000
            ? [1_000_000_000, ' M']
            : abs >= 1_000_000
              ? [1_000_000, ' jt']
              : abs >= 1_000
                ? [1_000, ' rb']
                : [1, ''];

    if (divisor === 1) {
        return `${sign}${abs}`;
    }

    // Two decimals, half up, trailing zeros removed (same as the PHP formatter).
    const hundredths = Math.floor((abs * 100 * 2 + divisor) / (divisor * 2));
    const whole = Math.floor(hundredths / 100);
    const decimals = String(hundredths % 100)
        .padStart(2, '0')
        .replace(/0+$/, '');

    return `${sign}${whole}${decimals ? `,${decimals}` : ''}${unit}`;
}

/** 28.4 => "28,4%", null => "Baru" (BR-A05). */
export function formatPercent(value: number | null): string {
    return value === null ? 'Baru' : `${value.toFixed(1).replace('.', ',')}%`;
}
