import { describe, expect, it } from 'vite-plus/test';
import { formatMoney, formatMoneyShort, formatPercent } from '@/lib/money';

describe('money formatting (mirrors MoneyFormatter, BR-A06)', () => {
    it.each([
        [1_250_000, 'Rp 1.250.000'],
        [0, 'Rp 0'],
        [999, 'Rp 999'],
        [-50_000, '-Rp 50.000'],
    ])('formats %d as %s', (amount, expected) => {
        expect(formatMoney(amount)).toBe(expected);
    });

    it.each([
        [1_250_000, '1,25 jt'],
        [850_000, '850 rb'],
        [2_500_000_000, '2,5 M'],
        [1_005_000, '1,01 jt'],
        [999, '999'],
        [-1_500_000, '-1,5 jt'],
    ])('shortens %d to %s', (amount, expected) => {
        expect(formatMoneyShort(amount)).toBe(expected);
    });

    it('shows a missing comparison as "Baru" (BR-A05)', () => {
        expect(formatPercent(null)).toBe('Baru');
        expect(formatPercent(28.4)).toBe('28,4%');
        expect(formatPercent(-5)).toBe('-5,0%');
    });
});
