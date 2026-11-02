<script setup lang="ts">
import { computed } from 'vue';
import { formatPercent } from '@/lib/money';

/*
 * One budget: "Rp 650rb / 800rb · 81,3%" with a bar coloured by status
 * (BR-F04, docs/14 §4). All numbers come from the server.
 */
const props = defineProps<{
    item: App.DTOs.Budget.BudgetItemData;
    /** Show remaining and daily allowance under the bar. */
    detailed?: boolean;
}>();

const tone = computed(
    () =>
        ({
            safe: { bar: 'bg-income', text: 'text-income' },
            warning: { bar: 'bg-warning', text: 'text-warning' },
            over: { bar: 'bg-expense', text: 'text-expense' },
        })[props.item.status],
);

const width = computed(() => Math.min(props.item.percentage ?? 0, 100));
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <div class="flex items-baseline justify-between gap-2 text-sm">
            <span class="truncate font-medium">
                <span v-if="item.isSubCategory" class="text-muted-foreground"
                    >↳
                </span>
                {{ item.categoryName }}
            </span>
            <span class="shrink-0 text-xs text-muted-foreground tabular-nums">
                {{ item.spentShort }} / {{ item.amountShort }} ·
                <span class="font-semibold" :class="tone.text">{{
                    formatPercent(item.percentage)
                }}</span>
            </span>
        </div>
        <div
            class="h-2 overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="item.percentage ?? 0"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="`${item.categoryName}: ${item.statusLabel}`"
        >
            <div
                class="h-full rounded-full transition-[width]"
                :class="tone.bar"
                :style="{ width: `${width}%` }"
            />
        </div>
        <p
            v-if="detailed"
            class="flex justify-between gap-2 text-xs text-muted-foreground"
        >
            <span>
                <span :class="tone.text">{{ item.statusLabel }}</span> · sisa
                <span
                    class="tabular-nums"
                    :class="item.remaining < 0 ? 'text-expense' : ''"
                    >{{ item.remainingFormatted }}</span
                >
            </span>
            <span v-if="item.remaining > 0" class="tabular-nums">
                {{ item.dailyAllowanceFormatted }}/hari
            </span>
        </p>
    </div>
</template>
