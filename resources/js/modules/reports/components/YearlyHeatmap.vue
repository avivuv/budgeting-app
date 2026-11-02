<script setup lang="ts">
import { computed } from 'vue';
import { formatMoneyShort } from '@/lib/money';
import ReportTable from './ReportTable.vue';

/*
 * 12 periods × category. Cell shade = share of the biggest cell (display
 * only); exact numbers are in the cells and the export.
 */
const props = defineProps<{
    report: App.DTOs.Report.YearlyReportData;
}>();

const max = computed(() =>
    Math.max(1, ...props.report.categories.flatMap((c) => c.values)),
);

function shade(value: number): string {
    return value === 0
        ? 'transparent'
        : `color-mix(in oklab, var(--expense) ${Math.round(12 + (value / max.value) * 70)}%, transparent)`;
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <p v-if="report.peakMonth" class="text-sm text-muted-foreground">
            Pengeluaran terbesar di bulan
            <span class="font-semibold text-foreground">{{
                report.peakMonth
            }}</span>
            · total setahun {{ report.total.formatted }} · pemasukan
            {{ report.income.formatted }}
        </p>
        <ReportTable
            :caption="`Pengeluaran per kategori tahun ${report.year}`"
            :headings="['Kategori', ...report.months, 'Total']"
            :numeric="Array.from({ length: 13 }, (_, i) => i + 1)"
        >
            <tr v-for="category in report.categories" :key="category.id">
                <td class="px-3 py-2 font-medium whitespace-nowrap">
                    {{ category.name }}
                </td>
                <td
                    v-for="(value, i) in category.values"
                    :key="i"
                    class="px-2 py-2 text-right text-xs"
                    :style="{ backgroundColor: shade(value) }"
                >
                    {{ value ? formatMoneyShort(value) : '–' }}
                </td>
                <td class="px-3 py-2 text-right font-semibold">
                    {{ category.total.formatted }}
                </td>
            </tr>
            <template #footer>
                <tr>
                    <td class="px-3 py-2">Total</td>
                    <td
                        v-for="(month, i) in report.monthTotals"
                        :key="i"
                        class="px-2 py-2 text-right text-xs"
                    >
                        {{
                            month.amount ? formatMoneyShort(month.amount) : '–'
                        }}
                    </td>
                    <td class="px-3 py-2 text-right">
                        {{ report.total.formatted }}
                    </td>
                </tr>
            </template>
        </ReportTable>
    </div>
</template>
