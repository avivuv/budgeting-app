<script setup lang="ts">
import { statusTone } from '../status';

defineProps<{
    status: App.Enums.ReceiptStatus;
    label: string;
}>();
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-medium"
        :class="statusTone[status]"
    >
        {{ label }}
    </span>
</template>
