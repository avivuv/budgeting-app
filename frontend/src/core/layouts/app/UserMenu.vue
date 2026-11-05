<script setup lang="ts">
import { ChevronsUpDown, LogOut, Settings } from '@lucide/vue';
import { useQueryClient } from '@tanstack/vue-query';
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { Avatar, AvatarFallback } from '@/core/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/core/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/core/components/ui/sidebar';
import { getInitials } from '@/core/composables/useInitials';
import { useSession } from '@/core/stores/session';
import NavLink from './NavLink.vue';
import { useNavigation } from './useNavigation';

const session = useSession();
const queryClient = useQueryClient();
const router = useRouter();
const { navigation } = useNavigation();
const { isMobile, state } = useSidebar();

const initials = computed(() => getInitials(session.user?.name));

async function logout(): Promise<void> {
    await session.logout();
    queryClient.clear();
    await router.push({ name: 'login' });
}
</script>

<template>
    <SidebarMenu v-if="session.user">
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="user-menu"
                    >
                        <Avatar class="size-8 rounded-lg">
                            <AvatarFallback
                                class="rounded-lg bg-primary-soft text-primary"
                                >{{ initials }}</AvatarFallback
                            >
                        </Avatar>
                        <div
                            class="grid flex-1 text-left text-sm leading-tight"
                        >
                            <span class="truncate font-medium">{{
                                session.user.name
                            }}</span>
                            <span
                                class="truncate text-xs text-muted-foreground"
                                >{{ session.user.email }}</span
                            >
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <DropdownMenuLabel class="font-normal">
                        <div class="grid text-sm leading-tight">
                            <span class="truncate font-medium">{{
                                session.user.name
                            }}</span>
                            <span
                                class="truncate text-xs text-muted-foreground"
                                >{{ session.user.email }}</span
                            >
                        </div>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <NavLink
                            :item="navigation.settings"
                            class="w-full cursor-pointer"
                        >
                            <Settings class="size-4" />
                            {{ navigation.settings.title }}
                        </NavLink>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        class="cursor-pointer"
                        data-test="logout-button"
                        @click="logout"
                    >
                        <LogOut class="size-4" />
                        Keluar
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
