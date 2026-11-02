<script setup lang="ts">
import {
    CircleDollarSign,
    CreditCard,
    Landmark,
    Smartphone,
    Wallet,
} from '@lucide/vue';
import type { Component } from 'vue';

defineProps<{
    wallets: App.DTOs.Wallet.WalletItemData[];
}>();

const icons: Record<App.Enums.WalletType, Component> = {
    cash: Wallet,
    bank: Landmark,
    ewallet: Smartphone,
    credit_card: CreditCard,
    other: CircleDollarSign,
};
</script>

<template>
    <ul class="flex flex-col divide-y">
        <li
            v-for="wallet in wallets"
            :key="wallet.id"
            class="flex items-center gap-3 py-2.5"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary"
            >
                <component :is="icons[wallet.type]" class="size-4" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">{{ wallet.name }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ wallet.typeLabel }}
                </p>
            </div>
            <span
                class="text-sm font-semibold tabular-nums"
                :class="wallet.balance < 0 ? 'text-expense' : ''"
            >
                {{ wallet.balanceFormatted }}
            </span>
        </li>
    </ul>
</template>
