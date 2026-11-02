<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Camera, FileUp, ReceiptText } from '@lucide/vue';
import { ref } from 'vue';
import ReceiptController from '@/actions/App/Http/Controllers/Web/Receipt/ReceiptController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { index, show } from '@/routes/receipts';
import ReceiptStatusBadge from '../components/ReceiptStatusBadge.vue';

type Receipt = App.DTOs.Receipt.ReceiptData;

defineProps<{
    receipts: Receipt[];
    quota: { used: number; limit: number; remaining: number };
    canScan: boolean;
    maxFileMb: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Scan struk', href: index() }],
    },
});

/*
 * Upload a receipt, transfer proof, e-wallet screenshot or invoice (BR-H11).
 * The server reads it into a draft; nothing is saved as a transaction until
 * the user confirms on the next page (BR-H08).
 */
const form = useForm<{ file: File | null }>({ file: null });
const dragging = ref(false);
const camera = ref<HTMLInputElement | null>(null);
const picker = ref<HTMLInputElement | null>(null);

function send(file: File | undefined | null) {
    if (!file || form.processing) {
        return;
    }

    form.file = file;
    form.post(ReceiptController.store.url(), {
        forceFormData: true,
        onFinish: () => {
            form.file = null;

            if (camera.value) {
                camera.value.value = '';
            }

            if (picker.value) {
                picker.value.value = '';
            }
        },
    });
}

function onChange(event: Event) {
    send((event.target as HTMLInputElement).files?.[0]);
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    send(event.dataTransfer?.files?.[0]);
}
</script>

<template>
    <Head title="Scan struk" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4 md:p-6">
        <Heading
            title="Scan struk"
            description="Foto struk, bukti transfer, screenshot e-wallet, atau invoice PDF. Hasil baca ditinjau dulu sebelum disimpan."
        />

        <Card v-if="canScan" class="rounded-2xl">
            <CardContent class="flex flex-col gap-4">
                <div
                    data-test="dropzone"
                    class="flex flex-col items-center gap-3 rounded-xl border-2 border-dashed px-4 py-8 text-center transition-colors"
                    :class="
                        dragging
                            ? 'border-primary bg-primary-soft'
                            : 'border-border'
                    "
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="onDrop"
                >
                    <span
                        class="flex size-12 items-center justify-center rounded-full bg-primary-soft text-primary"
                    >
                        <Spinner v-if="form.processing" class="size-5" />
                        <ReceiptText v-else class="size-5" />
                    </span>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="form.processing">
                            Mengunggah…
                        </template>
                        <template v-else>
                            Tarik file ke sini, atau pilih di bawah. JPG, PNG,
                            WEBP, HEIC, atau PDF (maks. 3 halaman),
                            {{ maxFileMb }} MB.
                        </template>
                    </p>

                    <div class="flex flex-wrap justify-center gap-2">
                        <Button
                            type="button"
                            class="md:hidden"
                            :disabled="form.processing || quota.remaining === 0"
                            @click="camera?.click()"
                        >
                            <Camera class="size-4" /> Ambil foto
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing || quota.remaining === 0"
                            @click="picker?.click()"
                        >
                            <FileUp class="size-4" /> Pilih file
                        </Button>
                    </div>

                    <input
                        ref="camera"
                        type="file"
                        accept="image/*"
                        capture="environment"
                        class="hidden"
                        aria-label="Ambil foto struk"
                        @change="onChange"
                    />
                    <input
                        ref="picker"
                        type="file"
                        accept="image/*,application/pdf"
                        class="hidden"
                        aria-label="Pilih file struk"
                        @change="onChange"
                    />
                </div>

                <InputError :message="form.errors.file" />

                <p class="text-xs text-muted-foreground">
                    Sisa kuota bulan ini:
                    <span class="font-medium text-foreground tabular-nums">
                        {{ quota.remaining }} dari {{ quota.limit }}
                    </span>
                    scan.
                </p>
            </CardContent>
        </Card>

        <h2 class="mt-2 text-sm font-semibold text-muted-foreground">
            Struk terakhir
        </h2>

        <div
            v-if="receipts.length === 0"
            class="rounded-2xl border border-dashed p-10 text-center"
        >
            <p class="font-medium">Belum ada struk</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Struk yang kamu unggah muncul di sini.
            </p>
        </div>

        <Card v-else class="gap-0 rounded-2xl py-0">
            <CardContent class="divide-y px-0">
                <Link
                    v-for="receipt in receipts"
                    :key="receipt.id"
                    :href="show(receipt.id)"
                    class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-accent md:px-6"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">
                            {{ receipt.merchant ?? 'Struk #' + receipt.id }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ receipt.documentTypeLabel ?? 'Struk' }}
                            <template v-if="receipt.date">
                                · {{ receipt.date }}
                            </template>
                        </p>
                    </div>
                    <span
                        v-if="receipt.totalFormatted"
                        class="text-sm font-semibold tabular-nums"
                    >
                        {{ receipt.totalFormatted }}
                    </span>
                    <ReceiptStatusBadge
                        :status="receipt.status"
                        :label="receipt.statusLabel"
                    />
                </Link>
            </CardContent>
        </Card>
    </div>
</template>
