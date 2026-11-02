import type { TransactionFilters } from './types';

const ID_LISTS = ['category_id', 'wallet_id', 'user_id'] as const;

/**
 * Query values arrive as strings ("3", ["1","2"]); turn them into the typed
 * filter the UI works with.
 */
export function normalizeFilters(
    raw: Record<string, unknown>,
): TransactionFilters {
    const filters: Record<string, unknown> = { ...raw };

    for (const key of ID_LISTS) {
        if (raw[key] !== undefined) {
            filters[key] = ([] as unknown[])
                .concat(raw[key])
                .map(Number)
                .filter((id) => Number.isInteger(id) && id > 0);
        }
    }

    if (raw.tag !== undefined) {
        filters.tag = ([] as unknown[]).concat(raw.tag).map(String);
    }

    for (const key of ['min', 'max'] as const) {
        if (raw[key] !== undefined && raw[key] !== null && raw[key] !== '') {
            filters[key] = Number(raw[key]);
        }
    }

    return filters as TransactionFilters;
}

/** Drops empty values so the URL only carries what is actually filtered. */
export function cleanFilters(
    filters: TransactionFilters,
): Record<string, string | number | (string | number)[]> {
    const query: Record<string, string | number | (string | number)[]> = {};

    for (const [key, value] of Object.entries(filters)) {
        const empty =
            value === undefined ||
            value === null ||
            value === '' ||
            (Array.isArray(value) && value.length === 0);

        if (!empty) {
            query[key] = value as string | number | (string | number)[];
        }
    }

    return query;
}

/** Number of filters hidden behind the "Filter" sheet (for its badge). */
export function advancedFilterCount(filters: TransactionFilters): number {
    return [
        filters.category_id?.length,
        filters.wallet_id?.length,
        filters.user_id?.length,
        filters.tag?.length,
        filters.source,
        filters.min,
        filters.max,
    ].filter((value) => value !== undefined && value !== 0 && value !== '')
        .length;
}
