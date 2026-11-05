<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useApiForm } from '@/core/api';
import TextLink from '@/core/components/common/TextLink.vue';
import InputError from '@/core/components/form/InputError.vue';
import PasswordInput from '@/core/components/form/PasswordInput.vue';
import { Button } from '@/core/components/ui/button';
import { Input } from '@/core/components/ui/input';
import { Label } from '@/core/components/ui/label';
import { Spinner } from '@/core/components/ui/spinner';
import { useSession } from '@/core/stores/session';

const session = useSession();
const router = useRouter();

const form = useApiForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

async function submit(): Promise<void> {
    const done = await form.submit(async (values) => {
        await session.register({ ...values });

        return true;
    });

    if (done) {
        // New accounts verify their email, then create a family.
        await router.push({ name: 'home' });
    }
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="name">Nama</Label>
            <Input
                id="name"
                v-model="form.values.name"
                required
                autofocus
                autocomplete="name"
            />
            <InputError :message="form.errors.value.name" />
        </div>

        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input
                id="email"
                v-model="form.values.email"
                type="email"
                required
                autocomplete="email"
                placeholder="email@contoh.com"
            />
            <InputError :message="form.errors.value.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Password</Label>
            <PasswordInput
                id="password"
                v-model="form.values.password"
                required
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
            <InputError :message="form.errors.value.password_confirmation" />
        </div>

        <Button type="submit" class="w-full" :disabled="form.processing.value">
            <Spinner v-if="form.processing.value" />
            Daftar
        </Button>

        <p class="text-center text-sm text-muted-foreground">
            Sudah punya akun?
            <TextLink :to="{ name: 'login' }">Masuk</TextLink>
        </p>
    </form>
</template>
