<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Button } from '@/core/components/ui/button';
import { Skeleton } from '@/core/components/ui/skeleton';
import { useSummary } from '../api';
import SummaryCard from '../components/SummaryCard.vue';

/*
 * Home of the SPA (M3): the period summary from the API. The full dashboard
 * (charts, budgets, recent transactions) moves here in M4.
 */
const route = useRoute();
const router = useRouter();

const period = computed(() =>
    typeof route.query.period === 'string' ? route.query.period : null,
);
const summary = useSummary(period);

function go(key: string | null): void {
    void router.replace({ query: key ? { period: key } : {} });
}
</script>

<template>
    <div class="flex items-center justify-between gap-2">
        <Button
            variant="ghost"
            size="icon"
            aria-label="Periode sebelumnya"
            :disabled="!summary.data.value"
            @click="go(summary.data.value?.period.previous_key ?? null)"
        >
            <ChevronLeft class="size-5" />
        </Button>
        <div class="text-center">
            <p class="font-semibold" data-test="period-label">
                {{ summary.data.value?.period.label ?? '…' }}
            </p>
            <p class="text-xs text-muted-foreground">
                {{ summary.data.value?.period.range }}
            </p>
        </div>
        <Button
            variant="ghost"
            size="icon"
            aria-label="Periode berikutnya"
            :disabled="!summary.data.value?.period.next_key"
            @click="go(summary.data.value?.period.next_key ?? null)"
        >
            <ChevronRight class="size-5" />
        </Button>
    </div>

    <div
        v-if="summary.isPending.value"
        class="grid grid-cols-2 gap-3 lg:grid-cols-4"
    >
        <Skeleton v-for="i in 4" :key="i" class="h-28 rounded-xl" />
    </div>

    <p v-else-if="summary.isError.value" class="text-sm text-destructive">
        Ringkasan tidak bisa dimuat. Coba muat ulang halaman.
    </p>

    <div
        v-else-if="summary.data.value"
        class="grid grid-cols-2 gap-3 lg:grid-cols-4"
    >
        <SummaryCard title="Saldo" :stat="summary.data.value.summary.balance" />
        <SummaryCard
            title="Pemasukan"
            :stat="summary.data.value.summary.income"
            tone="income"
        />
        <SummaryCard
            title="Pengeluaran"
            :stat="summary.data.value.summary.expense"
            tone="expense"
        />
        <SummaryCard title="Selisih" :stat="summary.data.value.summary.net" />
    </div>
</template>
