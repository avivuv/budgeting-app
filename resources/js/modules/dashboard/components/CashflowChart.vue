<script setup lang="ts">
import { computed } from 'vue';
import BaseChart from '@/components/charts/BaseChart.vue';
import { formatMoney, formatMoneyShort } from '@/lib/money';
import { cssVar } from '@/lib/theme';
import EmptyChart from './EmptyChart.vue';

const props = defineProps<{
    points: App.DTOs.Dashboard.CashflowPointData[];
}>();

const isEmpty = computed(() =>
    props.points.every((p) => p.income === 0 && p.expense === 0),
);

const series = computed(() => [
    {
        name: 'Pemasukan',
        type: 'column',
        data: props.points.map((p) => p.income),
    },
    {
        name: 'Pengeluaran',
        type: 'column',
        data: props.points.map((p) => p.expense),
    },
    { name: 'Selisih', type: 'line', data: props.points.map((p) => p.net) },
]);

// Income green, expense red, net line primary (docs/14 §1.4).
const options = computed(() => ({
    colors: [cssVar('income'), cssVar('expense'), cssVar('primary')],
    labels: props.points.map((p) => p.label),
    stroke: { width: [0, 0, 3], curve: 'smooth' as const },
    plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
    dataLabels: { enabled: false },
    legend: { position: 'top' as const, horizontalAlign: 'left' as const },
    yaxis: { labels: { formatter: (v: number) => formatMoneyShort(v) } },
    tooltip: { shared: true, y: { formatter: (v: number) => formatMoney(v) } },
}));
</script>

<template>
    <EmptyChart v-if="isEmpty" />
    <template v-else>
        <BaseChart
            type="line"
            :series="series"
            :options="options"
            label="Grafik arus kas: pemasukan, pengeluaran dan selisih per periode"
        />
        <details class="mt-2 text-sm">
            <summary class="cursor-pointer text-xs text-muted-foreground">
                Lihat sebagai tabel
            </summary>
            <table class="mt-2 w-full text-xs tabular-nums">
                <thead class="text-muted-foreground">
                    <tr>
                        <th class="py-1 text-left font-medium">Periode</th>
                        <th class="py-1 text-right font-medium">Masuk</th>
                        <th class="py-1 text-right font-medium">Keluar</th>
                        <th class="py-1 text-right font-medium">Selisih</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in points" :key="p.key" class="border-t">
                        <td class="py-1">{{ p.label }}</td>
                        <td class="py-1 text-right">
                            {{ formatMoney(p.income) }}
                        </td>
                        <td class="py-1 text-right">
                            {{ formatMoney(p.expense) }}
                        </td>
                        <td class="py-1 text-right">
                            {{ formatMoney(p.net) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </details>
    </template>
</template>
