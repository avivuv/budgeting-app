import {
    ArrowLeftRight,
    Bell,
    ChartPie,
    LayoutGrid,
    PiggyBank,
    Plus,
    ScanLine,
    Settings,
    Target,
    Tags,
    Users,
    Wallet,
} from '@lucide/vue';
import type { Navigation } from '@/core/layouts/navigation';

/*
 * App menu. Pages already in the SPA use `to`; the rest still open the old
 * web page (`href`) until their module moves over in M4 (docs/18 §4).
 */
const home = { title: 'Beranda', icon: LayoutGrid, to: { name: 'home' } };
const transactions = {
    title: 'Transaksi',
    icon: ArrowLeftRight,
    href: '/transactions',
};
const reports = { title: 'Laporan', icon: ChartPie, href: '/reports' };

export const navigation: Navigation = {
    main: [
        home,
        transactions,
        { title: 'Scan struk', icon: ScanLine, href: '/receipts' },
        reports,
        { title: 'Budget', icon: PiggyBank, href: '/budgets' },
        { title: 'Tabungan', icon: Target, href: '/savings' },
        { title: 'Dompet', icon: Wallet, href: '/wallets' },
        { title: 'Kategori', icon: Tags, href: '/categories' },
        { title: 'Anggota', icon: Users, href: '/members' },
    ],
    bottom: { left: [home, transactions], right: [reports] },
    create: {
        title: 'Catat transaksi',
        icon: Plus,
        href: '/transactions/create',
        requires: 'write',
    },
    notifications: { title: 'Notifikasi', icon: Bell, href: '/notifications' },
    settings: {
        title: 'Pengaturan',
        icon: Settings,
        href: '/settings/profile',
    },
    createFamily: {
        title: 'Buat keluarga baru',
        icon: Plus,
        to: { name: 'onboarding' },
    },
};
