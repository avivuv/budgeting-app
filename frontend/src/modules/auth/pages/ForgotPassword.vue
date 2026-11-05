<script setup lang="ts">
import { ref } from 'vue';
import { api, useApiForm } from '@/core/api';
import TextLink from '@/core/components/common/TextLink.vue';
import InputError from '@/core/components/form/InputError.vue';
import { Button } from '@/core/components/ui/button';
import { Input } from '@/core/components/ui/input';
import { Label } from '@/core/components/ui/label';
import { Spinner } from '@/core/components/ui/spinner';

/** The answer is the same whether the email exists or not (no guessing). */
const status = ref<string | null>(null);
const form = useApiForm({ email: '' });

async function submit(): Promise<void> {
    const response = await form.submit((values) =>
        api.post<{ message: string }>(
            '/auth/forgot-password',
            { ...values },
            { quiet: true },
        ),
    );

    if (response) {
        status.value = response.message;
    }
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <p
            v-if="status"
            class="rounded-md bg-primary-soft p-3 text-center text-sm text-primary"
        >
            {{ status }}
        </p>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    v-model="form.values.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                />
                <InputError :message="form.errors.value.email" />
            </div>

            <Button
                type="submit"
                class="w-full"
                :disabled="form.processing.value"
            >
                <Spinner v-if="form.processing.value" />
                Kirim link reset password
            </Button>
        </form>

        <p class="text-center text-sm text-muted-foreground">
            Ingat passwordnya?
            <TextLink :to="{ name: 'login' }">Masuk</TextLink>
        </p>
    </div>
</template>
