<script setup lang="ts">
import { computed, provide } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import AppLayout from '@/core/layouts/AppLayout.vue';
import AuthLayout from '@/core/layouts/AuthLayout.vue';
import { NAVIGATION } from '@/core/layouts/navigation';
import { navigation } from '@/navigation';

provide(NAVIGATION, navigation);

const route = useRoute();
const layout = computed(() =>
    route.meta.layout === 'auth' ? AuthLayout : AppLayout,
);
</script>

<template>
    <component :is="layout" v-if="route.matched.length > 0">
        <RouterView />
    </component>
</template>
