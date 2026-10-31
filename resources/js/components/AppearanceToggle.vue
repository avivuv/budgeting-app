<script setup lang="ts">
import { Check, Monitor, Moon, Sun } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, resolvedAppearance, updateAppearance } = useAppearance();

const options = [
    { value: 'light', Icon: Sun, label: 'Terang' },
    { value: 'dark', Icon: Moon, label: 'Gelap' },
    { value: 'system', Icon: Monitor, label: 'Ikuti sistem' },
] as const;
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button
                variant="ghost"
                size="icon"
                class="h-9 w-9 cursor-pointer"
                aria-label="Ganti tema"
                title="Ganti tema"
            >
                <Moon v-if="resolvedAppearance === 'dark'" class="size-5" />
                <Sun v-else class="size-5" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-44">
            <DropdownMenuItem
                v-for="{ value, Icon, label } in options"
                :key="value"
                class="cursor-pointer"
                @click="updateAppearance(value)"
            >
                <component :is="Icon" class="size-4" />
                <span>{{ label }}</span>
                <Check
                    v-if="appearance === value"
                    class="ml-auto size-4 text-primary"
                />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
