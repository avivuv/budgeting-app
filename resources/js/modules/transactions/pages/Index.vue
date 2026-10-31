<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowLeftRight,
    ArrowUpRight,
    ChevronLeft,
    ChevronRight,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import TransactionController from '@/actions/App/Http/Controllers/Web/Transaction/TransactionController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { create, edit, index } from '@/routes/transactions';
import type { Paginated } from '@/types';

type Item = App.DTOs.Transaction.TransactionItemData;

const props = defineProps<{
    transactions: Paginated<Item>;
    canCreate: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Transaksi', href: index() }],
    },
});

// Group the current page by date for display only; totals come from the server.
const groups = computed(() => {
    const map = new Map<string, { label: string; items: Item[] }>();

    for (const item of props.transactions.data) {
        if (!map.has(item.date)) {
            map.set(item.date, { label: item.dateFormatted, items: [] });
        }
        map.get(item.date)!.items.push(item);
    }

    return [...map.values()];
});

const style = {
    expense: {
        icon: ArrowUpRight,
        color: 'text-expense',
        bg: 'bg-expense/10',
        sign: '-',
    },
    income: {
        icon: ArrowDownLeft,
        color: 'text-income',
        bg: 'bg-income/10',
        sign: '+',
    },
    transfer: {
        icon: ArrowLeftRight,
        color: 'text-transfer',
        bg: 'bg-transfer/10',
        sign: '',
    },
} as const;

function destroy(item: Item) {
    if (confirm(`Hapus transaksi ${item.amountFormatted}?`)) {
        router.delete(TransactionController.destroy.url(item.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Transaksi" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Transaksi"
                :description="`${transactions.total} transaksi tercatat`"
            />
            <Button v-if="canCreate" as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Tambah transaksi
                </Link>
            </Button>
        </div>

        <div
            v-if="transactions.data.length === 0"
            class="rounded-lg border border-dashed p-10 text-center"
        >
            <p class="font-medium">Belum ada transaksi</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Catat pengeluaran pertama, misalnya "bensin 50rb".
            </p>
            <Button v-if="canCreate" class="mt-4" as-child>
                <Link :href="create()">Tambah transaksi</Link>
            </Button>
        </div>

        <div
            v-for="group in groups"
            :key="group.label"
            class="flex flex-col gap-2"
        >
            <h2 class="text-sm font-semibold text-muted-foreground">
                {{ group.label }}
            </h2>
            <Card class="py-0">
                <CardContent class="divide-y px-0">
                    <div
                        v-for="item in group.items"
                        :key="item.id"
                        class="flex items-center gap-3 px-4 py-3"
                    >
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-full"
                            :class="[
                                style[item.type].bg,
                                style[item.type].color,
                            ]"
                        >
                            <component
                                :is="style[item.type].icon"
                                class="size-5"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">
                                <template v-if="item.type === 'transfer'">
                                    {{ item.walletName }} →
                                    {{ item.toWalletName }}
                                </template>
                                <template v-else>
                                    {{ item.categoryName }}
                                </template>
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                <span v-if="item.note">{{ item.note }} · </span>
                                <span v-if="item.type !== 'transfer'"
                                    >{{ item.walletName }} ·
                                </span>
                                {{ item.creatorName ?? 'Mantan anggota' }}
                            </p>
                            <div
                                v-if="item.tags.length"
                                class="mt-1 flex flex-wrap gap-1"
                            >
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
                            :class="style[item.type].color"
                        >
                            {{ style[item.type].sign
                            }}{{ item.amountFormatted }}
                        </p>

                        <div v-if="item.canEdit" class="flex shrink-0 gap-1">
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
                                @click="destroy(item)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="transactions.last_page > 1"
            class="flex items-center justify-between gap-2 text-sm"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!transactions.prev_page_url"
                as-child
            >
                <Link :href="transactions.prev_page_url ?? '#'" preserve-scroll>
                    <ChevronLeft class="size-4" /> Sebelumnya
                </Link>
            </Button>
            <span class="text-muted-foreground">
                Halaman {{ transactions.current_page }} dari
                {{ transactions.last_page }}
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!transactions.next_page_url"
                as-child
            >
                <Link :href="transactions.next_page_url ?? '#'" preserve-scroll>
                    Berikutnya <ChevronRight class="size-4" />
                </Link>
            </Button>
        </div>
    </div>
</template>
