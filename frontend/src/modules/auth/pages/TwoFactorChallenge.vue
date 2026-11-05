<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useApiForm } from '@/core/api';
import TextLink from '@/core/components/common/TextLink.vue';
import InputError from '@/core/components/form/InputError.vue';
import { Button } from '@/core/components/ui/button';
import { Input } from '@/core/components/ui/input';
import { Label } from '@/core/components/ui/label';
import { Spinner } from '@/core/components/ui/spinner';
import { useSession } from '@/core/stores/session';
import { redirectAfterLogin } from '../redirect';

/** Second step of signing in when two-factor is on (POST /auth/two-factor-challenge). */
const session = useSession();
const router = useRouter();
const route = useRoute();

const useRecovery = ref(false);
const form = useApiForm({ code: '', recovery_code: '' });

async function submit(): Promise<void> {
    const done = await form.submit(async (values) => {
        await session.twoFactorChallenge(
            useRecovery.value
                ? { recovery_code: values.recovery_code }
                : { code: values.code },
        );

        return true;
    });

    if (done) {
        await redirectAfterLogin(router, route);
    }
}

function toggle(): void {
    useRecovery.value = !useRecovery.value;
    form.clearErrors();
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <p
            v-if="form.errors.value.general"
            class="text-center text-sm text-destructive"
        >
            {{ form.errors.value.general }}
            <TextLink :to="{ name: 'login' }">Masuk lagi</TextLink>
        </p>

        <div v-if="!useRecovery" class="grid gap-2">
            <Label for="code">Kode 6 digit</Label>
            <Input
                id="code"
                v-model="form.values.code"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                required
                autofocus
            />
            <InputError :message="form.errors.value.code" />
        </div>

        <div v-else class="grid gap-2">
            <Label for="recovery_code">Kode pemulihan</Label>
            <Input
                id="recovery_code"
                v-model="form.values.recovery_code"
                autocomplete="off"
                required
                autofocus
            />
            <InputError :message="form.errors.value.recovery_code" />
        </div>

        <Button type="submit" class="w-full" :disabled="form.processing.value">
            <Spinner v-if="form.processing.value" />
            Lanjutkan
        </Button>

        <button
            type="button"
            class="text-sm text-muted-foreground underline underline-offset-4"
            @click="toggle"
        >
            {{
                useRecovery
                    ? 'Pakai kode dari aplikasi'
                    : 'Pakai kode pemulihan'
            }}
        </button>
    </form>
</template>
