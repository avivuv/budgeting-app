import { describe, expect, it } from 'vite-plus/test';
import {
    advancedFilterCount,
    cleanFilters,
    normalizeFilters,
} from '../filters';

describe('transaction list filters', () => {
    it('turns query strings into typed values', () => {
        expect(
            normalizeFilters({
                category_id: ['3', '4'],
                wallet_id: '7',
                user_id: ['x'],
                tag: 'liburan',
                min: '50000',
                q: 'bensin',
            }),
        ).toEqual({
            category_id: [3, 4],
            wallet_id: [7],
            user_id: [],
            tag: ['liburan'],
            min: 50000,
            q: 'bensin',
        });
    });

    it('keeps only filled values in the query string', () => {
        expect(
            cleanFilters({
                q: '',
                type: 'expense',
                category_id: [],
                wallet_id: [2],
                min: undefined,
            }),
        ).toEqual({ type: 'expense', wallet_id: [2] });
    });

    it('counts the filters inside the filter sheet', () => {
        expect(advancedFilterCount({ q: 'x', type: 'income' })).toBe(0);
        expect(
            advancedFilterCount({ category_id: [1, 2], source: 'web', min: 0 }),
        ).toBe(2);
    });
});
