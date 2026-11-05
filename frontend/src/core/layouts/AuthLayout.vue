<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AppLogoIcon from '@/core/components/common/AppLogoIcon.vue';
import { Toaster } from '@/core/components/ui/sonner';

/*
 * Centered card for signing in, registering and onboarding. The page sets
 * its heading through route meta (title, description).
 */
const route = useRoute();
const title = computed(() => (route.meta.title as string | undefined) ?? '');
const description = computed(
    () => (route.meta.description as string | undefined) ?? '',
);
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <RouterLink
                        to="/"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div
                            class="mb-1 flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                        >
                            <AppLogoIcon class="size-6 fill-current" />
                        </div>
                        <span class="sr-only">Budgeting</span>
                    </RouterLink>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-semibold">{{ title }}</h1>
                        <p
                            v-if="description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
        <Toaster position="top-center" />
    </div>
</template>
