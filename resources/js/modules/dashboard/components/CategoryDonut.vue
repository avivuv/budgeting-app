<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import BaseChart from '@/components/charts/BaseChart.vue';
import { formatMoney, formatPercent } from '@/lib/money';
import { chartPalette } from '@/lib/theme';
import EmptyChart from './EmptyChart.vue';

type Slice = App.DTOs.Dashboard.CategorySliceData;

const props = defineProps<{
    slices: Slice[];
    /** Drill-down to the transactions of a category. */
    hrefFor: (slice: Slice) => NonNullable<InertiaLinkProps['href']>;
}>();

// The category's own colour wins; otherwise the palette, in order (docs/14 §1.4).
const colors = computed(() => {
    const palette = chartPalette();

    return props.slices.map((s, i) => s.color ?? palette[i % palette.length]);
});

const options = computed(() => ({
    labels: props.slices.map((s) => s.name),
    colors: colors.value,
    legend: { show: false },
    dataLabels: { enabled: false },
    stroke: { width: 0 },
    plotOptions: { pie: { donut: { size: '68%' } } },
    tooltip: { y: { formatter: (v: number) => formatMoney(v) } },
}));
</script>

<template>
    <EmptyChart v-if="slices.length === 0" text="Belum ada pengeluaran." />
    <div v-else class="grid items-center gap-4 sm:grid-cols-[200px_1fr]">
        <BaseChart
            type="donut"
            :series="slices.map((s) => s.amount)"
            :options="options"
            :height="200"
            label="Grafik donat pengeluaran per kategori"
        />
        <ul class="flex flex-col gap-1">
            <li v-for="(slice, i) in slices" :key="slice.categoryId">
                <Link
                    :href="hrefFor(slice)"
                    class="flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm hover:bg-muted"
                >
                    <span
                        class="size-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: colors[i] }"
                    />
                    <span class="min-w-0 flex-1 truncate">{{
                        slice.name
                    }}</span>
                    <span class="tabular-nums">{{ slice.formatted }}</span>
                    <span
                        class="w-12 text-right text-xs text-muted-foreground tabular-nums"
                    >
                        {{ formatPercent(slice.percentage) }}
                    </span>
                </Link>
            </li>
        </ul>
    </div>
</template>
