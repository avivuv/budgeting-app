<script setup lang="ts">
import { RouterLink } from 'vue-router';
import type { RouteLocationRaw } from 'vue-router';

defineProps<{
    to: RouteLocationRaw;
    tabindex?: number;
}>();
</script>

<template>
    <RouterLink
        :to="to"
        :tabindex="tabindex"
        class="text-foreground underline decoration-border underline-offset-4 transition-colors hover:decoration-current"
    >
        <slot />
    </RouterLink>
</template>
