<script setup lang="ts">
import { Menu, Plus } from '@lucide/vue';
import { useSidebar } from '@/core/components/ui/sidebar';
import NavLink from './NavLink.vue';
import { useNavigation } from './useNavigation';

/*
 * Phone navigation (docs/14 §4): two destinations, the "+ Transaksi" button
 * in the middle, one destination and "Lainnya" (opens the sidebar sheet).
 */
const { navigation, allowed, isActive } = useNavigation();
const { setOpenMobile } = useSidebar();

const itemClass =
    'flex h-16 flex-col items-center justify-center gap-1 text-[11px] font-medium';
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
        aria-label="Navigasi utama"
    >
        <ul class="grid h-16 grid-cols-5 items-center">
            <li v-for="item in navigation.bottom.left" :key="item.title">
                <NavLink
                    :item="item"
                    :class="[
                        itemClass,
                        isActive(item)
                            ? 'text-primary'
                            : 'text-muted-foreground',
                    ]"
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    <component :is="item.icon" class="size-5" />
                    {{ item.title }}
                </NavLink>
            </li>

            <li class="flex justify-center">
                <NavLink
                    v-if="allowed(navigation.create)"
                    :item="navigation.create"
                    class="-mt-6 flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg ring-4 ring-background transition-colors hover:bg-primary-hover focus-visible:ring-ring focus-visible:outline-none"
                    :aria-label="navigation.create.title"
                >
                    <Plus class="size-6" />
                </NavLink>
            </li>

            <li v-for="item in navigation.bottom.right" :key="item.title">
                <NavLink
                    :item="item"
                    :class="[
                        itemClass,
                        isActive(item)
                            ? 'text-primary'
                            : 'text-muted-foreground',
                    ]"
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    <component :is="item.icon" class="size-5" />
                    {{ item.title }}
                </NavLink>
            </li>

            <li>
                <button
                    type="button"
                    :class="[itemClass, 'w-full text-muted-foreground']"
                    @click="setOpenMobile(true)"
                >
                    <Menu class="size-5" />
                    Lainnya
                </button>
            </li>
        </ul>
    </nav>
</template>
