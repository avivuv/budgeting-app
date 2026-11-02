<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { edit } from '@/routes/transactions';
import { sourceLabel, typeStyle } from '../constants';

type Item = App.DTOs.Transaction.TransactionItemData;

/*
 * Desktop renderer (≥ 1024px): sortable columns (server-side) and row
 * selection for bulk actions. Phones use TransactionDayList.
 */
const props = defineProps<{
    items: Item[];
    sort: string;
}>();

const emit = defineEmits<{
    sort: [value: string];
    delete: [item: Item];
}>();

const selected = defineModel<number[]>('selected', { required: true });

const selectable = computed(() => props.items.filter((i) => i.canEdit));
const allSelected = computed(
    () =>
        selectable.value.length > 0 &&
        selectable.value.every((i) => selected.value.includes(i.id)),
);

function toggleAll(value: boolean | 'indeterminate') {
    selected.value = value === true ? selectable.value.map((i) => i.id) : [];
}

function toggle(id: number, value: boolean | 'indeterminate') {
    selected.value =
        value === true
            ? [...selected.value, id]
            : selected.value.filter((s) => s !== id);
}

function sortBy(column: 'date' | 'amount') {
    emit('sort', props.sort === `-${column}` ? column : `-${column}`);
}

function sortIcon(column: 'date' | 'amount') {
    if (props.sort === column) {
        return ArrowUp;
    }

    return props.sort === `-${column}` ? ArrowDown : ArrowUpDown;
}
</script>

<template>
    <div class="overflow-x-auto rounded-2xl border">
        <table class="w-full text-sm">
            <thead class="bg-muted/60 text-left text-xs text-muted-foreground">
                <tr>
                    <th class="w-10 px-3 py-2">
                        <Checkbox
                            :model-value="allSelected"
                            :disabled="selectable.length === 0"
                            aria-label="Pilih semua di halaman ini"
                            @update:model-value="toggleAll"
                        />
                    </th>
                    <th class="px-3 py-2 font-medium">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1"
                            @click="sortBy('date')"
                        >
                            Tanggal
                            <component
                                :is="sortIcon('date')"
                                class="size-3.5"
                            />
                        </button>
                    </th>
                    <th class="px-3 py-2 font-medium">Keterangan</th>
                    <th class="px-3 py-2 font-medium">Dompet</th>
                    <th class="px-3 py-2 font-medium">Dicatat oleh</th>
                    <th class="px-3 py-2 font-medium">Sumber</th>
                    <th class="px-3 py-2 text-right font-medium">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1"
                            @click="sortBy('amount')"
                        >
                            Nominal
                            <component
                                :is="sortIcon('amount')"
                                class="size-3.5"
                            />
                        </button>
                    </th>
                    <th class="w-20 px-3 py-2">
                        <span class="sr-only">Aksi</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr
                    v-for="item in items"
                    :key="item.id"
                    class="hover:bg-muted/40"
                    :class="
                        selected.includes(item.id) ? 'bg-primary-soft/40' : ''
                    "
                >
                    <td class="px-3 py-2">
                        <Checkbox
                            v-if="item.canEdit"
                            :model-value="selected.includes(item.id)"
                            :aria-label="`Pilih transaksi ${item.amountFormatted}`"
                            @update:model-value="toggle(item.id, $event)"
                        />
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap tabular-nums">
                        {{ item.dateFormatted }}
                    </td>
                    <td class="max-w-72 px-3 py-2">
                        <p class="truncate font-medium">
                            <template v-if="item.type === 'transfer'">
                                Transfer ke {{ item.toWalletName }}
                            </template>
                            <template v-else>{{ item.categoryName }}</template>
                        </p>
                        <p
                            v-if="item.note || item.tags.length"
                            class="truncate text-xs text-muted-foreground"
                        >
                            {{ item.note }}
                            <span v-for="tag in item.tags" :key="tag">
                                #{{ tag }}
                            </span>
                        </p>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        {{ item.walletName }}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        {{ item.creatorName ?? 'Mantan anggota' }}
                    </td>
                    <td
                        class="px-3 py-2 whitespace-nowrap text-muted-foreground"
                    >
                        {{ sourceLabel[item.source] }}
                    </td>
                    <td
                        class="px-3 py-2 text-right font-semibold whitespace-nowrap tabular-nums"
                        :class="typeStyle[item.type].color"
                    >
                        {{ typeStyle[item.type].sign
                        }}{{ item.amountFormatted }}
                    </td>
                    <td class="px-3 py-2">
                        <div v-if="item.canEdit" class="flex justify-end gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                as-child
                            >
                                <Link :href="edit(item.id)" aria-label="Ubah">
                                    <Pencil class="size-4" />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 text-muted-foreground hover:text-destructive"
                                aria-label="Hapus"
                                @click="emit('delete', item)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
