<script setup lang="ts">
import { ArrowDownLeft, ArrowUpRight } from '@lucide/vue';
import { formatPercent } from '@/lib/money';

defineProps<{
    summary: App.DTOs.Dashboard.DashboardSummaryData;
    periodLabel: string;
}>();
</script>

<template>
    <section
        class="rounded-2xl bg-linear-to-br from-primary to-primary-hover p-5 text-primary-foreground shadow-sm md:p-6"
        aria-label="Ringkasan saldo"
    >
        <p class="text-sm/none opacity-80">Total saldo</p>
        <p
            class="mt-2 text-[28px] font-bold tracking-tight tabular-nums md:text-[32px]"
        >
            {{ summary.balance.formatted }}
        </p>
        <p class="mt-1 text-xs opacity-80">
            {{ formatPercent(summary.balance.change) }} vs akhir periode lalu
        </p>

        <div class="mt-5 grid grid-cols-2 gap-3 border-t border-white/20 pt-4">
            <div>
                <p class="flex items-center gap-1 text-xs opacity-80">
                    <ArrowDownLeft class="size-4" /> Pemasukan
                    {{ periodLabel }}
                </p>
                <p class="mt-1 font-semibold tabular-nums">
                    +{{ summary.income.formatted }}
                </p>
            </div>
            <div>
                <p class="flex items-center gap-1 text-xs opacity-80">
                    <ArrowUpRight class="size-4" /> Pengeluaran
                    {{ periodLabel }}
                </p>
                <p class="mt-1 font-semibold tabular-nums">
                    −{{ summary.expense.formatted }}
                </p>
            </div>
        </div>
    </section>
</template>
