<script setup lang="ts">
import { formatPercent } from '@/lib/money';
import ReportTable from './ReportTable.vue';

/*
 * Members, wallets, merchants and tags: tables only.
 */
defineProps<{
    tab: 'members' | 'wallets' | 'merchants' | 'tags';
    rows: unknown;
}>();

const members = (rows: unknown) => rows as App.DTOs.Report.MemberRowData[];
const wallets = (rows: unknown) => rows as App.DTOs.Report.WalletMovementData[];
const ranked = (rows: unknown) => rows as App.DTOs.Report.RankedRowData[];
</script>

<template>
    <ReportTable
        v-if="tab === 'members'"
        caption="Per anggota"
        :headings="[
            'Anggota',
            'Pemasukan',
            'Pengeluaran',
            'Porsi pengeluaran',
            'Transaksi',
        ]"
        :numeric="[1, 2, 3, 4]"
    >
        <tr v-for="row in members(rows)" :key="row.userId ?? 0">
            <td class="px-3 py-2 font-medium">{{ row.name }}</td>
            <td class="px-3 py-2 text-right text-income">
                {{ row.income.formatted }}
            </td>
            <td class="px-3 py-2 text-right text-expense">
                {{ row.expense.formatted }}
            </td>
            <td class="px-3 py-2 text-right">
                {{ formatPercent(row.expenseShare) }}
            </td>
            <td class="px-3 py-2 text-right">{{ row.count }}</td>
        </tr>
    </ReportTable>

    <ReportTable
        v-else-if="tab === 'wallets'"
        caption="Mutasi per dompet"
        :headings="[
            'Dompet',
            'Saldo awal',
            'Masuk',
            'Keluar',
            'Transfer masuk',
            'Transfer keluar',
            'Saldo akhir',
        ]"
        :numeric="[1, 2, 3, 4, 5, 6]"
    >
        <tr v-for="row in wallets(rows)" :key="row.id">
            <td class="px-3 py-2 font-medium whitespace-nowrap">
                {{ row.name }}
                <span class="block text-xs text-muted-foreground">
                    {{ row.typeLabel
                    }}{{ row.isArchived ? ' · diarsipkan' : '' }}
                </span>
            </td>
            <td class="px-3 py-2 text-right">{{ row.opening.formatted }}</td>
            <td class="px-3 py-2 text-right text-income">
                {{ row.income.formatted }}
            </td>
            <td class="px-3 py-2 text-right text-expense">
                {{ row.expense.formatted }}
            </td>
            <td class="px-3 py-2 text-right">{{ row.transferIn.formatted }}</td>
            <td class="px-3 py-2 text-right">
                {{ row.transferOut.formatted }}
            </td>
            <td class="px-3 py-2 text-right font-semibold">
                {{ row.closing.formatted }}
            </td>
        </tr>
    </ReportTable>

    <ReportTable
        v-else
        :caption="tab === 'tags' ? 'Per tag' : 'Per merchant'"
        :headings="[
            tab === 'tags' ? 'Tag' : 'Merchant / catatan',
            'Nominal',
            'Porsi',
            'Transaksi',
        ]"
        :numeric="[1, 2, 3]"
    >
        <tr v-for="row in ranked(rows)" :key="row.name">
            <td class="px-3 py-2 font-medium">
                {{ tab === 'tags' ? `#${row.name}` : row.name }}
            </td>
            <td class="px-3 py-2 text-right">{{ row.amount.formatted }}</td>
            <td class="px-3 py-2 text-right">{{ formatPercent(row.share) }}</td>
            <td class="px-3 py-2 text-right">{{ row.count }}</td>
        </tr>
    </ReportTable>
</template>
