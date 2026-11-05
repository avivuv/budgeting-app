<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router';
import { useApiForm } from '@/core/api';
import TextLink from '@/core/components/common/TextLink.vue';
import InputError from '@/core/components/form/InputError.vue';
import PasswordInput from '@/core/components/form/PasswordInput.vue';
import { Button } from '@/core/components/ui/button';
import { Checkbox } from '@/core/components/ui/checkbox';
import { Input } from '@/core/components/ui/input';
import { Label } from '@/core/components/ui/label';
import { Spinner } from '@/core/components/ui/spinner';
import { useSession } from '@/core/stores/session';
import { redirectAfterLogin } from '../redirect';

const session = useSession();
const router = useRouter();
const route = useRoute();

const form = useApiForm({ email: '', password: '', remember: false });

async function submit(): Promise<void> {
    const result = await form.submit((values) => session.login({ ...values }));

    if (result === 'two-factor') {
        await router.push({ name: 'two-factor', query: route.query });
    } else if (result === 'ok') {
        await redirectAfterLogin(router, route);
    }
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <p
            v-if="form.errors.value.general"
            class="text-center text-sm text-destructive"
        >
            {{ form.errors.value.general }}
        </p>

        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input
                id="email"
                v-model="form.values.email"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@contoh.com"
            />
            <InputError :message="form.errors.value.email" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-center justify-between">
                <Label for="password">Password</Label>
                <TextLink
                    :to="{ name: 'forgot-password' }"
                    class="text-sm"
                    :tabindex="5"
                    >Lupa password?</TextLink
                >
            </div>
            <PasswordInput
                id="password"
                v-model="form.values.password"
                required
                autocomplete="current-password"
            />
            <InputError :message="form.errors.value.password" />
        </div>

        <Label for="remember" class="flex items-center gap-3">
            <Checkbox id="remember" v-model="form.values.remember" />
            <span>Ingat saya</span>
        </Label>

        <Button
            type="submit"
            class="w-full"
            :disabled="form.processing.value"
            data-test="login-button"
        >
            <Spinner v-if="form.processing.value" />
            Masuk
        </Button>

        <p class="text-center text-sm text-muted-foreground">
            Belum punya akun?
            <TextLink :to="{ name: 'register' }">Daftar</TextLink>
        </p>
    </form>
</template>
