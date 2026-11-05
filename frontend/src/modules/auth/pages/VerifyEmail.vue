<script setup lang="ts">
import { useQueryClient } from '@tanstack/vue-query';
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '@/core/api';
import { Button } from '@/core/components/ui/button';
import { Spinner } from '@/core/components/ui/spinner';
import { useSession } from '@/core/stores/session';

/*
 * New accounts confirm their email first (same as the web). The link in the
 * email is handled by the backend; "Sudah" re-reads /me.
 */
const session = useSession();
const router = useRouter();
const queryClient = useQueryClient();

const status = ref<string | null>(null);
const sending = ref(false);

async function resend(): Promise<void> {
    sending.value = true;
    try {
        const response = await api.post<{ message: string }>(
            '/auth/email/verification-notification',
        );
        status.value = response.message;
    } finally {
        sending.value = false;
    }
}

async function checkAgain(): Promise<void> {
    await session.load();
    if (session.user?.email_verified) {
        await router.push({ name: 'home' });
    } else {
        status.value =
            'Email belum terverifikasi. Buka link di email kamu dulu.';
    }
}

async function logout(): Promise<void> {
    await session.logout();
    queryClient.clear();
    await router.push({ name: 'login' });
}
</script>

<template>
    <div class="flex flex-col gap-6 text-center">
        <p class="text-sm text-muted-foreground">
            Kami sudah mengirim link verifikasi ke
            <strong class="text-foreground">{{ session.user?.email }}</strong
            >. Buka link tersebut untuk melanjutkan.
        </p>

        <p
            v-if="status"
            class="rounded-md bg-primary-soft p-3 text-sm text-primary"
        >
            {{ status }}
        </p>

        <Button @click="checkAgain">Sudah, lanjutkan</Button>
        <Button variant="outline" :disabled="sending" @click="resend">
            <Spinner v-if="sending" />
            Kirim ulang email verifikasi
        </Button>
        <button
            type="button"
            class="text-sm text-muted-foreground underline underline-offset-4"
            @click="logout"
        >
            Keluar
        </button>
    </div>
</template>
