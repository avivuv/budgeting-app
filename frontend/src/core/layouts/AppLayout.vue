<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { SidebarInset, SidebarProvider } from '@/core/components/ui/sidebar';
import { Toaster } from '@/core/components/ui/sonner';
import AppHeader from './app/AppHeader.vue';
import AppSidebar from './app/AppSidebar.vue';
import BottomNav from './app/BottomNav.vue';
import NavLink from './app/NavLink.vue';
import { useNavigation } from './app/useNavigation';

/*
 * Shell of every page inside a family (docs/14 §4): sidebar on desktop,
 * bottom bar on phones, "+ Transaksi" button, header with notifications
 * and theme.
 */
const { navigation, allowed } = useNavigation();
</script>

<template>
    <SidebarProvider>
        <AppSidebar />
        <!-- Bottom padding keeps content clear of the phone bottom bar. -->
        <SidebarInset class="min-w-0 overflow-x-clip pb-20 md:pb-0">
            <AppHeader />
            <main class="flex flex-1 flex-col gap-4 p-4">
                <slot />
            </main>
        </SidebarInset>
        <BottomNav />
        <NavLink
            v-if="allowed(navigation.create)"
            :item="navigation.create"
            class="fixed right-6 bottom-6 z-40 hidden size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition-colors hover:bg-primary-hover focus-visible:ring-4 focus-visible:ring-ring focus-visible:outline-none md:flex"
            :aria-label="navigation.create.title"
            :title="navigation.create.title"
        >
            <Plus class="size-6" />
        </NavLink>
        <Toaster position="top-center" />
    </SidebarProvider>
</template>
