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
import { computed, ref, watch } from 'vue';
import type { GroupedOption } from '@/types';

/**
 * Pick one value from a long, grouped list by typing to narrow it down,
 * e.g. an occupation. The list is filtered in the browser.
 */
const props = withDefaults(
    defineProps<{
        options: GroupedOption[];
        placeholder?: string;
        id?: string;
        clearable?: boolean;
    }>(),
    { placeholder: 'Search…', clearable: true },
);

const model = defineModel<string | null>({ default: null });

const search = ref('');
const open = ref(false);

const labels = computed(
    () => new Map(props.options.map((option) => [option.value, option.label])),
);

/** Matching options, keeping the order and grouping of the list. */
const groups = computed(() => {
    const words = search.value.toLowerCase().split(/\s+/).filter(Boolean);
    const grouped = new Map<string, GroupedOption[]>();

    for (const option of props.options) {
        const label = option.label.toLowerCase();

        if (words.every((word) => label.includes(word))) {
            grouped.set(option.group, [
                ...(grouped.get(option.group) ?? []),
                option,
            ]);
        }
    }

    return [...grouped].map(([name, options]) => ({ name, options }));
});

// Start every search from the full list rather than the chosen label.
watch(open, (isOpen) => {
    if (isOpen) {
        search.value = '';
    }
});
</script>

<template>
    <ComboboxRoot
        v-model:open="open"
        :model-value="model ?? undefined"
        ignore-filter
        :reset-search-term-on-blur="false"
        @update:model-value="model = ($event as string | undefined) ?? null"
    >
        <ComboboxAnchor
            class="flex h-9 w-full items-center gap-1 rounded-md border border-input bg-transparent pr-1 shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30"
        >
            <ComboboxInput
                :id="id"
                v-model="search"
                :display-value="
                    (value: string | undefined) =>
                        (value && labels.get(value)) ?? ''
                "
                :placeholder="placeholder"
                class="h-full min-w-0 flex-1 bg-transparent px-3 text-base outline-none placeholder:text-muted-foreground md:text-sm"
                @focus="open = true"
            />
            <button
                v-if="clearable && model"
                type="button"
                class="rounded p-1 text-muted-foreground hover:text-foreground"
                aria-label="Clear"
                @click="model = null"
            >
                <X class="size-3.5" />
            </button>
            <ComboboxTrigger
                class="rounded p-1 text-muted-foreground hover:text-foreground"
            >
                <ChevronsUpDown class="size-4" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                :side-offset="4"
                class="z-50 max-h-80 w-(--reka-combobox-trigger-width) min-w-64 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md"
            >
                <ComboboxViewport class="p-1">
                    <ComboboxEmpty
                        class="px-2 py-6 text-center text-sm text-muted-foreground"
                    >
                        No matches found.
                    </ComboboxEmpty>
                    <ComboboxGroup v-for="group in groups" :key="group.name">
                        <ComboboxLabel
                            class="px-2 pt-2 pb-1 text-xs font-medium text-muted-foreground"
                        >
                            {{ group.name }}
                        </ComboboxLabel>
                        <ComboboxItem
                            v-for="option in group.options"
                            :key="option.value"
                            :value="option.value"
                            class="relative flex cursor-default items-center rounded-sm py-1.5 pr-8 pl-2 text-sm outline-none select-none data-highlighted:bg-accent data-highlighted:text-accent-foreground"
                        >
                            {{ option.label }}
                            <ComboboxItemIndicator class="absolute right-2">
                                <Check class="size-4" />
                            </ComboboxItemIndicator>
                        </ComboboxItem>
                    </ComboboxGroup>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
