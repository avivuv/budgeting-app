<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { typeStyle } from '../constants';

defineProps<{
    item: App.DTOs.Transaction.TransactionItemData;
    /** Show the date under the title (lists not grouped by date). */
    showDate?: boolean;
}>();

defineSlots<{
    /** Row actions on the right, e.g. edit / delete. */
    actions?: () => unknown;
}>();
</script>

<template>
    <div class="flex items-center gap-3 px-4 py-3">
        <div
            class="flex size-10 shrink-0 items-center justify-center rounded-full"
            :class="[typeStyle[item.type].bg, typeStyle[item.type].color]"
        >
            <component :is="typeStyle[item.type].icon" class="size-5" />
        </div>

        <div class="min-w-0 flex-1">
            <p class="truncate font-medium">
                <template v-if="item.type === 'transfer'">
                    {{ item.walletName }} → {{ item.toWalletName }}
                </template>
                <template v-else>{{ item.categoryName }}</template>
            </p>
            <p class="truncate text-xs text-muted-foreground">
                <span v-if="showDate">{{ item.dateFormatted }} · </span>
                <span v-if="item.note">{{ item.note }} · </span>
                <span v-if="item.type !== 'transfer'"
                    >{{ item.walletName }} ·
                </span>
                {{ item.creatorName ?? 'Mantan anggota' }}
            </p>
            <div v-if="item.tags.length" class="mt-1 flex flex-wrap gap-1">
                <Badge
                    v-for="tag in item.tags"
                    :key="tag"
                    variant="secondary"
                    class="text-[10px]"
                >
                    #{{ tag }}
                </Badge>
            </div>
        </div>

        <p
            class="shrink-0 text-right font-semibold tabular-nums"
            :class="typeStyle[item.type].color"
        >
            {{ typeStyle[item.type].sign }}{{ item.amountFormatted }}
        </p>

        <slot name="actions" />
    </div>
</template>
