<script setup lang="ts">
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, Lightbulb, Scale } from '@lucide/vue';
import { Skeleton } from '@/components/ui/skeleton';
import { BudgetProgress } from '@/modules/budgets';
import { TransactionItem } from '@/modules/transactions';
import { dashboard } from '@/routes';
import { index as budgetsIndex } from '@/routes/budgets';
import { index as transactionsIndex } from '@/routes/transactions';
import { index as walletsIndex } from '@/routes/wallets';
import BalanceHeader from '../components/BalanceHeader.vue';
import CashflowChart from '../components/CashflowChart.vue';
import CategoryDonut from '../components/CategoryDonut.vue';
import DashboardCard from '../components/DashboardCard.vue';
import DashboardFilterBar from '../components/DashboardFilterBar.vue';
import MemberBar from '../components/MemberBar.vue';
import StatCard from '../components/StatCard.vue';
import TrendChart from '../components/TrendChart.vue';
import WalletBalanceList from '../components/WalletBalanceList.vue';
import type { DashboardFilters, Option } from '../types';

const props = defineProps<{
    period: App.DTOs.Dashboard.PeriodData;
    filters: DashboardFilters;
    walletOptions: (Option & { archived: boolean })[];
    memberOptions: Option[];
    summary: App.DTOs.Dashboard.DashboardSummaryData;
    insights?: string[];
    cashflow?: App.DTOs.Dashboard.CashflowPointData[];
    categories?: App.DTOs.Dashboard.CategorySliceData[];
    trend?: App.DTOs.Dashboard.TrendPointData[];
    members?: App.DTOs.Dashboard.MemberTotalData[];
    wallets?: App.DTOs.Wallet.WalletItemData[];
    budgets?: App.DTOs.Budget.BudgetItemData[];
    recent?: App.DTOs.Transaction.TransactionItemData[];
    canCreate: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Beranda', href: dashboard() }],
    },
});

function applyFilters(filters: DashboardFilters) {
    router.get(
        dashboard({
            query: {
                period: filters.period ?? undefined,
                wallet: filters.wallet ?? undefined,
                member: filters.member ?? undefined,
            },
        }).url,
        {},
        { preserveScroll: true, replace: true },
    );
}

/** Drill-down: transactions of this period with the same wallet/member filter. */
function transactionsOf(query: Record<string, string | number | number[]>) {
    return transactionsIndex({
        query: {
            from: props.period.start,
            to: props.period.end,
            wallet_id: props.filters.wallet
                ? [props.filters.wallet]
                : undefined,
            user_id: props.filters.member ? [props.filters.member] : undefined,
            ...query,
        },
    });
}
</script>

<template>
    <Head title="Beranda" />

    <div class="flex flex-col gap-4 p-4 md:gap-6 md:p-6">
        <DashboardFilterBar
            :period="period"
            :filters="filters"
            :wallet-options="walletOptions"
            :member-options="memberOptions"
            @change="applyFilters"
        />

        <div
            class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,2fr)]"
        >
            <BalanceHeader :summary="summary" :period-label="period.label" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    title="Pemasukan"
                    :stat="summary.income"
                    :icon="ArrowDownLeft"
                    tone="income"
                    :href="transactionsOf({ type: 'income' })"
                />
                <StatCard
                    title="Pengeluaran"
                    :stat="summary.expense"
                    :icon="ArrowUpRight"
                    tone="expense"
                    lower-is-better
                    :href="transactionsOf({ type: 'expense' })"
                />
                <StatCard title="Selisih" :stat="summary.net" :icon="Scale" />
            </div>
        </div>

        <Deferred data="insights">
            <template #fallback>
                <Skeleton class="h-14 rounded-2xl" />
            </template>
            <ul
                v-if="insights?.length"
                class="flex flex-col gap-2 rounded-2xl border bg-primary-soft/40 p-4 text-sm"
                aria-label="Insight"
            >
                <li
                    v-for="insight in insights"
                    :key="insight"
                    class="flex items-start gap-2"
                >
                    <Lightbulb class="mt-0.5 size-4 shrink-0 text-primary" />
                    {{ insight }}
                </li>
            </ul>
        </Deferred>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <DashboardCard
                title="Arus kas"
                description="Pemasukan vs pengeluaran 6 periode terakhir"
            >
                <Deferred data="cashflow">
                    <template #fallback>
                        <Skeleton class="h-64 rounded-xl" />
                    </template>
                    <CashflowChart :points="cashflow ?? []" />
                </Deferred>
            </DashboardCard>

            <DashboardCard title="Pengeluaran per kategori">
                <Deferred data="categories">
                    <template #fallback>
                        <Skeleton class="h-52 rounded-xl" />
                    </template>
                    <CategoryDonut
                        :slices="categories ?? []"
                        :href-for="
                            (slice) =>
                                transactionsOf({
                                    type: 'expense',
                                    category_id: [slice.categoryId],
                                })
                        "
                    />
                </Deferred>
            </DashboardCard>

            <DashboardCard
                title="Tren pengeluaran harian"
                description="Kumulatif periode ini dibanding laju rata-rata periode lalu"
            >
                <Deferred data="trend">
                    <template #fallback>
                        <Skeleton class="h-60 rounded-xl" />
                    </template>
                    <TrendChart :points="trend ?? []" />
                </Deferred>
            </DashboardCard>

            <DashboardCard title="Pengeluaran per anggota">
                <Deferred data="members">
                    <template #fallback>
                        <Skeleton class="h-40 rounded-xl" />
                    </template>
                    <MemberBar :members="members ?? []" />
                </Deferred>
            </DashboardCard>
        </div>

        <div
            class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]"
        >
            <div class="flex flex-col gap-4">
                <DashboardCard title="Budget">
                    <template #action>
                        <Link
                            :href="
                                budgetsIndex({ query: { period: period.key } })
                            "
                            class="text-sm font-medium text-primary"
                            >Atur</Link
                        >
                    </template>
                    <Deferred data="budgets">
                        <template #fallback>
                            <Skeleton class="h-32 rounded-xl" />
                        </template>
                        <p
                            v-if="!budgets?.length"
                            class="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground"
                        >
                            Belum ada budget di periode ini.
                        </p>
                        <div v-else class="flex flex-col gap-4">
                            <BudgetProgress
                                v-for="item in budgets.slice(0, 6)"
                                :key="item.id"
                                :item="item"
                            />
                        </div>
                    </Deferred>
                </DashboardCard>

                <DashboardCard title="Saldo dompet">
                    <template #action>
                        <Link
                            :href="walletsIndex()"
                            class="text-sm font-medium text-primary"
                            >Kelola</Link
                        >
                    </template>
                    <Deferred data="wallets">
                        <template #fallback>
                            <Skeleton class="h-40 rounded-xl" />
                        </template>
                        <WalletBalanceList :wallets="wallets ?? []" />
                    </Deferred>
                </DashboardCard>
            </div>

            <DashboardCard title="Transaksi terbaru">
                <template #action>
                    <Link
                        :href="transactionsIndex()"
                        class="text-sm font-medium text-primary"
                        >Lihat semua</Link
                    >
                </template>
                <Deferred data="recent">
                    <template #fallback>
                        <Skeleton class="h-64 rounded-xl" />
                    </template>
                    <p
                        v-if="!recent?.length"
                        class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
                    >
                        Belum ada transaksi. Coba catat "bensin 50rb".
                    </p>
                    <div v-else class="-mx-4 divide-y md:-mx-6">
                        <TransactionItem
                            v-for="item in recent"
                            :key="item.id"
                            :item="item"
                            show-date
                        />
                    </div>
                </Deferred>
            </DashboardCard>
        </div>
    </div>
</template>
