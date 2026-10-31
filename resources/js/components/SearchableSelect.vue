<script setup lang="ts">
import { Check, ChevronsUpDown } from '@lucide/vue';
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
import { cn } from '@/lib/utils';

export type SearchableOption = {
    value: number | string;
    label: string;
    /** Options sharing a group are listed under that heading. */
    group?: string;
    /** Shown indented, e.g. a sub-category under its parent. */
    indent?: boolean;
    /** Extra words that also match the search (e.g. category keywords). */
    keywords?: string[];
};

const props = withDefaults(
    defineProps<{
        options: SearchableOption[];
        /** Form field name; a hidden input carries the value for <Form>. */
        name?: string;
        id?: string;
        placeholder?: string;
        emptyText?: string;
        disabled?: boolean;
        required?: boolean;
        class?: string;
    }>(),
    {
        name: undefined,
        id: undefined,
        placeholder: 'Pilih…',
        emptyText: 'Tidak ditemukan',
        class: undefined,
    },
);

const model = defineModel<number | string | null>({ default: null });

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

function display(value: unknown): string {
    return byValue.value.get(value as number | string)?.label ?? '';
}

function searchText(option: SearchableOption): string {
    return [option.label, option.group, ...(option.keywords ?? [])]
        .filter(Boolean)
        .join(' ');
}
</script>

<template>
    <ComboboxRoot
        v-model="model"
        :disabled="disabled"
        open-on-click
        reset-search-term-on-blur
        reset-search-term-on-select
        :class="cn('relative', props.class)"
    >
        <ComboboxAnchor
            class="flex h-9 w-full items-center rounded-md border border-input bg-transparent shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 has-[input:disabled]:cursor-not-allowed has-[input:disabled]:opacity-50 dark:bg-input/30"
        >
            <ComboboxInput
                :id="id"
                :display-value="display"
                :placeholder="placeholder"
                autocomplete="off"
                class="h-full min-w-0 flex-1 bg-transparent px-3 text-sm outline-none placeholder:text-muted-foreground"
            />
            <ComboboxTrigger
                class="flex h-full items-center px-2 text-muted-foreground"
                aria-label="Buka pilihan"
            >
                <ChevronsUpDown class="size-4" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <input
            v-if="name"
            type="hidden"
            :name="name"
            :value="model ?? ''"
            :required="required"
        />

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
                            class="relative flex cursor-pointer items-center gap-2 rounded-sm py-1.5 pr-8 text-sm outline-none select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
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
