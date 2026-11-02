<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileSpreadsheet, FileText } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { BudgetProgress } from '@/modules/budgets';
import { exportMethod as exportReport, index } from '@/routes/reports';
import CashflowReport from '../components/CashflowReport.vue';
import CategoryReport from '../components/CategoryReport.vue';
import SimpleReports from '../components/SimpleReports.vue';
import YearlyHeatmap from '../components/YearlyHeatmap.vue';
import type { Preset, Report } from '../types';

const props = defineProps<{
    report: Report;
    tabs: { value: App.Enums.ReportTab; label: string }[];
    presets: Preset[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Laporan', href: index() }],
    },
});

type Query = {
    tab: App.Enums.ReportTab;
    from: string;
    to: string;
    year?: number;
    type?: 'income' | 'expense';
};

const query = computed<Query>(() => ({
    tab: props.report.tab,
    from: props.report.from,
    to: props.report.to,
    ...(props.report.tab === 'yearly' ? { year: props.report.year } : {}),
    ...(props.report.tab === 'categories' ? { type: props.report.type } : {}),
}));

function go(patch: Partial<Query>) {
    router.get(
        index().url,
        { ...query.value, ...patch },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

const years = computed(() => {
    const now = new Date().getFullYear();

    return Array.from({ length: 6 }, (_, i) => now - i);
});

const exportUrl = (format: 'xlsx' | 'pdf') =>
    exportReport({ query: { ...query.value, format } }).url;

const isRange = computed(
    () => props.report.tab !== 'yearly' && props.report.tab !== 'budget',
);
</script>

<template>
    <Head title="Laporan" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Laporan"
                :description="
                    report.tab === 'yearly'
                        ? `Tahun ${report.year}`
                        : report.rangeLabel
                "
            />
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <a :href="exportUrl('xlsx')" download>
                        <FileSpreadsheet class="size-4" /> Excel
                    </a>
                </Button>
                <Button variant="outline" as-child>
                    <a :href="exportUrl('pdf')" download>
                        <FileText class="size-4" /> PDF
                    </a>
                </Button>
            </div>
        </div>

        <nav
            class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0"
            aria-label="Jenis laporan"
        >
            <ul class="flex w-max gap-1 rounded-xl bg-muted p-1">
                <li v-for="tab in tabs" :key="tab.value">
                    <button
                        type="button"
                        class="min-h-9 rounded-lg px-3 text-sm font-medium whitespace-nowrap"
                        :class="
                            report.tab === tab.value
                                ? 'bg-background text-primary shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-current="
                            report.tab === tab.value ? 'page' : undefined
                        "
                        @click="go({ tab: tab.value })"
                    >
                        {{ tab.label }}
                    </button>
                </li>
            </ul>
        </nav>

        <div class="flex flex-wrap items-center gap-2">
            <template v-if="report.tab === 'yearly'">
                <NativeSelect
                    class="w-32"
                    :model-value="report.year"
                    aria-label="Tahun"
                    @update:model-value="go({ year: Number($event) })"
                >
                    <option v-for="y in years" :key="y" :value="y">
                        {{ y }}
                    </option>
                </NativeSelect>
            </template>
            <template v-else>
                <Button
                    v-for="preset in presets"
                    :key="preset.label"
                    size="sm"
                    :variant="
                        preset.from === report.from && preset.to === report.to
                            ? 'default'
                            : 'outline'
                    "
                    @click="go({ from: preset.from, to: preset.to })"
                >
                    {{ preset.label }}
                </Button>
                <template v-if="isRange">
                    <Input
                        type="date"
                        class="w-40"
                        :model-value="report.from"
                        aria-label="Dari tanggal"
                        @change="
                            go({
                                from: ($event.target as HTMLInputElement).value,
                            })
                        "
                    />
                    <Input
                        type="date"
                        class="w-40"
                        :model-value="report.to"
                        aria-label="Sampai tanggal"
                        @change="
                            go({
                                to: ($event.target as HTMLInputElement).value,
                            })
                        "
                    />
                </template>
                <NativeSelect
                    v-if="report.tab === 'categories'"
                    class="w-40"
                    :model-value="report.type"
                    aria-label="Jenis kategori"
                    @update:model-value="
                        go({ type: $event as 'income' | 'expense' })
                    "
                >
                    <option value="expense">Pengeluaran</option>
                    <option value="income">Pemasukan</option>
                </NativeSelect>
            </template>
        </div>

        <dl
            v-if="report.tab !== 'yearly'"
            class="grid grid-cols-3 gap-2 rounded-2xl border bg-muted/40 p-3 text-center text-xs sm:text-sm"
        >
            <div>
                <dt class="text-muted-foreground">Pemasukan</dt>
                <dd class="font-semibold text-income tabular-nums">
                    +{{ report.totals.income.formatted }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Pengeluaran</dt>
                <dd class="font-semibold text-expense tabular-nums">
                    −{{ report.totals.expense.formatted }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Selisih</dt>
                <dd class="font-semibold tabular-nums">
                    {{ report.totals.net.formatted }}
                </dd>
            </div>
        </dl>

        <p
            v-if="Array.isArray(report.rows) && report.rows.length === 0"
            class="rounded-2xl border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            Tidak ada data pada rentang ini.
        </p>

        <CashflowReport
            v-else-if="report.tab === 'cashflow'"
            :rows="report.rows as App.DTOs.Report.CashflowRowData[]"
        />
        <CategoryReport
            v-else-if="report.tab === 'categories'"
            :rows="report.rows as App.DTOs.Report.CategoryRowData[]"
        />
        <YearlyHeatmap
            v-else-if="report.tab === 'yearly'"
            :report="report.rows as App.DTOs.Report.YearlyReportData"
        />
        <div
            v-else-if="report.tab === 'budget'"
            class="flex flex-col gap-4 rounded-2xl border p-4 md:p-6"
        >
            <p v-if="report.budgetTotals" class="text-sm text-muted-foreground">
                Terpakai {{ report.budgetTotals.spentFormatted }} dari
                {{ report.budgetTotals.amountFormatted }} · sisa
                {{ report.budgetTotals.remainingFormatted }}
            </p>
            <BudgetProgress
                v-for="item in report.rows as App.DTOs.Budget.BudgetItemData[]"
                :key="item.id"
                :item="item"
                detailed
            />
        </div>
        <SimpleReports v-else :tab="report.tab" :rows="report.rows" />
    </div>
</template>
