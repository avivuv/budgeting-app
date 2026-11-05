<script setup lang="ts">
import { RouterLink } from 'vue-router';
import type { NavItem } from '../navigation';

/** A menu destination: SPA route, or an old page during the migration. */
defineProps<{
    item: NavItem;
}>();
</script>

<template>
    <RouterLink v-if="item.to" :to="item.to" v-bind="$attrs">
        <slot />
    </RouterLink>
    <a v-else :href="item.href" v-bind="$attrs">
        <slot />
    </a>
</template>
