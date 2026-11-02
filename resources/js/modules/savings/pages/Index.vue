<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { HandCoins, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import SavingGoalController from '@/actions/App/Http/Controllers/Web/Savings/SavingGoalController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index } from '@/routes/savings';
import SavingGoalCard from '../components/SavingGoalCard.vue';
import SavingGoalDialogs from '../components/SavingGoalDialogs.vue';

type Goal = App.DTOs.Savings.SavingGoalItemData;

defineProps<{
    goals: Goal[];
    wallets: { id: number; name: string }[];
    canManage: boolean;
    canContribute: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tabungan', href: index() }],
    },
});

const open = ref(false);
const mode = ref<'goal' | 'contribute'>('goal');
const selected = ref<Goal | null>(null);

function show(nextMode: 'goal' | 'contribute', goal: Goal | null) {
    mode.value = nextMode;
    selected.value = goal;
    open.value = true;
}

function remove(goal: Goal) {
    if (confirm(`Hapus tujuan ${goal.name}?`)) {
        router.delete(SavingGoalController.destroy.url(goal.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Tabungan" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Tujuan tabungan"
                description="Pantau dana darurat, liburan, atau barang impian keluarga."
            />
            <Button v-if="canManage" @click="show('goal', null)">
                <Plus class="size-4" /> Tujuan baru
            </Button>
        </div>

        <div
            v-if="goals.length === 0"
            class="rounded-2xl border border-dashed p-10 text-center"
        >
            <p class="font-medium">Belum ada tujuan tabungan</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Contoh: dana darurat Rp 15 jt, dihubungkan ke rekening tabungan.
            </p>
        </div>

        <Card v-else class="gap-0 rounded-2xl py-0">
            <CardContent class="divide-y px-0">
                <div
                    v-for="goal in goals"
                    :key="goal.id"
                    class="px-4 py-4 md:px-6"
                >
                    <SavingGoalCard :goal="goal">
                        <template #actions>
                            <div class="flex shrink-0 gap-1">
                                <Button
                                    v-if="canContribute && !goal.walletId"
                                    variant="ghost"
                                    size="icon"
                                    class="size-9"
                                    :aria-label="`Setor atau ambil ${goal.name}`"
                                    @click="show('contribute', goal)"
                                >
                                    <HandCoins class="size-4" />
                                </Button>
                                <template v-if="canManage">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-9"
                                        :aria-label="`Ubah ${goal.name}`"
                                        @click="show('goal', goal)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-9 text-muted-foreground hover:text-destructive"
                                        :aria-label="`Hapus ${goal.name}`"
                                        @click="remove(goal)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </template>
                            </div>
                        </template>
                    </SavingGoalCard>
                </div>
            </CardContent>
        </Card>
    </div>

    <SavingGoalDialogs
        v-model:open="open"
        :mode="mode"
        :goal="selected"
        :wallets="wallets"
    />
</template>
