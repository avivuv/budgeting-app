<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Card } from '@/components/ui/card';
import { edit } from '@/routes/transactions';
import type { DailyTotals } from '../types';
import TransactionItem from './TransactionItem.vue';

type Item = App.DTOs.Transaction.TransactionItemData;

/*
 * Phone renderer: cards grouped per date with the day's subtotal. Subtotals
 * come from the server for the whole filter, not from the loaded rows (INV-06).
 */
const props = defineProps<{
    items: Item[];
    daily: DailyTotals;
}>();

const groups = computed(() => {
    const map = new Map<
        string,
        { date: string; label: string; items: Item[] }
    >();

    for (const item of props.items) {
        if (!map.has(item.date)) {
            map.set(item.date, {
                date: item.date,
                label: item.dateFormatted,
                items: [],
            });
        }

        map.get(item.date)!.items.push(item);
    }

    return [...map.values()];
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <section
            v-for="group in groups"
            :key="group.date"
            class="flex flex-col gap-2"
        >
            <header
                class="flex items-baseline justify-between gap-2 px-1 text-xs"
            >
                <h2 class="font-semibold text-muted-foreground">
                    {{ group.label }}
                </h2>
                <p v-if="daily[group.date]" class="tabular-nums">
                    <span v-if="daily[group.date].income" class="text-income"
                        >+{{ daily[group.date].incomeFormatted }}</span
                    >
                    <span
                        v-if="
                            daily[group.date].income &&
                            daily[group.date].expense
                        "
                        class="text-muted-foreground"
                    >
                        ·
                    </span>
                    <span v-if="daily[group.date].expense" class="text-expense"
                        >−{{ daily[group.date].expenseFormatted }}</span
                    >
                </p>
            </header>
            <Card class="gap-0 divide-y overflow-hidden rounded-2xl py-0">
                <component
                    :is="item.canEdit ? Link : 'div'"
                    v-for="item in group.items"
                    :key="item.id"
                    :href="item.canEdit ? edit(item.id) : undefined"
                    class="block hover:bg-muted/40"
                >
                    <TransactionItem :item="item" />
                </component>
            </Card>
        </section>
    </div>
</template>
