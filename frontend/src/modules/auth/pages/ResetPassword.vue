<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import { api, useApiForm } from '@/core/api';
import InputError from '@/core/components/form/InputError.vue';
import PasswordInput from '@/core/components/form/PasswordInput.vue';
import { Button } from '@/core/components/ui/button';
import { Input } from '@/core/components/ui/input';
import { Label } from '@/core/components/ui/label';
import { Spinner } from '@/core/components/ui/spinner';

/** Opened from the email link: /reset-password/{token}?email=... */
const route = useRoute();
const router = useRouter();

const form = useApiForm({
    token: String(route.params.token ?? ''),
    email: typeof route.query.email === 'string' ? route.query.email : '',
    password: '',
    password_confirmation: '',
});

async function submit(): Promise<void> {
    const response = await form.submit((values) =>
        api.post<{ message: string }>(
            '/auth/reset-password',
            { ...values },
            { quiet: true },
        ),
    );

    if (response) {
        toast.success(response.message);
        await router.push({ name: 'login' });
    }
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input
                id="email"
                v-model="form.values.email"
                type="email"
                required
                readonly
                autocomplete="email"
            />
            <InputError :message="form.errors.value.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Password baru</Label>
            <PasswordInput
                id="password"
                v-model="form.values.password"
                required
                autofocus
                autocomplete="new-password"
            />
            <InputError :message="form.errors.value.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">Ulangi password</Label>
            <PasswordInput
                id="password_confirmation"
                v-model="form.values.password_confirmation"
                required
                autocomplete="new-password"
            />
        </div>

        <Button type="submit" class="w-full" :disabled="form.processing.value">
            <Spinner v-if="form.processing.value" />
            Simpan password
        </Button>
    </form>
</template>
