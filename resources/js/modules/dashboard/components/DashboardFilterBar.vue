<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import type { DashboardFilters, Option } from '../types';

const props = defineProps<{
    period: App.DTOs.Dashboard.PeriodData;
    filters: DashboardFilters;
    walletOptions: (Option & { archived: boolean })[];
    memberOptions: Option[];
}>();

const emit = defineEmits<{
    change: [filters: DashboardFilters];
}>();

const wallets = computed(() =>
    props.walletOptions.map((w) => ({
        value: w.id,
        label: w.archived ? `${w.name} (diarsipkan)` : w.name,
    })),
);

const members = computed(() =>
    props.memberOptions.map((m) => ({ value: m.id, label: m.name })),
);

function set(patch: Partial<DashboardFilters>) {
    emit('change', { ...props.filters, ...patch });
}
</script>

<template>
    <div
        class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
    >
        <div class="flex items-center gap-1">
            <Button
                variant="ghost"
                size="icon"
                class="size-11"
                aria-label="Periode sebelumnya"
                @click="set({ period: period.previousKey })"
            >
                <ChevronLeft class="size-5" />
            </Button>
            <div class="min-w-36 text-center">
                <p class="font-semibold">{{ period.label }}</p>
                <p class="text-xs text-muted-foreground">{{ period.range }}</p>
            </div>
            <Button
                variant="ghost"
                size="icon"
                class="size-11"
                aria-label="Periode berikutnya"
                :disabled="period.nextKey === null"
                @click="set({ period: period.nextKey })"
            >
                <ChevronRight class="size-5" />
            </Button>
            <Button
                v-if="!period.isCurrent"
                variant="link"
                size="sm"
                @click="set({ period: null })"
            >
                Periode ini
            </Button>
        </div>

        <div class="grid grid-cols-2 gap-2 md:w-md">
            <SearchableSelect
                :model-value="filters.wallet"
                :options="wallets"
                placeholder="Semua dompet"
                clearable
                aria-label="Filter dompet"
                @update:model-value="
                    set({ wallet: ($event as number | null) ?? null })
                "
            />
            <SearchableSelect
                :model-value="filters.member"
                :options="members"
                placeholder="Semua anggota"
                clearable
                aria-label="Filter anggota"
                @update:model-value="
                    set({ member: ($event as number | null) ?? null })
                "
            />
        </div>
    </div>
</template>
