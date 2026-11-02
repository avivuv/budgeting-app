<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import SavingGoalController from '@/actions/App/Http/Controllers/Web/Savings/SavingGoalController';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
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

type Goal = App.DTOs.Savings.SavingGoalItemData;

/*
 * "goal": create/edit a goal. "contribute": add or take out money (BR-K02).
 */
const props = defineProps<{
    mode: 'goal' | 'contribute';
    goal: Goal | null;
    wallets: { id: number; name: string }[];
}>();

const open = defineModel<boolean>('open', { required: true });

const walletId = ref<number | string | null>(null);
const direction = ref<'deposit' | 'withdraw'>('deposit');
watch(open, (isOpen) => {
    if (isOpen) {
        walletId.value = props.goal?.walletId ?? null;
        direction.value = 'deposit';
    }
});

const walletOptions = computed(() =>
    props.wallets.map((w) => ({ value: w.id, label: w.name })),
);

const action = computed(() =>
    props.mode === 'contribute' && props.goal
        ? SavingGoalController.contribute.form(props.goal.id)
        : props.goal
          ? SavingGoalController.update.form(props.goal.id)
          : SavingGoalController.store.form(),
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    <template v-if="mode === 'contribute'">
                        {{ goal?.name }}
                    </template>
                    <template v-else>
                        {{ goal ? 'Ubah tujuan' : 'Tujuan tabungan baru' }}
                    </template>
                </DialogTitle>
                <DialogDescription>
                    <template v-if="mode === 'contribute'">
                        Terkumpul {{ goal?.progressFormatted }} dari
                        {{ goal?.targetFormatted }}.
                    </template>
                    <template v-else>
                        Progres diambil dari saldo dompet yang dipilih, atau
                        dari setoran manual bila tanpa dompet.
                    </template>
                </DialogDescription>
            </DialogHeader>

            <Form
                :key="`${mode}-${goal?.id ?? 'new'}`"
                v-bind="action"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <template v-if="mode === 'contribute'">
                    <div class="grid gap-2">
                        <Label for="contribute-direction">Jenis</Label>
                        <NativeSelect
                            id="contribute-direction"
                            v-model="direction"
                            name="direction"
                        >
                            <option value="deposit">Setor</option>
                            <option value="withdraw">Ambil</option>
                        </NativeSelect>
                        <InputError :message="errors.direction" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="contribute-amount">Nominal</Label>
                        <Input
                            id="contribute-amount"
                            name="amount"
                            placeholder="500rb"
                            class="h-11 text-lg font-semibold tabular-nums"
                            required
                        />
                        <InputError :message="errors.amount" />
                    </div>
                </template>

                <template v-else>
                    <div class="grid gap-2">
                        <Label for="goal-name">Nama</Label>
                        <Input
                            id="goal-name"
                            name="name"
                            :default-value="goal?.name"
                            placeholder="Dana darurat, liburan, laptop…"
                            maxlength="80"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="goal-target">Target</Label>
                        <Input
                            id="goal-target"
                            name="target_amount"
                            :default-value="
                                goal ? String(goal.targetAmount) : ''
                            "
                            placeholder="Contoh: 15jt"
                            required
                        />
                        <InputError :message="errors.target_amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="goal-wallet">Dompet (opsional)</Label>
                        <SearchableSelect
                            id="goal-wallet"
                            v-model="walletId"
                            name="wallet_id"
                            :options="walletOptions"
                            placeholder="Tanpa dompet — setor manual"
                            clearable
                        />
                        <InputError :message="errors.wallet_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="goal-deadline">Tenggat (opsional)</Label>
                        <Input
                            id="goal-deadline"
                            name="deadline"
                            type="date"
                            :default-value="goal?.deadline ?? ''"
                        />
                        <InputError :message="errors.deadline" />
                    </div>
                </template>

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
