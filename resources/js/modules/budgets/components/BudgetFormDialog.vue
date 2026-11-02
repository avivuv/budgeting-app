<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import BudgetController from '@/actions/App/Http/Controllers/Web/Budget/BudgetController';
import InputError from '@/components/InputError.vue';
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
import type { AvailableCategory } from '../types';

/*
 * Set a budget: a new category, or a new amount for an existing one
 * (the server keeps one budget per category and period, BR-F01).
 */
const props = defineProps<{
    budget: App.DTOs.Budget.BudgetItemData | null;
    available: AvailableCategory[];
    periodKey: string;
    periodLabel: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const categoryId = ref<number | string | null>(null);
watch(open, (isOpen) => {
    if (isOpen) {
        categoryId.value = props.budget?.categoryId ?? null;
    }
});

const options = computed(() =>
    props.available.map((c) => ({
        value: c.id,
        label: c.name,
        indent: c.parentName !== null,
        keywords: c.parentName ? [c.parentName] : [],
    })),
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{
                        budget
                            ? `Ubah budget ${budget.categoryName}`
                            : 'Tambah budget'
                    }}
                </DialogTitle>
                <DialogDescription>
                    Batas pengeluaran untuk periode {{ periodLabel }}. Budget
                    kategori induk ikut menghitung sub-kategorinya.
                </DialogDescription>
            </DialogHeader>

            <Form
                :key="budget?.id ?? 'new'"
                v-bind="BudgetController.store.form()"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <input type="hidden" name="period" :value="periodKey" />

                <input
                    v-if="budget"
                    type="hidden"
                    name="category_id"
                    :value="budget.categoryId"
                />
                <div v-else class="grid gap-2">
                    <Label for="budget-category">Kategori</Label>
                    <SearchableSelect
                        id="budget-category"
                        v-model="categoryId"
                        name="category_id"
                        :options="options"
                        placeholder="Cari kategori pengeluaran…"
                        empty-text="Semua kategori sudah punya budget"
                        required
                    />
                    <InputError :message="errors.category_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="budget-amount">Nominal budget</Label>
                    <Input
                        id="budget-amount"
                        name="amount"
                        inputmode="text"
                        :default-value="budget ? String(budget.amount) : ''"
                        placeholder="Contoh: 800rb atau 1,5jt"
                        class="h-11 text-lg font-semibold tabular-nums"
                        required
                    />
                    <InputError :message="errors.amount" />
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
