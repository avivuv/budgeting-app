<script setup lang="ts">
/** Message of one field from a 422 (useApiForm errors). Uses the token colour. */
defineProps<{
    message?: string;
}>();
</script>

<template>
    <p v-if="message" class="text-sm text-destructive" role="alert">
        {{ message }}
    </p>
</template>
