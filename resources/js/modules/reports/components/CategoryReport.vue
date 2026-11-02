<script setup lang="ts">
import { computed } from 'vue';
import BaseChart from '@/components/charts/BaseChart.vue';
import { formatMoney, formatPercent } from '@/lib/money';
import { chartPalette } from '@/lib/theme';
import ReportTable from './ReportTable.vue';

type Row = App.DTOs.Report.CategoryRowData;

const props = defineProps<{
    rows: Row[];
}>();

const colors = computed(() => {
    const palette = chartPalette();

    return props.rows.map((r, i) => r.color ?? palette[i % palette.length]);
});

const options = computed(() => ({
    labels: props.rows.map((r) => r.name),
    colors: colors.value,
    legend: { position: 'bottom' as const },
    dataLabels: { enabled: false },
    stroke: { width: 0 },
    plotOptions: { pie: { donut: { size: '65%' } } },
    tooltip: { y: { formatter: (v: number) => formatMoney(v) } },
}));

function changeClass(change: number | null): string {
    if (change === null || change === 0) {
        return 'text-muted-foreground';
    }

    return change > 0 ? 'text-expense' : 'text-income';
}
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
        <BaseChart
            v-if="rows.length"
            type="donut"
            :series="rows.map((r) => r.amount.amount)"
            :options="options"
            :height="280"
            label="Grafik donat per kategori"
        />
        <ReportTable
            caption="Total per kategori"
            :headings="[
                'Kategori',
                'Nominal',
                'Porsi',
                'Periode lalu',
                'Perubahan',
                'Transaksi',
            ]"
            :numeric="[1, 2, 3, 4, 5]"
        >
            <template v-for="row in rows" :key="row.id">
                <tr>
                    <td class="px-3 py-2 font-medium">{{ row.name }}</td>
                    <td class="px-3 py-2 text-right">
                        {{ row.amount.formatted }}
                    </td>
                    <td class="px-3 py-2 text-right">
                        {{ formatPercent(row.share) }}
                    </td>
                    <td class="px-3 py-2 text-right text-muted-foreground">
                        {{ row.previous.formatted }}
                    </td>
                    <td
                        class="px-3 py-2 text-right"
                        :class="changeClass(row.change)"
                    >
                        {{ formatPercent(row.change) }}
                    </td>
                    <td class="px-3 py-2 text-right">{{ row.count }}</td>
                </tr>
                <tr
                    v-for="child in row.children"
                    :key="child.id"
                    class="text-muted-foreground"
                >
                    <td class="py-1.5 pr-3 pl-7">↳ {{ child.name }}</td>
                    <td class="px-3 py-1.5 text-right">
                        {{ child.amount.formatted }}
                    </td>
                    <td class="px-3 py-1.5 text-right">
                        {{ formatPercent(child.share) }}
                    </td>
                    <td class="px-3 py-1.5 text-right">
                        {{ child.previous.formatted }}
                    </td>
                    <td class="px-3 py-1.5 text-right">
                        {{ formatPercent(child.change) }}
                    </td>
                    <td class="px-3 py-1.5 text-right">{{ child.count }}</td>
                </tr>
            </template>
        </ReportTable>
    </div>
</template>
