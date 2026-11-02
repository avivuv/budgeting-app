<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Bell, CheckCheck } from '@lucide/vue';
import NotificationController from '@/actions/App/Http/Controllers/Web/NotificationController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { index } from '@/routes/notifications';

type Item = {
    id: string;
    title: string;
    message: string;
    url: string | null;
    read: boolean;
    createdAt: string;
};

const props = defineProps<{
    notifications: Item[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Notifikasi', href: index() }],
    },
});

function open(item: Item) {
    router.post(
        NotificationController.read.url(),
        { id: item.id },
        {
            onSuccess: () => {
                if (item.url) {
                    router.visit(item.url);
                }
            },
        },
    );
}

function readAll() {
    router.post(
        NotificationController.read.url(),
        {},
        { preserveScroll: true },
    );
}

const hasUnread = () => props.notifications.some((n) => !n.read);
</script>

<template>
    <Head title="Notifikasi" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Notifikasi"
                description="Peringatan budget dan kabar lain dari keluarga."
            />
            <Button v-if="hasUnread()" variant="outline" @click="readAll">
                <CheckCheck class="size-4" /> Tandai semua dibaca
            </Button>
        </div>

        <div
            v-if="notifications.length === 0"
            class="flex flex-col items-center gap-2 rounded-2xl border border-dashed p-10 text-center text-muted-foreground"
        >
            <Bell class="size-6" />
            <p>Belum ada notifikasi.</p>
        </div>

        <Card v-else class="gap-0 divide-y overflow-hidden rounded-2xl py-0">
            <button
                v-for="item in notifications"
                :key="item.id"
                type="button"
                class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-muted/50"
                @click="open(item)"
            >
                <span
                    class="mt-1.5 size-2 shrink-0 rounded-full"
                    :class="item.read ? 'bg-transparent' : 'bg-primary'"
                    :aria-label="item.read ? undefined : 'Belum dibaca'"
                />
                <span class="min-w-0 flex-1">
                    <span
                        class="block text-sm"
                        :class="item.read ? '' : 'font-semibold'"
                        >{{ item.title }}</span
                    >
                    <span class="block text-sm text-muted-foreground">{{
                        item.message
                    }}</span>
                    <span class="mt-1 block text-xs text-muted-foreground">{{
                        item.createdAt
                    }}</span>
                </span>
            </button>
        </Card>
    </div>
</template>
