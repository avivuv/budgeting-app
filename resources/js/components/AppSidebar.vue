<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    ChartPie,
    LayoutGrid,
    PiggyBank,
    Tags,
    Users,
    Wallet,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import FamilySwitcher from '@/components/FamilySwitcher.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as budgetsIndex } from '@/routes/budgets';
import { index as categoriesIndex } from '@/routes/categories';
import { index as membersIndex } from '@/routes/members';
import { index as reportsIndex } from '@/routes/reports';
import { index as transactionsIndex } from '@/routes/transactions';
import { index as walletsIndex } from '@/routes/wallets';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Beranda',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Transaksi',
        href: transactionsIndex(),
        icon: ArrowLeftRight,
    },
    {
        title: 'Laporan',
        href: reportsIndex(),
        icon: ChartPie,
    },
    {
        title: 'Budget',
        href: budgetsIndex(),
        icon: PiggyBank,
    },
    {
        title: 'Dompet',
        href: walletsIndex(),
        icon: Wallet,
    },
    {
        title: 'Kategori',
        href: categoriesIndex(),
        icon: Tags,
    },
    {
        title: 'Anggota',
        href: membersIndex(),
        icon: Users,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <FamilySwitcher />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
