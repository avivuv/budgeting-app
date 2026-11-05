<script setup lang="ts">
import { Check, ChevronsUpDown, Plus, Users } from '@lucide/vue';
import { useQueryClient } from '@tanstack/vue-query';
import { toast } from 'vue-sonner';
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
} from '@/core/components/ui/sidebar';
import { useSession } from '@/core/stores/session';
import NavLink from './NavLink.vue';
import { useNavigation } from './useNavigation';

/** Active family (X-Family-Id of every request) and the switch between families. */
const session = useSession();
const queryClient = useQueryClient();
const { navigation } = useNavigation();

async function switchTo(id: number): Promise<void> {
    if (id === session.currentFamilyId) {
        return;
    }

    await session.switchFamily(id);
    // Answers of the previous family must not show up: reset everything and
    // refetch what is on screen (clear() would orphan the queries the page
    // just started for the new family).
    void queryClient.resetQueries();
    toast.success(
        `Sekarang di ${session.currentFamily?.name ?? 'keluarga lain'}.`,
    );
}
</script>

<template>
    <SidebarMenu v-if="session.currentFamily">
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent"
                        tooltip="Ganti keluarga"
                        data-test="family-switcher"
                    >
                        <div
                            class="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary-soft text-primary"
                        >
                            <Users class="size-4" />
                        </div>
                        <div
                            class="grid flex-1 text-left text-sm leading-tight"
                        >
                            <span class="truncate font-semibold">{{
                                session.currentFamily.name
                            }}</span>
                            <span
                                class="truncate text-xs text-muted-foreground"
                                >{{ session.currentFamily.role_label }}</span
                            >
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-60" align="start">
                    <DropdownMenuLabel class="text-xs text-muted-foreground"
                        >Keluarga</DropdownMenuLabel
                    >
                    <DropdownMenuItem
                        v-for="family in session.families"
                        :key="family.id"
                        class="cursor-pointer"
                        @click="switchTo(family.id)"
                    >
                        <span class="truncate">{{ family.name }}</span>
                        <Check
                            v-if="family.id === session.currentFamilyId"
                            class="ml-auto size-4 text-primary"
                        />
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <NavLink
                            :item="navigation.createFamily"
                            class="cursor-pointer"
                        >
                            <Plus class="size-4" />
                            {{ navigation.createFamily.title }}
                        </NavLink>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
