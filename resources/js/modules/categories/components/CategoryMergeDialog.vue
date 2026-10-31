<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Web/Category/CategoryController';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Category = App.DTOs.Category.CategoryItemData;

const props = defineProps<{
    category: Category | null;
    candidates: Category[];
}>();

const open = defineModel<boolean>('open', { required: true });

// Flatten parents and children of the same type, excluding the source (BR-E03).
const targets = computed(() =>
    props.candidates
        .flatMap((parent) => [
            { id: parent.id, label: parent.name },
            ...parent.children.map((child) => ({
                id: child.id,
                label: `${parent.name} › ${child.name}`,
            })),
        ])
        .filter((target) => target.id !== props.category?.id),
);
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
                    <NativeSelect id="merge-target" name="target_id" required>
                        <option
                            v-for="target in targets"
                            :key="target.id"
                            :value="target.id"
                        >
                            {{ target.label }}
                        </option>
                    </NativeSelect>
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
