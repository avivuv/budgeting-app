<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';
import { index } from '@/routes/notifications';

/** Header bell with the unread count (docs/07 §4b). */
const page = usePage();
const unread = computed(() => page.props.unreadNotifications ?? 0);
</script>

<template>
    <Link
        :href="index()"
        class="relative inline-flex size-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground"
        :aria-label="
            unread ? `Notifikasi, ${unread} belum dibaca` : 'Notifikasi'
        "
    >
        <Bell class="size-5" />
        <span
            v-if="unread"
            class="absolute top-0.5 right-0.5 flex min-w-4 items-center justify-center rounded-full bg-expense px-1 text-[10px] leading-4 font-semibold text-white"
        >
            {{ unread > 9 ? '9+' : unread }}
        </span>
    </Link>
</template>
