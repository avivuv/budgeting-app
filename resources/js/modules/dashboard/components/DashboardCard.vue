<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

defineProps<{ title: string; description?: string }>();

defineSlots<{
    default: () => unknown;
    /** Right side of the header, e.g. a "see all" link. */
    action?: () => unknown;
}>();
</script>

<template>
    <Card class="gap-4 rounded-2xl py-4 shadow-sm md:py-6">
        <CardHeader
            class="flex flex-row items-start justify-between gap-2 px-4 md:px-6"
        >
            <div>
                <CardTitle class="text-base font-semibold">{{
                    title
                }}</CardTitle>
                <p
                    v-if="description"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>
            <slot name="action" />
        </CardHeader>
        <CardContent class="px-4 md:px-6">
            <slot />
        </CardContent>
    </Card>
</template>
