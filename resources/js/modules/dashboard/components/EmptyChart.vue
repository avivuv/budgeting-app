<script setup lang="ts">
defineProps<{ text?: string }>();
</script>

<template>
    <div
        class="flex min-h-40 items-center justify-center rounded-xl border border-dashed text-center text-sm text-muted-foreground"
    >
        {{ text ?? 'Belum ada data di periode ini.' }}
    </div>
</template>
