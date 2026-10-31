<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Check, Copy, KeyRound, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ApiTokenController from '@/actions/App/Http/Controllers/Settings/ApiTokenController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/api-tokens';

type Token = {
    id: number;
    name: string;
    can_write: boolean;
    last_used: string;
    expires: string;
};

defineProps<{
    tokens: Token[];
    newToken: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Token API', href: index() }],
    },
});

const copied = ref(false);

async function copy(token: string) {
    await navigator.clipboard.writeText(token);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function revoke(token: Token) {
    if (
        confirm(
            `Cabut token "${token.name}"? Aplikasi yang memakainya akan berhenti bekerja.`,
        )
    ) {
        router.delete(ApiTokenController.destroy.url(token.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Token API" />

    <h1 class="sr-only">Token API</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Token API"
            description="Untuk aplikasi lain (shortcut HP, skrip) yang mengakses data lewat REST API /api/v1. Token berlaku 90 hari."
        />

        <Alert v-if="newToken">
            <KeyRound class="size-4" />
            <AlertTitle>Simpan token ini sekarang</AlertTitle>
            <AlertDescription class="space-y-2">
                <p>Token hanya ditampilkan sekali.</p>
                <div class="flex items-center gap-2">
                    <code
                        class="flex-1 truncate rounded bg-muted px-2 py-1 font-mono text-xs"
                    >
                        {{ newToken }}
                    </code>
                    <Button size="sm" variant="outline" @click="copy(newToken)">
                        <component :is="copied ? Check : Copy" class="size-4" />
                        {{ copied ? 'Tersalin' : 'Salin' }}
                    </Button>
                </div>
            </AlertDescription>
        </Alert>

        <Form
            v-bind="ApiTokenController.store.form()"
            class="space-y-4"
            :reset-on-success="true"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="token-name">Nama token</Label>
                <Input
                    id="token-name"
                    name="name"
                    maxlength="50"
                    placeholder="Shortcut iPhone"
                    required
                />
                <InputError :message="errors.name" />
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="can_write" value="0" />
                <Checkbox id="token-write" name="can_write" value="1" />
                <Label for="token-write" class="font-normal">
                    Boleh menambah/mengubah data (tanpa ini hanya bisa membaca)
                </Label>
            </div>

            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                Buat token
            </Button>
        </Form>

        <div class="divide-y rounded-lg border">
            <div
                v-for="token in tokens"
                :key="token.id"
                class="flex items-center gap-3 p-3"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ token.name }}</p>
                    <p class="text-xs text-muted-foreground">
                        Terakhir dipakai: {{ token.last_used }} · berlaku sampai
                        {{ token.expires }}
                    </p>
                </div>
                <Badge :variant="token.can_write ? 'default' : 'secondary'">
                    {{ token.can_write ? 'Baca & tulis' : 'Hanya baca' }}
                </Badge>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8 text-muted-foreground hover:text-destructive"
                    aria-label="Cabut token"
                    @click="revoke(token)"
                >
                    <Trash2 class="size-4" />
                </Button>
            </div>
            <p
                v-if="tokens.length === 0"
                class="p-4 text-center text-sm text-muted-foreground"
            >
                Belum ada token.
            </p>
        </div>
    </div>
</template>
