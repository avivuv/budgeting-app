<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    class?: string;
}>();

const model = defineModel<string | number | null>();
</script>

<template>
    <div :class="cn('relative', props.class)">
        <!--
            Without a v-model the browser keeps its own choice (first option or
            :selected). Binding an undefined model would select nothing and
            submit an empty value in plain <Form> posts.
        -->
        <select
            v-if="model !== undefined"
            v-model="model"
            v-bind="$attrs"
            class="h-9 w-full appearance-none rounded-md border border-input bg-transparent px-3 pr-9 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive dark:bg-input/30"
        >
            <slot />
        </select>
        <select
            v-else
            v-bind="$attrs"
            class="h-9 w-full appearance-none rounded-md border border-input bg-transparent px-3 pr-9 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive dark:bg-input/30"
        >
            <slot />
        </select>
        <ChevronDown
            class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
    </div>
</template>
