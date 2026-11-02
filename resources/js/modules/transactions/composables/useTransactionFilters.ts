import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import { index } from '@/routes/transactions';
import {
    advancedFilterCount,
    cleanFilters,
    normalizeFilters,
} from '../filters';
import type { TransactionFilters } from '../types';

/**
 * List filter <-> query string (docs/15 §5): every change is a GET visit, so
 * filters can be bookmarked and shared. Search is debounced by 300ms.
 */
export function useTransactionFilters(
    initial: Record<string, unknown>,
    perPage: () => number,
) {
    const filters = ref<TransactionFilters>(normalizeFilters(initial));
    const search = ref(filters.value.q ?? '');

    function visit() {
        const query = cleanFilters(filters.value);

        if (perPage() !== 20) {
            query.per_page = perPage();
        }

        router.get(index().url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function apply(patch: Partial<TransactionFilters>) {
        filters.value = { ...filters.value, ...patch };
        visit();
    }

    function reset() {
        filters.value = {};
        search.value = '';
        visit();
    }

    const applySearch = useDebounceFn(() => apply({ q: search.value }), 300);

    const isFiltered = computed(
        () =>
            Object.keys(cleanFilters({ ...filters.value, sort: undefined }))
                .length > 0,
    );

    return {
        filters,
        search,
        apply,
        applySearch,
        reset,
        isFiltered,
        advancedCount: computed(() => advancedFilterCount(filters.value)),
    };
}
