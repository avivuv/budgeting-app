<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Web/Category/CategoryController';
import InputError from '@/components/InputError.vue';
import { categoryOptions } from '../index';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Category = App.DTOs.Category.CategoryItemData;

const props = defineProps<{
    category: Category | null;
    candidates: Category[];
}>();

const open = defineModel<boolean>('open', { required: true });

// Same-type categories except the source itself (BR-E03).
const targets = computed(() =>
    categoryOptions(props.candidates, { excludeId: props.category?.id }),
);

const targetId = ref<number | string | null>(null);

watch(open, () => (targetId.value = null));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="category" class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Gabungkan "{{ category.name }}"</DialogTitle>
                <DialogDescription>
                    Semua transaksi dipindahkan ke kategori tujuan, lalu
                    kategori ini dihapus. Tindakan ini tidak bisa dibatalkan.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="CategoryController.merge.form(category.id)"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <div class="grid gap-2">
                    <Label for="merge-target">Kategori tujuan</Label>
                    <SearchableSelect
                        id="merge-target"
                        v-model="targetId"
                        name="target_id"
                        :options="targets"
                        placeholder="Cari kategori tujuan…"
                        required
                    />
                    <InputError :message="errors.target_id ?? errors.general" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" />
                        Gabungkan
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
