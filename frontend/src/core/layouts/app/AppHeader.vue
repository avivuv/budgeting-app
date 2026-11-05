<script setup lang="ts">
import { Bell, Check, Monitor, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { Button } from '@/core/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/core/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/core/components/ui/sidebar';
import { useTheme } from '@/core/composables/useTheme';
import { useSession } from '@/core/stores/session';
import NavLink from './NavLink.vue';
import { useNavigation } from './useNavigation';

const route = useRoute();
const session = useSession();
const { navigation } = useNavigation();
const { appearance, resolved, setAppearance } = useTheme();

const title = computed(() => (route.meta.title as string | undefined) ?? '');
const unread = computed(() => session.user?.unread_notifications ?? 0);

const themes = [
    { value: 'light', icon: Sun, label: 'Terang' },
    { value: 'dark', icon: Moon, label: 'Gelap' },
    { value: 'system', icon: Monitor, label: 'Ikuti sistem' },
] as const;
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
    >
        <SidebarTrigger class="-ml-1 hidden md:inline-flex" />
        <h1 class="truncate text-base font-semibold">{{ title }}</h1>

        <div class="ml-auto flex items-center gap-1">
            <NavLink
                :item="navigation.notifications"
                class="relative inline-flex size-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                :aria-label="
                    unread ? `Notifikasi, ${unread} belum dibaca` : 'Notifikasi'
                "
            >
                <Bell class="size-5" />
                <span
                    v-if="unread"
                    class="absolute top-0.5 right-0.5 flex min-w-4 items-center justify-center rounded-full bg-expense px-1 text-[10px] leading-4 font-semibold text-white"
                >
                    {{ unread > 9 ? '9+' : unread }}
                </span>
            </NavLink>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-9"
                        aria-label="Ganti tema"
                        title="Ganti tema"
                    >
                        <Moon v-if="resolved === 'dark'" class="size-5" />
                        <Sun v-else class="size-5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-44">
                    <DropdownMenuItem
                        v-for="theme in themes"
                        :key="theme.value"
                        class="cursor-pointer"
                        @click="setAppearance(theme.value)"
                    >
                        <component :is="theme.icon" class="size-4" />
                        <span>{{ theme.label }}</span>
                        <Check
                            v-if="appearance === theme.value"
                            class="ml-auto size-4 text-primary"
                        />
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
