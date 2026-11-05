<script setup lang="ts">
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/core/components/ui/card';
import { formatPercent } from '@/core/lib/money';
import type { Stat } from '../api';

/*
 * One summary card: the amount as formatted by the server and the change
 * against the previous period (BR-A05: null = "Baru"). For expenses a rise
 * is bad, so the colour flips.
 */
const props = defineProps<{
    title: string;
    stat: Stat;
    tone?: 'income' | 'expense' | 'neutral';
}>();

const changeClass = computed(() => {
    const change = props.stat.change;
    if (change === null || change === 0) {
        return 'text-muted-foreground';
    }
    const good = props.tone === 'expense' ? change < 0 : change > 0;

    return good ? 'text-income' : 'text-expense';
});

const amountClass = computed(() =>
    props.tone === 'income'
        ? 'text-income'
        : props.tone === 'expense'
          ? 'text-expense'
          : 'text-foreground',
);
</script>

<template>
    <Card class="gap-2 py-4">
        <CardHeader class="px-4">
            <CardTitle class="text-sm font-medium text-muted-foreground">{{
                title
            }}</CardTitle>
        </CardHeader>
        <CardContent class="px-4">
            <p
                class="text-xl font-semibold tabular-nums"
                :class="amountClass"
                data-test="summary-amount"
            >
                {{ stat.formatted }}
            </p>
            <p class="mt-1 text-xs" :class="changeClass">
                {{ formatPercent(stat.change) }} dibanding periode lalu
            </p>
        </CardContent>
    </Card>
</template>
