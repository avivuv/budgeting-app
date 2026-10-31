<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Web/Category/CategoryController';
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

type Category = App.DTOs.Category.CategoryItemData;

const props = defineProps<{
    category: Category | null;
    defaultType: App.Enums.CategoryType;
    parents: Record<App.Enums.CategoryType, Category[]>;
}>();

const open = defineModel<boolean>('open', { required: true });

const type = ref<App.Enums.CategoryType>(props.defaultType);

// Parents must have the same type (BR-E01).
watch(type, () => {
    if (!parentOptions.value.some((o) => o.value === parentId.value)) {
        parentId.value = '';
    }
});

watch(open, (isOpen) => {
    if (isOpen) {
        type.value = props.category?.type ?? props.defaultType;
        parentId.value = props.category?.parentId ?? '';
    }
});

const action = computed(() =>
    props.category
        ? CategoryController.update.form(props.category.id)
        : CategoryController.store.form(),
);

// A category can only sit under a top-level category of the same type (BR-E01).
const parentOptions = computed(() => [
    { value: '', label: '— Kategori utama —' },
    ...props.parents[type.value]
        .filter(
            (parent) => !parent.isArchived && parent.id !== props.category?.id,
        )
        .map((parent) => ({
            value: parent.id,
            label: parent.name,
            keywords: parent.keywords,
        })),
]);

const parentId = ref<number | string | null>('');

const hasChildren = computed(() => (props.category?.children.length ?? 0) > 0);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ category ? 'Ubah kategori' : 'Tambah kategori' }}
                </DialogTitle>
                <DialogDescription>
                    Kata kunci membantu menebak kategori dari catatan, misalnya
                    "bensin" → Transportasi.
                </DialogDescription>
            </DialogHeader>

            <Form
                :key="`${category?.id ?? 'new'}-${open}`"
                v-bind="action"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <div class="grid gap-2">
                    <Label for="category-type">Jenis</Label>
                    <NativeSelect
                        id="category-type"
                        v-model="type"
                        name="type"
                        :disabled="hasChildren"
                    >
                        <option value="expense">Pengeluaran</option>
                        <option value="income">Pemasukan</option>
                    </NativeSelect>
                    <input
                        v-if="hasChildren"
                        type="hidden"
                        name="type"
                        :value="type"
                    />
                    <InputError :message="errors.type" />
                </div>

                <div class="grid gap-2">
                    <Label for="category-name">Nama</Label>
                    <Input
                        id="category-name"
                        name="name"
                        :default-value="category?.name"
                        maxlength="50"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <div v-if="!hasChildren" class="grid gap-2">
                    <Label for="category-parent">Induk (opsional)</Label>
                    <SearchableSelect
                        id="category-parent"
                        v-model="parentId"
                        name="parent_id"
                        :options="parentOptions"
                        placeholder="Cari kategori induk…"
                    />
                    <InputError :message="errors.parent_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="category-keywords">Kata kunci</Label>
                    <Input
                        id="category-keywords"
                        name="keywords"
                        :default-value="category?.keywords.join(', ')"
                        placeholder="bensin, parkir, tol"
                    />
                    <p class="text-xs text-muted-foreground">
                        Pisahkan dengan koma.
                    </p>
                    <InputError :message="errors.keywords" />
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
