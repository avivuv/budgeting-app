<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Archive,
    ArchiveRestore,
    Combine,
    MoreVertical,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Web/Category/CategoryController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index } from '@/routes/categories';
import CategoryFormDialog from '../components/CategoryFormDialog.vue';
import CategoryMergeDialog from '../components/CategoryMergeDialog.vue';

type Category = App.DTOs.Category.CategoryItemData;

const props = defineProps<{
    expense: Category[];
    income: Category[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Kategori', href: index() }],
    },
});

const groups = [
    { type: 'expense' as const, title: 'Pengeluaran' },
    { type: 'income' as const, title: 'Pemasukan' },
];

const formOpen = ref(false);
const mergeOpen = ref(false);
const editing = ref<Category | null>(null);
const merging = ref<Category | null>(null);
const defaultType = ref<App.Enums.CategoryType>('expense');

function openCreate(type: App.Enums.CategoryType) {
    editing.value = null;
    defaultType.value = type;
    formOpen.value = true;
}

function openEdit(category: Category) {
    editing.value = category;
    formOpen.value = true;
}

function openMerge(category: Category) {
    merging.value = category;
    mergeOpen.value = true;
}

function setArchived(category: Category, archived: boolean) {
    router.patch(
        CategoryController.archive.url(category.id),
        { archived },
        { preserveScroll: true },
    );
}

function destroy(category: Category) {
    if (confirm(`Hapus kategori "${category.name}"?`)) {
        router.delete(CategoryController.destroy.url(category.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Kategori" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Kategori"
            description="Kelompokkan transaksi agar laporan mudah dibaca. Maksimal dua tingkat."
        />

        <div class="grid gap-6 lg:grid-cols-2">
            <Card v-for="group in groups" :key="group.type">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <span
                            class="size-2.5 rounded-full"
                            :class="
                                group.type === 'expense'
                                    ? 'bg-expense'
                                    : 'bg-income'
                            "
                        />
                        {{ group.title }}
                    </CardTitle>
                    <Button
                        v-if="canManage"
                        size="sm"
                        variant="outline"
                        @click="openCreate(group.type)"
                    >
                        <Plus class="size-4" /> Tambah
                    </Button>
                </CardHeader>
                <CardContent class="flex flex-col divide-y">
                    <div
                        v-for="category in props[group.type]"
                        :key="category.id"
                        class="py-2"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="size-3 shrink-0 rounded-full"
                                :style="{
                                    backgroundColor:
                                        category.color ?? undefined,
                                }"
                                :class="{ 'bg-muted': !category.color }"
                            />
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate font-medium"
                                    :class="{
                                        'text-muted-foreground line-through':
                                            category.isArchived,
                                    }"
                                >
                                    {{ category.name }}
                                </p>
                                <p
                                    v-if="category.keywords.length"
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ category.keywords.join(', ') }}
                                </p>
                            </div>
                            <Badge
                                v-if="category.isArchived"
                                variant="secondary"
                            >
                                Diarsipkan
                            </Badge>
                            <DropdownMenu v-if="canManage">
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-8"
                                        aria-label="Aksi kategori"
                                    >
                                        <MoreVertical class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        @click="openEdit(category)"
                                    >
                                        <Pencil class="size-4" /> Ubah
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="
                                            setArchived(
                                                category,
                                                !category.isArchived,
                                            )
                                        "
                                    >
                                        <component
                                            :is="
                                                category.isArchived
                                                    ? ArchiveRestore
                                                    : Archive
                                            "
                                            class="size-4"
                                        />
                                        {{
                                            category.isArchived
                                                ? 'Aktifkan'
                                                : 'Arsipkan'
                                        }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="!category.children.length"
                                        @click="openMerge(category)"
                                    >
                                        <Combine class="size-4" /> Gabungkan
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        class="text-destructive"
                                        @click="destroy(category)"
                                    >
                                        <Trash2 class="size-4" /> Hapus
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        <div
                            v-for="child in category.children"
                            :key="child.id"
                            class="mt-1 ml-6 flex items-center gap-3 border-l pl-3"
                        >
                            <p
                                class="min-w-0 flex-1 truncate text-sm"
                                :class="{
                                    'text-muted-foreground line-through':
                                        child.isArchived,
                                }"
                            >
                                {{ child.name }}
                            </p>
                            <DropdownMenu v-if="canManage">
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-7"
                                        aria-label="Aksi sub-kategori"
                                    >
                                        <MoreVertical class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="openEdit(child)">
                                        <Pencil class="size-4" /> Ubah
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="
                                            setArchived(
                                                child,
                                                !child.isArchived,
                                            )
                                        "
                                    >
                                        <Archive class="size-4" />
                                        {{
                                            child.isArchived
                                                ? 'Aktifkan'
                                                : 'Arsipkan'
                                        }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="openMerge(child)">
                                        <Combine class="size-4" /> Gabungkan
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        class="text-destructive"
                                        @click="destroy(child)"
                                    >
                                        <Trash2 class="size-4" /> Hapus
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <CategoryFormDialog
            v-model:open="formOpen"
            :category="editing"
            :default-type="defaultType"
            :parents="{ expense, income }"
        />
        <CategoryMergeDialog
            v-model:open="mergeOpen"
            :category="merging"
            :candidates="merging ? props[merging.type] : []"
        />
    </div>
</template>
