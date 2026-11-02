<script setup lang="ts">
import { formatPercent } from '@/lib/money';
import EmptyChart from './EmptyChart.vue';

defineProps<{
    members: App.DTOs.Dashboard.MemberTotalData[];
}>();
</script>

<template>
    <EmptyChart v-if="members.length === 0" text="Belum ada pengeluaran." />
    <ul v-else class="flex flex-col gap-3" aria-label="Pengeluaran per anggota">
        <li v-for="member in members" :key="member.userId ?? 0">
            <div class="flex items-baseline justify-between gap-2 text-sm">
                <span class="truncate font-medium">{{ member.name }}</span>
                <span class="shrink-0 tabular-nums">{{
                    member.formatted
                }}</span>
            </div>
            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full bg-primary"
                    :style="{ width: `${member.percentage ?? 0}%` }"
                />
            </div>
            <p class="mt-1 text-xs text-muted-foreground">
                {{ formatPercent(member.percentage) }} ·
                {{ member.count }} transaksi
            </p>
        </li>
    </ul>
</template>
