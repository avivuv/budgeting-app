<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Copy,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/Web/Budget/BudgetController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatPercent } from '@/lib/money';
import { index } from '@/routes/budgets';
import BudgetFormDialog from '../components/BudgetFormDialog.vue';
import BudgetProgress from '../components/BudgetProgress.vue';
import type { AvailableCategory, BudgetTotals } from '../types';

type Item = App.DTOs.Budget.BudgetItemData;

const props = defineProps<{
    period: App.DTOs.Dashboard.PeriodData;
    budgets: Item[];
    totals: BudgetTotals;
    availableCategories: AvailableCategory[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Budget', href: index() }],
    },
});

const dialogOpen = ref(false);
const editing = ref<Item | null>(null);

function add() {
    editing.value = null;
    dialogOpen.value = true;
}

function edit(item: Item) {
    editing.value = item;
    dialogOpen.value = true;
}

function remove(item: Item) {
    if (confirm(`Hapus budget ${item.categoryName}?`)) {
        router.delete(BudgetController.destroy.url(item.id), {
            preserveScroll: true,
        });
    }
}

function copyPrevious() {
    router.post(
        BudgetController.copy.url(),
        { period: props.period.key },
        { preserveScroll: true },
    );
}

const totalTone = {
    safe: 'bg-income',
    warning: 'bg-warning',
    over: 'bg-expense',
} as const;
</script>

<template>
    <Head title="Budget" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Budget"
                description="Batas pengeluaran per kategori. Peringatan dikirim saat mencapai 80% dan 100%."
            />
            <div v-if="canManage" class="flex gap-2">
                <Button variant="outline" @click="copyPrevious">
                    <Copy class="size-4" /> Salin periode lalu
                </Button>
                <Button @click="add"> <Plus class="size-4" /> Tambah </Button>
            </div>
        </div>

        <div class="flex items-center gap-1">
            <Button variant="ghost" size="icon" class="size-11" as-child>
                <Link
                    :href="index({ query: { period: period.previousKey } })"
                    aria-label="Periode sebelumnya"
                    preserve-scroll
                >
                    <ChevronLeft class="size-5" />
                </Link>
            </Button>
            <div class="min-w-36 text-center">
                <p class="font-semibold">{{ period.label }}</p>
                <p class="text-xs text-muted-foreground">{{ period.range }}</p>
            </div>
            <Button
                variant="ghost"
                size="icon"
                class="size-11"
                :disabled="!period.nextKey"
                as-child
            >
                <Link
                    :href="
                        period.nextKey
                            ? index({ query: { period: period.nextKey } })
                            : '#'
                    "
                    aria-label="Periode berikutnya"
                    preserve-scroll
                >
                    <ChevronRight class="size-5" />
                </Link>
            </Button>
        </div>

        <Card v-if="budgets.length" class="rounded-2xl">
            <CardContent class="flex flex-col gap-3">
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <p class="text-sm text-muted-foreground">Total terpakai</p>
                    <p class="text-sm tabular-nums">
                        <span class="text-lg font-bold">{{
                            totals.spentFormatted
                        }}</span>
                        / {{ totals.amountFormatted }} ·
                        {{ formatPercent(totals.percentage) }}
                    </p>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full"
                        :class="totalTone[totals.status]"
                        :style="{
                            width: `${Math.min(totals.percentage ?? 0, 100)}%`,
                        }"
                    />
                </div>
                <p class="text-xs text-muted-foreground">
                    Sisa {{ totals.remainingFormatted }}
                    <template v-if="period.isCurrent && totals.remaining > 0">
                        · boleh dipakai {{ totals.dailyAllowanceFormatted }} per
                        hari sampai akhir periode
                    </template>
                </p>
            </CardContent>
        </Card>

        <div
            v-if="budgets.length === 0"
            class="rounded-2xl border border-dashed p-10 text-center"
        >
            <p class="font-medium">Belum ada budget untuk {{ period.label }}</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Tentukan batas, misalnya Makanan Rp 2 jt per bulan.
            </p>
            <div v-if="canManage" class="mt-4 flex justify-center gap-2">
                <Button variant="outline" @click="copyPrevious">
                    Salin periode lalu
                </Button>
                <Button @click="add">Tambah budget</Button>
            </div>
        </div>

        <Card v-else class="gap-0 rounded-2xl py-0">
            <CardContent class="divide-y px-0">
                <div
                    v-for="item in budgets"
                    :key="item.id"
                    class="flex items-center gap-3 px-4 py-4 md:px-6"
                >
                    <BudgetProgress :item="item" detailed class="flex-1" />
                    <div v-if="canManage" class="flex shrink-0 gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-9"
                            :aria-label="`Ubah budget ${item.categoryName}`"
                            @click="edit(item)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-9 text-muted-foreground hover:text-destructive"
                            :aria-label="`Hapus budget ${item.categoryName}`"
                            @click="remove(item)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <BudgetFormDialog
        v-model:open="dialogOpen"
        :budget="editing"
        :available="availableCategories"
        :period-key="period.key"
        :period-label="period.label"
    />
</template>
