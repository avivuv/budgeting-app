<script setup lang="ts">
/*
 * Plain, scrollable report table. Cells are already formatted by the server;
 * numeric columns are right-aligned.
 */
defineProps<{
    headings: string[];
    /** Indices of right-aligned (numeric) columns. */
    numeric?: number[];
    caption: string;
}>();

defineSlots<{
    default: () => unknown;
    footer?: () => unknown;
}>();
</script>

<template>
    <div class="overflow-x-auto rounded-2xl border">
        <table class="w-full text-sm">
            <caption class="sr-only">
                {{
                    caption
                }}
            </caption>
            <thead class="bg-muted/60 text-xs text-muted-foreground">
                <tr>
                    <th
                        v-for="(heading, i) in headings"
                        :key="heading"
                        scope="col"
                        class="px-3 py-2 font-medium whitespace-nowrap"
                        :class="
                            numeric?.includes(i) ? 'text-right' : 'text-left'
                        "
                    >
                        {{ heading }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y tabular-nums">
                <slot />
            </tbody>
            <tfoot
                v-if="$slots.footer"
                class="border-t-2 font-semibold tabular-nums"
            >
                <slot name="footer" />
            </tfoot>
        </table>
    </div>
</template>
