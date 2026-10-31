<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowLeftRight,
    ArrowUpRight,
    History,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TransactionController from '@/actions/App/Http/Controllers/Web/Transaction/TransactionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import type { SearchableOption } from '@/components/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { categoryOptions } from '@/modules/categories';
import { index } from '@/routes/transactions';

type Category = App.DTOs.Category.CategoryItemData;
type TransactionType = App.Enums.TransactionType;

const props = defineProps<{
    transaction: App.DTOs.Transaction.TransactionItemData | null;
    wallets: { id: number; name: string; type: App.Enums.WalletType }[];
    categories: Record<'expense' | 'income', Category[]>;
    today: string;
    history: {
        event: string;
        title: string;
        who: string;
        when: string;
        changes: { field: string; old: string; new: string }[];
    }[];
}>();

const isEdit = computed(() => props.transaction !== null);
const title = computed(() =>
    isEdit.value ? 'Ubah transaksi' : 'Tambah transaksi',
);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Transaksi', href: index() }],
    },
});

const type = ref<TransactionType>(props.transaction?.type ?? 'expense');
const addAnother = ref(false);

const types: {
    value: TransactionType;
    label: string;
    icon: typeof ArrowUpRight;
    active: string;
}[] = [
    {
        value: 'expense',
        label: 'Pengeluaran',
        icon: ArrowUpRight,
        active: 'border-expense bg-expense/10 text-expense',
    },
    {
        value: 'income',
        label: 'Pemasukan',
        icon: ArrowDownLeft,
        active: 'border-income bg-income/10 text-income',
    },
    {
        value: 'transfer',
        label: 'Transfer',
        icon: ArrowLeftRight,
        active: 'border-transfer bg-transfer/10 text-transfer',
    },
];

const walletId = ref<number | string | null>(
    props.transaction?.walletId ?? props.wallets[0]?.id ?? null,
);
const toWalletId = ref<number | string | null>(
    props.transaction?.toWalletId ?? null,
);
const categoryId = ref<number | string | null>(
    props.transaction?.categoryId ?? null,
);

// Keep the current value selectable even if it was archived meanwhile,
// so editing never silently switches wallet or category.
function withCurrent(
    options: SearchableOption[],
    id: number | null | undefined,
    name: string | null | undefined,
): SearchableOption[] {
    if (id == null || options.some((option) => option.value === id)) {
        return options;
    }

    return [...options, { value: id, label: `${name ?? '-'} (diarsipkan)` }];
}

const walletOptions = computed(() =>
    props.wallets.map((wallet) => ({ value: wallet.id, label: wallet.name })),
);
const fromWalletOptions = computed(() =>
    withCurrent(
        walletOptions.value,
        props.transaction?.walletId,
        props.transaction?.walletName,
    ),
);
const toWalletOptions = computed(() =>
    withCurrent(
        walletOptions.value,
        props.transaction?.toWalletId,
        props.transaction?.toWalletName,
    ),
);
const categoryChoices = computed(() =>
    type.value === 'transfer'
        ? []
        : withCurrent(
              categoryOptions(props.categories[type.value]),
              props.transaction?.type === type.value
                  ? props.transaction.categoryId
                  : null,
              props.transaction?.categoryName,
          ),
);

// A category of the other type is not valid anymore (BR-B02).
watch(type, () => {
    if (!categoryChoices.value.some((o) => o.value === categoryId.value)) {
        categoryId.value = null;
    }
});

const action = computed(() =>
    props.transaction
        ? TransactionController.update.form(props.transaction.id)
        : TransactionController.store.form(),
);
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="title"
            description="Nominal boleh ditulis singkat: 50rb, 1,5jt, atau 250.000."
        />

        <Card>
            <CardContent>
                <Form
                    v-bind="action"
                    class="grid gap-5"
                    v-slot="{ errors, processing }"
                    :reset-on-success="['amount', 'note', 'tags']"
                >
                    <input type="hidden" name="type" :value="type" />
                    <input
                        type="hidden"
                        name="add_another"
                        :value="addAnother ? 1 : 0"
                    />

                    <div class="grid grid-cols-3 gap-2" role="radiogroup">
                        <button
                            v-for="option in types"
                            :key="option.value"
                            type="button"
                            role="radio"
                            :aria-checked="type === option.value"
                            class="flex items-center justify-center gap-1.5 rounded-lg border px-2 py-2.5 text-sm font-medium transition-colors"
                            :class="
                                type === option.value
                                    ? option.active
                                    : 'text-muted-foreground hover:bg-muted'
                            "
                            @click="type = option.value"
                        >
                            <component :is="option.icon" class="size-4" />
                            {{ option.label }}
                        </button>
                    </div>
                    <InputError :message="errors.type" />

                    <div class="grid gap-2">
                        <Label for="amount">Nominal</Label>
                        <Input
                            id="amount"
                            name="amount"
                            inputmode="text"
                            autocomplete="off"
                            class="h-12 text-lg font-semibold tabular-nums"
                            :default-value="
                                transaction ? String(transaction.amount) : ''
                            "
                            placeholder="50rb"
                            autofocus
                            required
                        />
                        <InputError :message="errors.amount" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="date">Tanggal</Label>
                            <Input
                                id="date"
                                name="date"
                                type="date"
                                :max="today"
                                :default-value="transaction?.date ?? today"
                                required
                            />
                            <InputError :message="errors.date" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="wallet_id">
                                {{
                                    type === 'transfer'
                                        ? 'Dari dompet'
                                        : 'Dompet'
                                }}
                            </Label>
                            <SearchableSelect
                                id="wallet_id"
                                v-model="walletId"
                                name="wallet_id"
                                :options="fromWalletOptions"
                                placeholder="Cari dompet…"
                                required
                            />
                            <InputError :message="errors.wallet_id" />
                        </div>
                    </div>

                    <div v-if="type === 'transfer'" class="grid gap-2">
                        <Label for="to_wallet_id">Ke dompet</Label>
                        <SearchableSelect
                            id="to_wallet_id"
                            v-model="toWalletId"
                            name="to_wallet_id"
                            :options="toWalletOptions"
                            placeholder="Cari dompet tujuan…"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            Transfer hanya memindahkan saldo, tidak dihitung
                            sebagai pemasukan atau pengeluaran. Bayar tagihan
                            kartu kredit juga dicatat sebagai transfer.
                        </p>
                        <InputError :message="errors.to_wallet_id" />
                    </div>

                    <div v-else class="grid gap-2">
                        <Label for="category_id">Kategori</Label>
                        <SearchableSelect
                            id="category_id"
                            v-model="categoryId"
                            name="category_id"
                            :options="categoryChoices"
                            placeholder="Cari kategori atau kata kunci (mis. bensin)…"
                            required
                        />
                        <InputError :message="errors.category_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="note">Catatan (opsional)</Label>
                        <Input
                            id="note"
                            name="note"
                            maxlength="255"
                            :default-value="transaction?.note ?? ''"
                            placeholder="Bensin motor, makan siang kantor…"
                        />
                        <InputError :message="errors.note" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="tags">Tag (opsional)</Label>
                        <Input
                            id="tags"
                            name="tags"
                            :default-value="transaction?.tags.join(', ') ?? ''"
                            placeholder="mudik, liburan-bali"
                        />
                        <InputError :message="errors.tags" />
                    </div>

                    <InputError :message="errors.general" />

                    <div
                        class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                    >
                        <Button variant="outline" as-child>
                            <Link :href="index()">Batal</Link>
                        </Button>
                        <Button
                            v-if="!isEdit"
                            type="submit"
                            variant="secondary"
                            :disabled="processing"
                            @click="addAnother = true"
                        >
                            Simpan &amp; tambah lagi
                        </Button>
                        <Button
                            type="submit"
                            :disabled="processing"
                            @click="addAnother = false"
                        >
                            <Spinner v-if="processing" />
                            Simpan
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>

        <Card v-if="isEdit && history.length">
            <CardContent class="flex flex-col gap-4">
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <History class="size-4" /> Riwayat perubahan
                </h2>
                <ol class="flex flex-col gap-3 border-l pl-4">
                    <li v-for="(entry, i) in history" :key="i" class="text-sm">
                        <p>
                            <span class="font-medium">{{ entry.who }}</span>
                            {{ entry.title }}
                            <span class="text-xs text-muted-foreground">
                                · {{ entry.when }}
                            </span>
                        </p>
                        <ul
                            v-if="entry.changes.length"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            <li
                                v-for="change in entry.changes"
                                :key="change.field"
                            >
                                {{ change.field }}: {{ change.old }} →
                                <span class="font-medium text-foreground">
                                    {{ change.new }}
                                </span>
                            </li>
                        </ul>
                    </li>
                </ol>
                <p class="text-xs text-muted-foreground">
                    Riwayat disimpan 30 hari.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
