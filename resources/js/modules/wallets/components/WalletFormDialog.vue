<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import WalletController from '@/actions/App/Http/Controllers/Web/Wallet/WalletController';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const props = defineProps<{
    wallet: App.DTOs.Wallet.WalletItemData | null;
    walletTypes: { value: App.Enums.WalletType; label: string }[];
}>();

const open = defineModel<boolean>('open', { required: true });

const action = computed(() =>
    props.wallet
        ? WalletController.update.form(props.wallet.id)
        : WalletController.store.form(),
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ wallet ? 'Ubah dompet' : 'Tambah dompet' }}
                </DialogTitle>
                <DialogDescription>
                    Dompet adalah tempat uang disimpan: tunai, rekening bank,
                    e-wallet, atau kartu kredit.
                </DialogDescription>
            </DialogHeader>

            <Form
                :key="wallet?.id ?? 'new'"
                v-bind="action"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <div class="grid gap-2">
                    <Label for="wallet-name">Nama</Label>
                    <Input
                        id="wallet-name"
                        name="name"
                        :default-value="wallet?.name"
                        placeholder="BCA, GoPay, Tunai…"
                        maxlength="50"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="wallet-type">Jenis</Label>
                    <NativeSelect id="wallet-type" name="type" required>
                        <option
                            v-for="type in walletTypes"
                            :key="type.value"
                            :value="type.value"
                            :selected="type.value === (wallet?.type ?? 'cash')"
                        >
                            {{ type.label }}
                        </option>
                    </NativeSelect>
                    <InputError :message="errors.type" />
                </div>

                <div class="grid gap-2">
                    <Label for="wallet-balance">Saldo awal</Label>
                    <Input
                        id="wallet-balance"
                        name="initial_balance"
                        inputmode="text"
                        :default-value="
                            wallet ? String(wallet.initialBalance) : ''
                        "
                        placeholder="Contoh: 1.500.000 atau 1,5jt"
                    />
                    <p class="text-xs text-muted-foreground">
                        Saldo saat mulai dicatat. Untuk kartu kredit, isi
                        tagihan berjalan sebagai angka minus (misal -500000).
                    </p>
                    <InputError :message="errors.initial_balance" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        Batal
                    </Button>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Simpan
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
