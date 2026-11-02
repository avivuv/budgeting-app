<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { create as createTransaction } from '@/routes/transactions';

/*
 * Desktop/tablet "+ Transaksi" FAB (docs/14 §4). On phones the same action
 * sits in the middle of the bottom navigation.
 */
const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const visible = computed(
    () =>
        (page.props.family?.can.write ?? false) &&
        !isCurrentUrl(createTransaction()),
);
</script>

<template>
    <Link
        v-if="visible"
        :href="createTransaction()"
        class="fixed right-6 bottom-6 z-40 hidden size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition-colors hover:bg-primary-hover focus-visible:ring-4 focus-visible:ring-ring focus-visible:outline-none md:flex"
        aria-label="Catat transaksi"
        title="Catat transaksi"
    >
        <Plus class="size-6" />
    </Link>
</template>
