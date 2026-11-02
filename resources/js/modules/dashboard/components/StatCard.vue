<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { ArrowDownRight, ArrowUpRight } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { Card } from '@/components/ui/card';
import { formatPercent } from '@/lib/money';

const props = defineProps<{
    title: string;
    stat: App.DTOs.Dashboard.StatData;
    icon: Component;
    /** Colour of the value: income green, expense red (docs/14 §1.2). */
    tone?: 'income' | 'expense' | 'default';
    /** True when a higher value is bad (expenses): arrow colours flip. */
    lowerIsBetter?: boolean;
    href?: InertiaLinkProps['href'];
}>();

const trend = computed(() => {
    const change = props.stat.change;

    if (change === null || change === 0) {
        return { icon: null, class: 'text-muted-foreground' };
    }

    const good = props.lowerIsBetter ? change < 0 : change > 0;

    return {
        icon: change > 0 ? ArrowUpRight : ArrowDownRight,
        class: good ? 'text-income' : 'text-expense',
    };
});

const valueClass = computed(
    () =>
        ({
            income: 'text-income',
            expense: 'text-expense',
            default: 'text-foreground',
        })[props.tone ?? 'default'],
);
</script>

<template>
    <Card class="gap-0 rounded-2xl p-4 shadow-sm md:p-5">
        <component
            :is="href ? Link : 'div'"
            :href="href"
            class="flex flex-col gap-3 rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <div class="flex items-center gap-2">
                <span
                    class="flex size-9 items-center justify-center rounded-full bg-primary-soft text-primary"
                >
                    <component :is="icon" class="size-5" />
                </span>
                <span class="text-sm font-medium text-muted-foreground">
                    {{ title }}
                </span>
            </div>
            <p
                class="text-xl font-bold tracking-tight tabular-nums md:text-2xl"
                :class="valueClass"
            >
                {{ stat.formatted }}
            </p>
            <p class="flex items-center gap-1 text-xs" :class="trend.class">
                <component :is="trend.icon" v-if="trend.icon" class="size-4" />
                <span class="font-semibold">{{
                    formatPercent(stat.change)
                }}</span>
                <span class="text-muted-foreground">vs periode lalu</span>
            </p>
        </component>
    </Card>
</template>
