<script setup lang="ts">
import { computed } from 'vue';
import BaseChart from '@/components/charts/BaseChart.vue';
import { formatMoney, formatMoneyShort } from '@/lib/money';
import { cssVar } from '@/lib/theme';
import ReportTable from './ReportTable.vue';

const props = defineProps<{
    rows: App.DTOs.Report.CashflowRowData[];
}>();

const series = computed(() => [
    {
        name: 'Pemasukan',
        type: 'column',
        data: props.rows.map((r) => r.income.amount),
    },
    {
        name: 'Pengeluaran',
        type: 'column',
        data: props.rows.map((r) => r.expense.amount),
    },
    {
        name: 'Saldo akhir',
        type: 'line',
        data: props.rows.map((r) => r.closing.amount),
    },
]);

const options = computed(() => ({
    colors: [cssVar('income'), cssVar('expense'), cssVar('primary')],
    labels: props.rows.map((r) => r.label),
    stroke: { width: [0, 0, 3], curve: 'smooth' as const },
    plotOptions: { bar: { columnWidth: '50%', borderRadius: 4 } },
    dataLabels: { enabled: false },
    legend: { position: 'top' as const, horizontalAlign: 'left' as const },
    yaxis: { labels: { formatter: (v: number) => formatMoneyShort(v) } },
    tooltip: { shared: true, y: { formatter: (v: number) => formatMoney(v) } },
}));
</script>

<template>
    <div class="flex flex-col gap-4">
        <BaseChart
            v-if="rows.length > 1"
            type="line"
            :series="series"
            :options="options"
            label="Grafik arus kas per periode"
        />
        <ReportTable
            caption="Arus kas per periode"
            :headings="[
                'Periode',
                'Saldo awal',
                'Pemasukan',
                'Pengeluaran',
                'Selisih',
                'Saldo akhir',
            ]"
            :numeric="[1, 2, 3, 4, 5]"
        >
            <tr v-for="row in rows" :key="row.key">
                <td class="px-3 py-2 whitespace-nowrap">
                    {{ row.label }}
                    <span class="block text-xs text-muted-foreground"
                        >{{ row.start }} – {{ row.end }}</span
                    >
                </td>
                <td class="px-3 py-2 text-right">
                    {{ row.opening.formatted }}
                </td>
                <td class="px-3 py-2 text-right text-income">
                    +{{ row.income.formatted }}
                </td>
                <td class="px-3 py-2 text-right text-expense">
                    −{{ row.expense.formatted }}
                </td>
                <td class="px-3 py-2 text-right">{{ row.net.formatted }}</td>
                <td class="px-3 py-2 text-right font-semibold">
                    {{ row.closing.formatted }}
                </td>
            </tr>
        </ReportTable>
    </div>
</template>
