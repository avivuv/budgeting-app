<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import NativeSelect from '@/components/NativeSelect.vue';
import SearchableMultiSelect from '@/components/SearchableMultiSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { categoryOptions } from '@/modules/categories';
import type { FilterOptions, TransactionFilters } from '../types';

const props = defineProps<{
    filters: TransactionFilters;
    options: FilterOptions;
}>();

const emit = defineEmits<{
    apply: [patch: Partial<TransactionFilters>];
}>();

const open = defineModel<boolean>('open', { required: true });

// A bottom sheet on phones, a side panel from tablet up (docs/15 §6b).
const wide = useMediaQuery('(min-width: 768px)');

type Draft = {
    category_id: (number | string)[];
    wallet_id: (number | string)[];
    user_id: (number | string)[];
    tag: (number | string)[];
    source: string;
    min: string;
    max: string;
};

const draft = ref<Draft>(fromFilters());

function fromFilters(): Draft {
    return {
        category_id: props.filters.category_id ?? [],
        wallet_id: props.filters.wallet_id ?? [],
        user_id: props.filters.user_id ?? [],
        tag: props.filters.tag ?? [],
        source: props.filters.source ?? '',
        min: props.filters.min?.toString() ?? '',
        max: props.filters.max?.toString() ?? '',
    };
}

watch(open, (isOpen) => {
    if (isOpen) {
        draft.value = fromFilters();
    }
});

const categories = computed(() => [
    ...categoryOptions(props.options.categories.expense, {
        includeArchived: true,
    }).map((o) => ({ ...o, group: 'Pengeluaran' })),
    ...categoryOptions(props.options.categories.income, {
        includeArchived: true,
    }).map((o) => ({ ...o, group: 'Pemasukan' })),
]);
const wallets = computed(() =>
    props.options.wallets.map((w) => ({
        value: w.id,
        label: w.archived ? `${w.name} (diarsipkan)` : w.name,
    })),
);
const members = computed(() =>
    props.options.members.map((m) => ({ value: m.id, label: m.name })),
);
const tags = computed(() =>
    props.options.tags.map((t) => ({ value: t, label: `#${t}` })),
);

// Amounts may be typed as "50rb"; the server parses them (BR-A03).
function submit() {
    const d = draft.value;

    emit('apply', {
        category_id: d.category_id.map(Number),
        wallet_id: d.wallet_id.map(Number),
        user_id: d.user_id.map(Number),
        tag: d.tag.map(String),
        source: (d.source || undefined) as TransactionFilters['source'],
        min: d.min.trim() || undefined,
        max: d.max.trim() || undefined,
    });
    open.value = false;
}

function clear() {
    draft.value = {
        category_id: [],
        wallet_id: [],
        user_id: [],
        tag: [],
        source: '',
        min: '',
        max: '',
    };
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            :side="wide ? 'right' : 'bottom'"
            class="max-h-[90dvh] overflow-y-auto"
        >
            <SheetHeader>
                <SheetTitle>Filter transaksi</SheetTitle>
                <SheetDescription>
                    Kategori induk ikut menyertakan sub-kategorinya.
                </SheetDescription>
            </SheetHeader>

            <div class="grid gap-4 px-4">
                <div class="grid gap-2">
                    <Label for="filter-category">Kategori</Label>
                    <SearchableMultiSelect
                        id="filter-category"
                        v-model="draft.category_id"
                        :options="categories"
                        placeholder="Semua kategori"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="filter-wallet">Dompet</Label>
                    <SearchableMultiSelect
                        id="filter-wallet"
                        v-model="draft.wallet_id"
                        :options="wallets"
                        placeholder="Semua dompet"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="filter-member">Dicatat oleh</Label>
                    <SearchableMultiSelect
                        id="filter-member"
                        v-model="draft.user_id"
                        :options="members"
                        placeholder="Semua anggota"
                    />
                </div>
                <div v-if="tags.length" class="grid gap-2">
                    <Label for="filter-tag">Tag</Label>
                    <SearchableMultiSelect
                        id="filter-tag"
                        v-model="draft.tag"
                        :options="tags"
                        placeholder="Semua tag"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="filter-source">Sumber</Label>
                    <NativeSelect id="filter-source" v-model="draft.source">
                        <option value="">Semua sumber</option>
                        <option
                            v-for="s in options.sources"
                            :key="s.value"
                            :value="s.value"
                        >
                            {{ s.label }}
                        </option>
                    </NativeSelect>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-2">
                        <Label for="filter-min">Nominal min.</Label>
                        <Input
                            id="filter-min"
                            v-model="draft.min"
                            inputmode="numeric"
                            placeholder="mis. 50rb"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="filter-max">Nominal maks.</Label>
                        <Input
                            id="filter-max"
                            v-model="draft.max"
                            inputmode="numeric"
                            placeholder="mis. 1jt"
                        />
                    </div>
                </div>
            </div>

            <SheetFooter class="flex-row gap-2">
                <Button variant="outline" class="flex-1" @click="clear">
                    Kosongkan
                </Button>
                <Button class="flex-1" @click="submit">Terapkan</Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
