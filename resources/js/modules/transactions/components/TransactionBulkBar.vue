<script setup lang="ts">
import { Tags, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { categoryOptions } from '@/modules/categories';
import type { FilterOptions } from '../types';

/*
 * Bulk actions for the selected rows (docs/07 §4): change category or delete.
 * The server re-checks permissions per transaction (BR-B09).
 */
const props = defineProps<{
    count: number;
    categories: FilterOptions['categories'];
    processing: boolean;
}>();

const emit = defineEmits<{
    changeCategory: [categoryId: number];
    delete: [];
    clear: [];
}>();

const categoryId = ref<number | string | null>(null);

const options = computed(() => [
    ...categoryOptions(props.categories.expense).map((o) => ({
        ...o,
        group: 'Pengeluaran',
    })),
    ...categoryOptions(props.categories.income).map((o) => ({
        ...o,
        group: 'Pemasukan',
    })),
]);
</script>

<template>
    <div
        class="sticky top-2 z-20 flex flex-wrap items-center gap-2 rounded-2xl border bg-background p-3 shadow-md"
        role="region"
        aria-label="Aksi untuk transaksi terpilih"
    >
        <p class="text-sm font-medium">{{ count }} dipilih</p>
        <SearchableSelect
            v-model="categoryId"
            :options="options"
            placeholder="Pindahkan ke kategori…"
            class="w-64"
        />
        <Button
            size="sm"
            :disabled="categoryId === null || processing"
            @click="emit('changeCategory', Number(categoryId))"
        >
            <Tags class="size-4" /> Ubah kategori
        </Button>
        <Button
            size="sm"
            variant="destructive"
            :disabled="processing"
            @click="emit('delete')"
        >
            <Trash2 class="size-4" /> Hapus
        </Button>
        <Button
            size="sm"
            variant="ghost"
            class="ml-auto"
            aria-label="Batalkan pilihan"
            @click="emit('clear')"
        >
            <X class="size-4" />
        </Button>
    </div>
</template>
