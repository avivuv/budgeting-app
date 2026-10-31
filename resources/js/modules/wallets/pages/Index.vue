<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Archive,
    ArchiveRestore,
    Banknote,
    CreditCard,
    Landmark,
    MoreVertical,
    Pencil,
    Plus,
    Smartphone,
    Trash2,
    Wallet as WalletIcon,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import WalletController from '@/actions/App/Http/Controllers/Web/Wallet/WalletController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index } from '@/routes/wallets';
import WalletFormDialog from '../components/WalletFormDialog.vue';

type WalletItem = App.DTOs.Wallet.WalletItemData;

const props = defineProps<{
    wallets: WalletItem[];
    totalBalance: number;
    totalBalanceFormatted: string;
    walletTypes: { value: App.Enums.WalletType; label: string }[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dompet', href: index() }],
    },
});

const active = computed(() => props.wallets.filter((w) => !w.isArchived));
const archived = computed(() => props.wallets.filter((w) => w.isArchived));

const dialogOpen = ref(false);
const editing = ref<WalletItem | null>(null);

const icons: Record<App.Enums.WalletType, typeof WalletIcon> = {
    cash: Banknote,
    bank: Landmark,
    ewallet: Smartphone,
    credit_card: CreditCard,
    other: WalletIcon,
};

function openCreate() {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(wallet: WalletItem) {
    editing.value = wallet;
    dialogOpen.value = true;
}

function setArchived(wallet: WalletItem, archived: boolean) {
    router.patch(
        WalletController.archive.url(wallet.id),
        { archived },
        { preserveScroll: true },
    );
}

function destroy(wallet: WalletItem) {
    if (confirm(`Hapus dompet "${wallet.name}"?`)) {
        router.delete(WalletController.destroy.url(wallet.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Dompet" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Dompet"
                description="Saldo dihitung otomatis dari saldo awal dan semua transaksi."
            />
            <Button v-if="canManage" @click="openCreate">
                <Plus class="size-4" />
                Tambah dompet
            </Button>
        </div>

        <Card
            class="border-0 bg-gradient-to-br from-primary to-primary-hover text-primary-foreground"
        >
            <CardContent class="py-2">
                <p class="text-sm opacity-90">Total saldo</p>
                <p class="text-3xl font-bold tabular-nums">
                    {{ totalBalanceFormatted }}
                </p>
                <p class="mt-1 text-xs opacity-80">
                    {{ active.length }} dompet aktif · kartu kredit dihitung
                    sebagai minus
                </p>
            </CardContent>
        </Card>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Card v-for="wallet in active" :key="wallet.id">
                <CardContent class="flex items-center gap-3 py-1">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary"
                    >
                        <component :is="icons[wallet.type]" class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ wallet.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ wallet.typeLabel }}
                        </p>
                    </div>
                    <p
                        class="font-semibold tabular-nums"
                        :class="wallet.balance < 0 ? 'text-expense' : ''"
                    >
                        {{ wallet.balanceFormatted }}
                    </p>
                    <DropdownMenu v-if="canManage">
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                aria-label="Aksi dompet"
                            >
                                <MoreVertical class="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="openEdit(wallet)">
                                <Pencil class="size-4" /> Ubah
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click="setArchived(wallet, true)"
                            >
                                <Archive class="size-4" /> Arsipkan
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                class="text-destructive"
                                @click="destroy(wallet)"
                            >
                                <Trash2 class="size-4" /> Hapus
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </CardContent>
            </Card>
        </div>

        <p
            v-if="active.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Belum ada dompet aktif.
        </p>

        <div v-if="archived.length" class="flex flex-col gap-3">
            <h2 class="text-sm font-semibold text-muted-foreground">
                Diarsipkan
            </h2>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <Card
                    v-for="wallet in archived"
                    :key="wallet.id"
                    class="opacity-70"
                >
                    <CardContent class="flex items-center gap-3 py-1">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">
                                {{ wallet.name }}
                            </p>
                            <Badge variant="secondary">Diarsipkan</Badge>
                        </div>
                        <p class="tabular-nums">
                            {{ wallet.balanceFormatted }}
                        </p>
                        <Button
                            v-if="canManage"
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            title="Aktifkan kembali"
                            @click="setArchived(wallet, false)"
                        >
                            <ArchiveRestore class="size-4" />
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>

        <WalletFormDialog
            v-model:open="dialogOpen"
            :wallet="editing"
            :wallet-types="walletTypes"
        />
    </div>
</template>
