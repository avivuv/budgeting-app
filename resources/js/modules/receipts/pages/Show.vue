<script setup lang="ts">
import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import {
    CircleAlert,
    FileText,
    Plus,
    ScanLine,
    SplitSquareHorizontal,
    Trash2,
} from '@lucide/vue';
import { computed, onMounted, watch } from 'vue';
import ReceiptController from '@/actions/App/Http/Controllers/Web/Receipt/ReceiptController';
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatMoney } from '@/lib/money';
import { categoryOptions } from '@/modules/categories';
import { image, index } from '@/routes/receipts';
import {
    create as createTransaction,
    index as transactionsIndex,
} from '@/routes/transactions';
import ReceiptStatusBadge from '../components/ReceiptStatusBadge.vue';
import { flagMessages, isReading } from '../status';

type Receipt = App.DTOs.Receipt.ReceiptData;
type Category = App.DTOs.Category.CategoryItemData;
type Mode = App.Enums.ReceiptConfirmMode;

const props = defineProps<{
    receipt: Receipt;
    categories: Category[];
    wallets: { id: number; name: string }[];
    canEdit: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Scan struk', href: index() }],
    },
});

/*
 * Review of what was read (BR-H08, BR-H09): every field can be corrected and
 * nothing becomes a transaction until Simpan. Amounts and checks come from
 * the server; this page only edits and shows them.
 */
const { start, stop } = usePoll(
    2000,
    { only: ['receipt'] },
    { autoStart: false },
);

onMounted(() => {
    if (isReading(props.receipt.status)) {
        start();
    }
});

watch(
    () => props.receipt.status,
    (status) => {
        if (!isReading(status)) {
            stop();
        }
    },
);

type ItemRow = {
    name: string;
    qty: number | string;
    total: number | string;
    category_id: number | string | null;
};

function draftValues() {
    return {
        merchant: props.receipt.merchant ?? '',
        receipt_date: props.receipt.date ?? '',
        total: props.receipt.total !== null ? String(props.receipt.total) : '',
        category_id: props.receipt.categoryId as number | string | null,
        wallet_id: props.receipt.walletId as number | string | null,
        items: props.receipt.items.map((item): ItemRow => ({
            name: item.name,
            qty: item.qty,
            total: String(item.total),
            category_id: item.categoryId,
        })),
    };
}

const form = useForm(draftValues());

// The draft arrives by polling: fill the form once it is read.
watch(
    () => props.receipt.status,
    (status, previous) => {
        if (status === 'parsed' && previous !== 'parsed') {
            form.defaults(draftValues());
            form.reset();
        }
    },
);

const editable = computed(
    () => props.canEdit && props.receipt.status === 'parsed',
);
const categoryChoices = computed(() => categoryOptions(props.categories));
const walletChoices = computed(() =>
    props.wallets.map((wallet) => ({ value: wallet.id, label: wallet.name })),
);
// Rule errors of confirm/cancel come back as page errors (BR-H07, BR-H08).
const page = usePage();
const confirmError = computed(() => {
    const errors = page.props.errors as Record<string, string>;

    return (
        errors.status ??
        errors.mode ??
        errors.category_id ??
        errors.wallet_id ??
        errors.total
    );
});
const isPdf = computed(() => props.receipt.mimeType === 'application/pdf');
const imageUrl = computed(() => image.url(props.receipt.id));

function addItem() {
    form.items.push({ name: '', qty: 1, total: '', category_id: null });
}

function removeItem(index: number) {
    form.items.splice(index, 1);
}

function saveDraft() {
    form.put(ReceiptController.update.url(props.receipt.id), {
        preserveScroll: true,
    });
}

/*
 * Simpan: corrections first (if any), then confirm. Confirming twice is
 * harmless on the server (BR-H07).
 */
function confirm(mode: Mode) {
    const post = () =>
        router.post(ReceiptController.confirm.url(props.receipt.id), {
            mode,
        });

    if (form.isDirty) {
        form.put(ReceiptController.update.url(props.receipt.id), {
            preserveScroll: true,
            onSuccess: post,
        });

        return;
    }

    post();
}

function reject() {
    if (
        window.confirm('Batalkan struk ini? Tidak ada transaksi yang dibuat.')
    ) {
        router.delete(ReceiptController.destroy.url(props.receipt.id));
    }
}
</script>

<template>
    <Head title="Tinjau struk" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ receipt.merchant ?? 'Tinjau struk' }}
                </h1>
                <ReceiptStatusBadge
                    :status="receipt.status"
                    :label="receipt.statusLabel"
                />
            </div>
            <Button variant="outline" as-child>
                <Link :href="index()">
                    <ScanLine class="size-4" /> Scan lagi
                </Link>
            </Button>
        </div>

        <!-- Reading -->
        <Card v-if="isReading(receipt.status)" class="rounded-2xl">
            <CardContent
                class="flex flex-col items-center gap-3 py-10 text-center"
                role="status"
            >
                <Spinner class="size-6 text-primary" />
                <p class="font-medium">Membaca struk…</p>
                <p class="text-sm text-muted-foreground">
                    Biasanya beberapa detik. Halaman ini akan diperbarui
                    otomatis.
                </p>
            </CardContent>
        </Card>

        <!-- Failed -->
        <Card v-else-if="receipt.status === 'failed'" class="rounded-2xl">
            <CardContent
                class="flex flex-col items-center gap-3 py-10 text-center"
            >
                <CircleAlert class="size-6 text-destructive" />
                <p class="font-medium">Struk tidak terbaca</p>
                <p class="text-sm text-muted-foreground">
                    {{ receipt.error ?? 'Coba foto ulang dengan lebih jelas.' }}
                </p>
                <div class="flex flex-wrap justify-center gap-2">
                    <Button as-child>
                        <Link :href="createTransaction()">Ketik manual</Link>
                    </Button>
                    <Button v-if="canEdit" variant="outline" @click="reject">
                        Hapus dari daftar
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Confirmed / cancelled / expired -->
        <Card
            v-else-if="receipt.status !== 'parsed'"
            class="rounded-2xl"
            data-test="receipt-closed"
        >
            <CardContent
                class="flex flex-col items-center gap-3 py-10 text-center"
            >
                <template v-if="receipt.status === 'confirmed'">
                    <p class="font-medium">
                        Tersimpan sebagai
                        {{ receipt.transactionIds.length }} transaksi ·
                        {{ receipt.totalFormatted }}
                    </p>
                    <Button as-child>
                        <Link :href="transactionsIndex()">Lihat transaksi</Link>
                    </Button>
                </template>
                <template v-else>
                    <p class="font-medium">{{ receipt.statusLabel }}</p>
                    <p class="text-sm text-muted-foreground">
                        Tidak ada transaksi yang dibuat dari struk ini.
                    </p>
                </template>
            </CardContent>
        </Card>

        <!-- Review -->
        <div
            v-else
            class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]"
        >
            <Card class="overflow-hidden rounded-2xl py-0">
                <a
                    v-if="receipt.hasFile && isPdf"
                    :href="imageUrl"
                    target="_blank"
                    rel="noopener"
                    class="flex flex-col items-center gap-2 p-10 text-sm text-primary"
                >
                    <FileText class="size-8" /> Buka PDF
                </a>
                <a
                    v-else-if="receipt.hasFile"
                    :href="imageUrl"
                    target="_blank"
                    rel="noopener"
                >
                    <img
                        :src="imageUrl"
                        alt="Foto struk"
                        class="max-h-[32rem] w-full bg-muted object-contain"
                    />
                </a>
            </Card>

            <Card class="rounded-2xl">
                <CardContent class="flex flex-col gap-4">
                    <div
                        v-if="receipt.flags.length > 0"
                        class="flex flex-col gap-2 rounded-xl bg-warning/10 p-3 text-sm text-warning"
                        role="alert"
                    >
                        <p
                            v-for="flag in receipt.flags"
                            :key="flag"
                            class="flex gap-2"
                        >
                            <CircleAlert class="mt-0.5 size-4 shrink-0" />
                            {{ flagMessages[flag] ?? flag }}
                        </p>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Belum tersimpan sebagai transaksi. Periksa hasil baca,
                        ubah bila perlu, lalu tekan Simpan.
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="receipt-total">Total dibayar</Label>
                            <Input
                                id="receipt-total"
                                v-model="form.total"
                                inputmode="numeric"
                                class="h-11 text-lg font-semibold tabular-nums"
                                :disabled="!editable"
                            />
                            <InputError :message="form.errors.total" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="receipt-merchant"
                                >Toko / penerima</Label
                            >
                            <Input
                                id="receipt-merchant"
                                v-model="form.merchant"
                                maxlength="120"
                                :disabled="!editable"
                            />
                            <InputError :message="form.errors.merchant" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="receipt-date">Tanggal</Label>
                            <Input
                                id="receipt-date"
                                v-model="form.receipt_date"
                                type="date"
                                :disabled="!editable"
                            />
                            <InputError :message="form.errors.receipt_date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="receipt-category">Kategori</Label>
                            <SearchableSelect
                                id="receipt-category"
                                v-model="form.category_id"
                                :options="categoryChoices"
                                placeholder="Pilih kategori"
                                :disabled="!editable"
                            />
                            <InputError :message="form.errors.category_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="receipt-wallet">Dompet</Label>
                            <SearchableSelect
                                id="receipt-wallet"
                                v-model="form.wallet_id"
                                :options="walletChoices"
                                placeholder="Pilih dompet"
                                :disabled="!editable"
                            />
                            <InputError :message="form.errors.wallet_id" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold">
                                Item
                                <span
                                    v-if="receipt.items.length > 0"
                                    class="font-normal text-muted-foreground"
                                >
                                    · jumlah terbaca
                                    {{ receipt.items.length }} item
                                </span>
                            </h2>
                            <Button
                                v-if="editable"
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="addItem"
                            >
                                <Plus class="size-4" /> Item
                            </Button>
                        </div>

                        <p
                            v-if="form.items.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Tidak ada daftar item (mis. bukti transfer).
                        </p>

                        <div
                            v-for="(item, i) in form.items"
                            :key="i"
                            class="grid grid-cols-[1fr_4rem_7rem_auto] items-start gap-2 rounded-xl border p-2 sm:grid-cols-[1fr_4rem_7rem_10rem_auto]"
                            data-test="receipt-item"
                        >
                            <Input
                                v-model="item.name"
                                :aria-label="`Nama item ${i + 1}`"
                                placeholder="Nama item"
                                :disabled="!editable"
                            />
                            <Input
                                v-model="item.qty"
                                :aria-label="`Jumlah item ${i + 1}`"
                                inputmode="decimal"
                                :disabled="!editable"
                            />
                            <Input
                                v-model="item.total"
                                :aria-label="`Harga item ${i + 1}`"
                                inputmode="numeric"
                                class="tabular-nums"
                                :disabled="!editable"
                            />
                            <SearchableSelect
                                v-model="item.category_id"
                                :options="categoryChoices"
                                placeholder="Kategori struk"
                                clearable
                                class="col-span-3 row-start-2 sm:col-span-1 sm:row-start-auto"
                                :disabled="!editable"
                            />
                            <Button
                                v-if="editable"
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`Hapus item ${i + 1}`"
                                class="col-start-4 row-start-1 sm:col-start-auto"
                                @click="removeItem(i)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                            <InputError
                                class="col-span-full"
                                :message="
                                    form.errors[`items.${i}.name`] ??
                                    form.errors[`items.${i}.total`]
                                "
                            />
                        </div>
                        <p
                            v-if="receipt.items.length > 0"
                            class="text-xs text-muted-foreground tabular-nums"
                        >
                            Jumlah item tersimpan:
                            {{ formatMoney(receipt.itemsTotal) }}
                            <template v-if="receipt.discount > 0">
                                · diskon {{ formatMoney(receipt.discount) }}
                            </template>
                            <template v-if="receipt.tax > 0">
                                · pajak {{ formatMoney(receipt.tax) }}
                            </template>
                        </p>
                    </div>

                    <InputError :message="confirmError" />

                    <div
                        v-if="editable"
                        class="flex flex-col gap-2 pt-2 sm:flex-row sm:flex-wrap"
                    >
                        <Button
                            type="button"
                            class="sm:flex-1"
                            :disabled="form.processing"
                            @click="confirm('total')"
                        >
                            <Spinner v-if="form.processing" />
                            Simpan
                        </Button>
                        <Button
                            v-if="form.items.length > 0"
                            type="button"
                            variant="outline"
                            class="sm:flex-1"
                            :disabled="form.processing"
                            @click="confirm('split')"
                        >
                            <SplitSquareHorizontal class="size-4" />
                            Pecah per item
                        </Button>
                        <Button
                            v-if="form.isDirty"
                            type="button"
                            variant="ghost"
                            :disabled="form.processing"
                            @click="saveDraft"
                        >
                            Simpan perubahan saja
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            class="text-destructive"
                            @click="reject"
                        >
                            Batal
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
