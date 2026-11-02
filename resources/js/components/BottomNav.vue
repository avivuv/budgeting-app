<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeftRight, ChartPie, LayoutGrid, Menu, Plus } from '@lucide/vue';
import { computed } from 'vue';
import { useSidebar } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import {
    create as createTransaction,
    index as transactionsIndex,
} from '@/routes/transactions';
import { index as reportsIndex } from '@/routes/reports';

/*
 * Phone navigation (docs/14 §4): four destinations with the "+ Transaksi" FAB
 * in the middle. "Lainnya" opens the sidebar sheet with the remaining menu.
 * Shown below the sidebar's mobile breakpoint (md).
 */
const page = usePage();
const { setOpenMobile } = useSidebar();
const { isCurrentOrParentUrl } = useCurrentUrl();

const canWrite = computed(() => page.props.family?.can.write ?? false);

const left = [
    { title: 'Beranda', href: dashboard(), icon: LayoutGrid },
    { title: 'Transaksi', href: transactionsIndex(), icon: ArrowLeftRight },
];
const right = [{ title: 'Laporan', href: reportsIndex(), icon: ChartPie }];
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
        aria-label="Navigasi utama"
    >
        <ul class="grid h-16 grid-cols-5 items-center">
            <li v-for="item in left" :key="item.title">
                <Link
                    :href="item.href"
                    class="flex h-16 flex-col items-center justify-center gap-1 text-[11px] font-medium"
                    :class="
                        isCurrentOrParentUrl(item.href)
                            ? 'text-primary'
                            : 'text-muted-foreground'
                    "
                    :aria-current="
                        isCurrentOrParentUrl(item.href) ? 'page' : undefined
                    "
                >
                    <component :is="item.icon" class="size-5" />
                    {{ item.title }}
                </Link>
            </li>

            <li class="flex justify-center">
                <Link
                    v-if="canWrite"
                    :href="createTransaction()"
                    class="-mt-6 flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg ring-4 ring-background transition-colors hover:bg-primary-hover focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="Catat transaksi"
                >
                    <Plus class="size-6" />
                </Link>
            </li>

            <li v-for="item in right" :key="item.title">
                <Link
                    :href="item.href"
                    class="flex h-16 flex-col items-center justify-center gap-1 text-[11px] font-medium"
                    :class="
                        isCurrentOrParentUrl(item.href)
                            ? 'text-primary'
                            : 'text-muted-foreground'
                    "
                    :aria-current="
                        isCurrentOrParentUrl(item.href) ? 'page' : undefined
                    "
                >
                    <component :is="item.icon" class="size-5" />
                    {{ item.title }}
                </Link>
            </li>

            <li>
                <button
                    type="button"
                    class="flex h-16 w-full flex-col items-center justify-center gap-1 text-[11px] font-medium text-muted-foreground"
                    @click="setOpenMobile(true)"
                >
                    <Menu class="size-5" />
                    Lainnya
                </button>
            </li>
        </ul>
    </nav>
</template>
