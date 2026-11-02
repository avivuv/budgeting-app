<script setup lang="ts">
import { Search, SlidersHorizontal, X } from '@lucide/vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { TransactionFilters } from '../types';

defineProps<{
    filters: TransactionFilters;
    advancedCount: number;
    isFiltered: boolean;
}>();

const emit = defineEmits<{
    apply: [patch: Partial<TransactionFilters>];
    search: [];
    reset: [];
    openSheet: [];
}>();

const search = defineModel<string>('search', { required: true });

const types: { value: App.Enums.TransactionType | ''; label: string }[] = [
    { value: '', label: 'Semua jenis' },
    { value: 'expense', label: 'Pengeluaran' },
    { value: 'income', label: 'Pemasukan' },
    { value: 'transfer', label: 'Transfer' },
];

function onDate(key: 'from' | 'to', event: Event) {
    emit('apply', {
        [key]: (event.target as HTMLInputElement).value || undefined,
    });
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex gap-2">
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Cari catatan atau merchant…"
                    class="pl-9"
                    aria-label="Cari transaksi"
                    @update:model-value="emit('search')"
                />
            </div>
            <Button
                variant="outline"
                class="shrink-0"
                aria-label="Filter"
                @click="emit('openSheet')"
            >
                <SlidersHorizontal class="size-4" />
                <span class="hidden sm:inline">Filter</span>
                <Badge v-if="advancedCount" class="ml-1 px-1.5">{{
                    advancedCount
                }}</Badge>
            </Button>
        </div>

        <div
            class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center"
        >
            <NativeSelect
                class="col-span-2 sm:w-44"
                :model-value="filters.type ?? ''"
                aria-label="Jenis transaksi"
                @update:model-value="
                    emit('apply', {
                        type: ($event ||
                            undefined) as TransactionFilters['type'],
                    })
                "
            >
                <option v-for="t in types" :key="t.value" :value="t.value">
                    {{ t.label }}
                </option>
            </NativeSelect>
            <Input
                type="date"
                class="sm:w-40"
                :model-value="filters.from ?? ''"
                aria-label="Dari tanggal"
                @change="onDate('from', $event)"
            />
            <Input
                type="date"
                class="sm:w-40"
                :model-value="filters.to ?? ''"
                aria-label="Sampai tanggal"
                @change="onDate('to', $event)"
            />
            <Button
                v-if="isFiltered"
                variant="ghost"
                size="sm"
                class="col-span-2 justify-self-start"
                @click="emit('reset')"
            >
                <X class="size-4" /> Hapus semua filter
            </Button>
        </div>
    </div>
</template>
