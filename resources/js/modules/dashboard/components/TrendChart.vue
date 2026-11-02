<script setup lang="ts">
import { computed } from 'vue';
import BaseChart from '@/components/charts/BaseChart.vue';
import { formatMoney, formatMoneyShort } from '@/lib/money';
import { cssVar } from '@/lib/theme';
import EmptyChart from './EmptyChart.vue';

const props = defineProps<{
    points: App.DTOs.Dashboard.TrendPointData[];
}>();

const isEmpty = computed(() =>
    props.points.every((p) => !p.cumulative && p.pace === 0),
);

const series = computed(() => [
    {
        name: 'Pengeluaran kumulatif',
        type: 'area',
        data: props.points.map((p) => p.cumulative),
    },
    {
        name: 'Laju rata-rata periode lalu',
        type: 'line',
        data: props.points.map((p) => p.pace),
    },
]);

const options = computed(() => ({
    colors: [cssVar('expense'), cssVar('muted-foreground')],
    labels: props.points.map((p) => p.label),
    stroke: { width: [2, 2], curve: 'smooth' as const, dashArray: [0, 5] },
    fill: {
        type: ['gradient', 'solid'],
        gradient: { opacityFrom: 0.35, opacityTo: 0.05 },
    },
    dataLabels: { enabled: false },
    legend: { position: 'top' as const, horizontalAlign: 'left' as const },
    xaxis: { tickAmount: 6, labels: { rotate: 0 } },
    yaxis: { labels: { formatter: (v: number) => formatMoneyShort(v) } },
    tooltip: {
        shared: true,
        y: {
            formatter: (v: number | null) =>
                v === null ? 'belum ada' : formatMoney(v),
        },
    },
}));
</script>

<template>
    <EmptyChart v-if="isEmpty" />
    <BaseChart
        v-else
        type="line"
        :series="series"
        :options="options"
        :height="240"
        label="Grafik tren pengeluaran kumulatif harian dibanding rata-rata periode lalu"
    />
</template>
