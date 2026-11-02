<script setup lang="ts">
import { Check, ChevronsUpDown, X } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxLabel,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { computed } from 'vue';
import type { SearchableOption } from '@/components/SearchableSelect.vue';
import { cn } from '@/lib/utils';

/*
 * Multi-value variant of SearchableSelect for master-data filters
 * (docs/15 §6c): searchable, grouped, with sub-items indented.
 */
const props = withDefaults(
    defineProps<{
        options: SearchableOption[];
        id?: string;
        placeholder?: string;
        emptyText?: string;
        class?: string;
    }>(),
    {
        id: undefined,
        placeholder: 'Pilih…',
        emptyText: 'Tidak ditemukan',
        class: undefined,
    },
);

const model = defineModel<(number | string)[]>({ default: () => [] });

const byValue = computed(
    () => new Map(props.options.map((option) => [option.value, option])),
);

const groups = computed(() => {
    const result: { label: string | null; options: SearchableOption[] }[] = [];

    for (const option of props.options) {
        const label = option.group ?? null;
        let group = result.find((g) => g.label === label);

        if (!group) {
            group = { label, options: [] };
            result.push(group);
        }

        group.options.push(option);
    }

    return result;
});

const summary = computed(() => {
    if (model.value.length === 0) {
        return '';
    }

    const first = byValue.value.get(model.value[0])?.label ?? '';

    return model.value.length === 1
        ? first
        : `${first} +${model.value.length - 1}`;
});

function searchText(option: SearchableOption): string {
    return [option.label, option.group, ...(option.keywords ?? [])]
        .filter(Boolean)
        .join(' ');
}
</script>

<template>
    <ComboboxRoot
        v-model="model"
        multiple
        open-on-click
        reset-search-term-on-blur
        :class="cn('relative', props.class)"
    >
        <ComboboxAnchor
            class="flex h-9 w-full items-center rounded-md border border-input bg-transparent shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30"
        >
            <ComboboxInput
                :id="id"
                :placeholder="summary || placeholder"
                autocomplete="off"
                class="h-full min-w-0 flex-1 bg-transparent px-3 text-sm outline-none"
                :class="
                    summary
                        ? 'placeholder:text-foreground'
                        : 'placeholder:text-muted-foreground'
                "
            />
            <button
                v-if="model.length > 0"
                type="button"
                class="flex h-full items-center px-1 text-muted-foreground hover:text-foreground"
                aria-label="Kosongkan pilihan"
                @click="model = []"
            >
                <X class="size-4" />
            </button>
            <ComboboxTrigger
                class="flex h-full items-center px-2 text-muted-foreground"
                aria-label="Buka pilihan"
            >
                <ChevronsUpDown class="size-4" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                :side-offset="4"
                class="z-50 max-h-72 w-(--reka-combobox-trigger-width) min-w-48 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0"
            >
                <ComboboxViewport class="max-h-72 overflow-y-auto p-1">
                    <ComboboxEmpty
                        class="px-2 py-6 text-center text-sm text-muted-foreground"
                    >
                        {{ emptyText }}
                    </ComboboxEmpty>

                    <ComboboxGroup
                        v-for="group in groups"
                        :key="group.label ?? '_'"
                    >
                        <ComboboxLabel
                            v-if="group.label"
                            class="px-2 pt-2 pb-1 text-xs font-semibold text-muted-foreground"
                        >
                            {{ group.label }}
                        </ComboboxLabel>
                        <ComboboxItem
                            v-for="option in group.options"
                            :key="option.value"
                            :value="option.value"
                            :text-value="searchText(option)"
                            class="relative flex cursor-pointer items-center gap-2 rounded-sm py-1.5 pr-8 text-sm outline-none select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                            :class="option.indent ? 'pl-6' : 'pl-2'"
                        >
                            <span
                                v-if="option.indent"
                                class="text-muted-foreground"
                                >↳</span
                            >
                            <span class="truncate">{{ option.label }}</span>
                            <ComboboxItemIndicator
                                class="absolute right-2 flex items-center text-primary"
                            >
                                <Check class="size-4" />
                            </ComboboxItemIndicator>
                        </ComboboxItem>
                    </ComboboxGroup>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
