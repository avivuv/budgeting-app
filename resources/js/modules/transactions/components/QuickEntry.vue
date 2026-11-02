<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { parse } from '@/routes/transactions';

type Parsed = App.DTOs.Transaction.ParsedTransactionData;

/*
 * "bensin 50rb @gopay kemarin" → form fields, read by the same parser the
 * API and Telegram bot use (docs/15 §7). Nothing is saved here.
 */
const emit = defineEmits<{
    parsed: [item: Parsed];
}>();

const http = useHttp({ text: '' });
const hint = ref<string | null>(null);

const fieldLabels: Record<string, string> = {
    amount: 'nominal',
    category_id: 'kategori',
    to_wallet_id: 'dompet tujuan',
};

function submit() {
    if (http.text.trim() === '') {
        return;
    }

    hint.value = null;
    http.post(parse().url, {
        onSuccess: (response) => {
            const item = (response as { data: Parsed[] }).data[0];

            if (!item) {
                return;
            }

            emit('parsed', item);
            hint.value = item.complete
                ? 'Form sudah terisi. Periksa lalu simpan.'
                : `Lengkapi ${item.missing.map((f) => fieldLabels[f] ?? f).join(', ')} di bawah.`;
        },
    });
}
</script>

<template>
    <form class="grid gap-2" @submit.prevent="submit">
        <label for="quick-entry" class="text-sm font-medium">Ketik cepat</label>
        <div class="flex gap-2">
            <Input
                id="quick-entry"
                v-model="http.text"
                autocomplete="off"
                placeholder="bensin 50rb @gopay kemarin"
                class="h-11"
            />
            <Button
                type="submit"
                variant="secondary"
                class="h-11 shrink-0"
                :disabled="http.processing"
            >
                <Spinner v-if="http.processing" />
                <Sparkles v-else class="size-4" />
                Isi form
            </Button>
        </div>
        <InputError :message="http.errors.text" />
        <p class="text-xs text-muted-foreground">
            {{
                hint ??
                'Contoh: "makan siang 35rb", "+ gaji 8jt @bca", "tf 500rb bca ke gopay", "!transportasi parkir 5rb #kantor".'
            }}
        </p>
    </form>
</template>
