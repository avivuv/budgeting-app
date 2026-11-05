import { useQuery } from '@tanstack/vue-query';
import { computed } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import { toValue } from 'vue';
import { api } from '@/core/api';
import { useSession } from '@/core/stores/session';

/*
 * Shapes from docs/api-contracts/reports.md. The generated schema cannot type
 * these yet (Scramble sees the snake_case conversion as a string), so they
 * are written here until the report resources are typed (M4, dashboard).
 */
export type Stat = {
    amount: number;
    formatted: string;
    previous: number;
    change: number | null;
};

export type Period = {
    key: string;
    label: string;
    range: string;
    start: string;
    end: string;
    previous_key: string;
    next_key: string | null;
    is_current: boolean;
};

export type Summary = {
    period: Period;
    summary: { balance: Stat; income: Stat; expense: Stat; net: Stat };
};

/** GET /summary for a period key ("2026-10"), or the current period. */
export function useSummary(period: MaybeRefOrGetter<string | null>) {
    const session = useSession();

    return useQuery({
        queryKey: computed(() => [
            'summary',
            session.currentFamilyId,
            toValue(period),
        ]),
        // Not while signing out or before a family exists.
        enabled: computed(() => session.currentFamilyId !== null),
        queryFn: () => {
            const key = toValue(period);

            return api
                .get<{ data: Summary }>('/summary', {
                    query: key ? { period: key } : {},
                })
                .then((r) => r.data);
        },
    });
}
