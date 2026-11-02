<script setup lang="ts">
import { PartyPopper, Target } from '@lucide/vue';
import { formatPercent } from '@/lib/money';

/*
 * One goal with its progress (BR-K01, K03, K04). Numbers from the server.
 */
defineProps<{
    goal: App.DTOs.Savings.SavingGoalItemData;
    compact?: boolean;
}>();

defineSlots<{
    actions?: () => unknown;
}>();
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex items-start gap-3">
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-full"
                :class="
                    goal.achieved
                        ? 'bg-income/15 text-income'
                        : 'bg-primary-soft text-primary'
                "
            >
                <component
                    :is="goal.achieved ? PartyPopper : Target"
                    class="size-4"
                />
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate font-medium">{{ goal.name }}</p>
                <p class="text-xs text-muted-foreground tabular-nums">
                    {{ goal.progressFormatted }} / {{ goal.targetFormatted }} ·
                    {{ formatPercent(goal.percentage) }}
                </p>
            </div>
            <slot name="actions" />
        </div>
        <div
            class="h-2 overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="goal.percentage ?? 0"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="`Tujuan ${goal.name}`"
        >
            <div
                class="h-full rounded-full"
                :class="goal.achieved ? 'bg-income' : 'bg-primary'"
                :style="{ width: `${Math.min(goal.percentage ?? 0, 100)}%` }"
            />
        </div>
        <p v-if="!compact" class="text-xs text-muted-foreground">
            <template v-if="goal.achieved">Tercapai 🎉</template>
            <template v-else>
                Kurang {{ goal.remainingFormatted }}
                <template v-if="goal.monthlyNeededFormatted">
                    · {{ goal.monthlyNeededFormatted }}/bulan sampai
                    {{ goal.deadlineFormatted }}
                </template>
            </template>
            <template v-if="goal.walletName">
                · mengikuti saldo {{ goal.walletName }}</template
            >
        </p>
    </div>
</template>
