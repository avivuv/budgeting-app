<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Plus, Users } from '@lucide/vue';
import { computed } from 'vue';
import SwitchFamilyController from '@/actions/App/Http/Controllers/Web/Family/SwitchFamilyController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { create as createFamily } from '@/routes/onboarding';

const page = usePage();
const family = computed(() => page.props.family);
const families = computed(() => page.props.families ?? []);

function switchTo(id: number) {
    if (id !== family.value?.id) {
        router.put(SwitchFamilyController.url(id));
    }
}
</script>

<template>
    <SidebarMenu v-if="family">
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent"
                        tooltip="Ganti keluarga"
                    >
                        <div
                            class="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary-soft text-primary"
                        >
                            <Users class="size-4" />
                        </div>
                        <div
                            class="grid flex-1 text-left text-sm leading-tight"
                        >
                            <span class="truncate font-semibold">
                                {{ family.name }}
                            </span>
                            <span
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ family.role_label }}
                            </span>
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-60" align="start">
                    <DropdownMenuLabel class="text-xs text-muted-foreground">
                        Keluarga
                    </DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="item in families"
                        :key="item.id"
                        class="cursor-pointer"
                        @click="switchTo(item.id)"
                    >
                        <span class="truncate">{{ item.name }}</span>
                        <Check
                            v-if="item.id === family.id"
                            class="ml-auto size-4 text-primary"
                        />
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link
                            :href="createFamily({ query: { new: 1 } })"
                            class="cursor-pointer"
                        >
                            <Plus class="size-4" />
                            Buat keluarga baru
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
