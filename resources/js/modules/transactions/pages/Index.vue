<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { bulk, create, index } from '@/routes/transactions';
import type { Paginated } from '@/types';
import TransactionBulkBar from '../components/TransactionBulkBar.vue';
import TransactionDayList from '../components/TransactionDayList.vue';
import TransactionFilterBar from '../components/TransactionFilterBar.vue';
import TransactionFilterSheet from '../components/TransactionFilterSheet.vue';
import TransactionTable from '../components/TransactionTable.vue';
import { useTransactionFilters } from '../composables/useTransactionFilters';
import type { Aggregates, DailyTotals, FilterOptions } from '../types';

type Item = App.DTOs.Transaction.TransactionItemData;

const props = defineProps<{
    transactions: Paginated<Item>;
    daily: DailyTotals;
    aggregates: Aggregates;
    filters: Record<string, unknown>;
    perPage: number;
    options: FilterOptions;
    canCreate: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Transaksi', href: index() }],
    },
});

// One data source, two renderers (docs/15 §6b): a table with pages on
// desktop, date-grouped cards with infinite scroll on phones and tablets.
const desktop = useMediaQuery('(min-width: 1024px)');

const perPage = ref(props.perPage);
const {
    filters,
    search,
    apply,
    applySearch,
    reset,
    isFiltered,
    advancedCount,
} = useTransactionFilters(props.filters, () => perPage.value);

const sheetOpen = ref(false);
const selected = ref<number[]>([]);
const processing = ref(false);

// A new filter or page is a new selection context.
watch(
    () => props.transactions.current_page + JSON.stringify(props.filters),
    () => (selected.value = []),
);

function runBulk(payload: {
    action: 'delete' | 'category';
    ids: number[];
    category_id?: number;
}) {
    router.post(bulk().url, payload, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: () => (selected.value = []),
    });
}

function deleteSelected(ids: number[]) {
    if (confirm(`Hapus ${ids.length} transaksi?`)) {
        runBulk({ action: 'delete', ids });
    }
}

function deleteOne(item: Item) {
    if (confirm(`Hapus transaksi ${item.amountFormatted}?`)) {
        runBulk({ action: 'delete', ids: [item.id] });
    }
}
</script>

<template>
    <Head title="Transaksi" />

    <div class="flex flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Transaksi"
                :description="`${aggregates.count} transaksi${isFiltered ? ' sesuai filter' : ''}`"
            />
            <Button v-if="canCreate" class="hidden md:inline-flex" as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Tambah transaksi
                </Link>
            </Button>
        </div>

        <TransactionFilterBar
            v-model:search="search"
            :filters="filters"
            :advanced-count="advancedCount"
            :is-filtered="isFiltered"
            @apply="apply"
            @search="applySearch"
            @reset="reset"
            @open-sheet="sheetOpen = true"
        />

        <dl
            class="grid grid-cols-3 gap-2 rounded-2xl border bg-muted/40 p-3 text-center text-xs sm:text-sm"
            aria-label="Total sesuai filter"
        >
            <div>
                <dt class="text-muted-foreground">Pemasukan</dt>
                <dd class="font-semibold text-income tabular-nums">
                    +{{ aggregates.incomeFormatted }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Pengeluaran</dt>
                <dd class="font-semibold text-expense tabular-nums">
                    −{{ aggregates.expenseFormatted }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Selisih</dt>
                <dd class="font-semibold tabular-nums">
                    {{ aggregates.netFormatted }}
                </dd>
            </div>
        </dl>

        <div
            v-if="transactions.data.length === 0"
            class="rounded-2xl border border-dashed p-10 text-center"
        >
            <template v-if="isFiltered">
                <p class="font-medium">Tidak ada transaksi yang cocok</p>
                <Button variant="link" @click="reset">Hapus filter</Button>
            </template>
            <template v-else>
                <p class="font-medium">Belum ada transaksi</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Catat pengeluaran pertama, misalnya "bensin 50rb".
                </p>
                <Button v-if="canCreate" class="mt-4" as-child>
                    <Link :href="create()">Tambah transaksi</Link>
                </Button>
            </template>
        </div>

        <template v-else-if="desktop">
            <TransactionBulkBar
                v-if="selected.length"
                :count="selected.length"
                :categories="options.categories"
                :processing="processing"
                @change-category="
                    (id) =>
                        runBulk({
                            action: 'category',
                            ids: selected,
                            category_id: id,
                        })
                "
                @delete="deleteSelected(selected)"
                @clear="selected = []"
            />

            <TransactionTable
                v-model:selected="selected"
                :items="transactions.data"
                :sort="filters.sort ?? '-date'"
                @sort="(sort) => apply({ sort })"
                @delete="deleteOne"
            />

            <div
                class="flex flex-wrap items-center justify-between gap-2 text-sm"
            >
                <div class="flex items-center gap-2 text-muted-foreground">
                    <span>Per halaman</span>
                    <NativeSelect
                        v-model="perPage"
                        class="w-20"
                        aria-label="Jumlah per halaman"
                        @update:model-value="apply({})"
                    >
                        <option
                            v-for="n in [10, 20, 50, 100]"
                            :key="n"
                            :value="n"
                        >
                            {{ n }}
                        </option>
                    </NativeSelect>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-muted-foreground">
                        Halaman {{ transactions.current_page }} dari
                        {{ transactions.last_page }}
                    </span>
                    <Button
                        variant="outline"
                        size="icon"
                        :disabled="!transactions.prev_page_url"
                        aria-label="Halaman sebelumnya"
                        as-child
                    >
                        <Link
                            :href="transactions.prev_page_url ?? '#'"
                            preserve-scroll
                        >
                            <ChevronLeft class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        :disabled="!transactions.next_page_url"
                        aria-label="Halaman berikutnya"
                        as-child
                    >
                        <Link
                            :href="transactions.next_page_url ?? '#'"
                            preserve-scroll
                        >
                            <ChevronRight class="size-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </template>

        <InfiniteScroll
            v-else
            data="transactions"
            only-next
            :buffer="400"
            :params="{ only: ['daily'] }"
        >
            <TransactionDayList :items="transactions.data" :daily="daily" />

            <template #loading>
                <div class="flex justify-center py-4">
                    <Spinner />
                </div>
            </template>
        </InfiniteScroll>
    </div>

    <TransactionFilterSheet
        v-model:open="sheetOpen"
        :filters="filters"
        :options="options"
        @apply="apply"
    />
</template>
