<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import InvitationController from '@/actions/App/Http/Controllers/Web/Family/InvitationController';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Undangan keluarga',
        description: 'Bergabung untuk mencatat keuangan bersama.',
    },
});

defineProps<{
    token: string;
    familyName: string | null;
    roleLabel: string;
    email: string;
    problem: string | null;
}>();
</script>

<template>
    <Head title="Undangan keluarga" />

    <div class="flex flex-col gap-6 text-center">
        <p>
            Kamu diundang bergabung ke
            <b>{{ familyName }}</b> sebagai <b>{{ roleLabel }}</b
            >.
        </p>

        <Alert v-if="problem" variant="destructive" class="text-left">
            <TriangleAlert class="size-4" />
            <AlertDescription>{{ problem }}</AlertDescription>
        </Alert>

        <Form
            v-else
            v-bind="InvitationController.accept.form(token)"
            v-slot="{ errors, processing }"
        >
            <Button type="submit" class="w-full" :disabled="processing">
                <Spinner v-if="processing" />
                Terima undangan
            </Button>
            <InputError class="mt-2" :message="errors.general" />
        </Form>

        <p class="text-xs text-muted-foreground">Undangan untuk {{ email }}.</p>
    </div>
</template>
