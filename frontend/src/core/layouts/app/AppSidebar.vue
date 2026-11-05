<script setup lang="ts">
import { RouterLink } from 'vue-router';
import AppLogoIcon from '@/core/components/common/AppLogoIcon.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/core/components/ui/sidebar';
import FamilySwitcher from './FamilySwitcher.vue';
import NavLink from './NavLink.vue';
import UserMenu from './UserMenu.vue';
import { useNavigation } from './useNavigation';

const { navigation, allowed, isActive } = useNavigation();
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <RouterLink to="/">
                            <div
                                class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
                            >
                                <AppLogoIcon class="size-5 fill-current" />
                            </div>
                            <span class="ml-1 truncate font-semibold"
                                >Budgeting</span
                            >
                        </RouterLink>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <FamilySwitcher />
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup class="px-2 py-0">
                <SidebarGroupLabel>Menu</SidebarGroupLabel>
                <SidebarMenu>
                    <template v-for="item in navigation.main" :key="item.title">
                        <SidebarMenuItem v-if="allowed(item)">
                            <SidebarMenuButton
                                as-child
                                :is-active="isActive(item)"
                                :tooltip="item.title"
                            >
                                <NavLink :item="item">
                                    <component :is="item.icon" />
                                    <span>{{ item.title }}</span>
                                </NavLink>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </template>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <UserMenu />
        </SidebarFooter>
    </Sidebar>
</template>
