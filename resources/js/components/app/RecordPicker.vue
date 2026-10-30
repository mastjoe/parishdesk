<script setup lang="ts" generic="TItem extends { id: number; name: string }">
import { useDebounceFn } from '@vueuse/core';
import { Check, ChevronsUpDown, LoaderCircle, X } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { ref, shallowRef, watch } from 'vue';
import type { Ref } from 'vue';

/**
 * Search a JSON lookup endpoint and pick one record, e.g. a member or a
 * family. The endpoint answers `?q=` with `{ data: TItem[] }`.
 */
const props = withDefaults(
    defineProps<{
        url: string;
        /** The currently selected record, so its name shows before any search. */
        initial?: TItem | null;
        placeholder?: string;
        id?: string;
        clearable?: boolean;
        describe?: (item: TItem) => string | null | undefined;
    }>(),
    { initial: null, placeholder: 'Search…', clearable: true },
);

const model = defineModel<number | null>({ default: null });

const emit = defineEmits<{
    select: [item: TItem | null];
}>();

const selected: Ref<TItem | null> = shallowRef(props.initial);
const items: Ref<TItem[]> = shallowRef([]);
const search = ref('');
const loading = ref(false);
const open = ref(false);

const load = async (term: string) => {
    loading.value = true;

    try {
        const url = new URL(props.url, window.location.origin);
        url.searchParams.set('q', term);

        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        items.value = response.ok ? (await response.json()).data : [];
    } finally {
        loading.value = false;
    }
};

const debouncedLoad = useDebounceFn(load, 250);

watch(search, (term) => debouncedLoad(term));
watch(open, (isOpen) => {
    if (isOpen && items.value.length === 0) {
        load(search.value);
    }
});

watch(
    () => props.initial,
    (initial) => {
        selected.value = initial;
    },
);

const choose = (item: TItem | null) => {
    selected.value = item;
    model.value = item?.id ?? null;
    emit('select', item);
};
</script>

<template>
    <ComboboxRoot
        v-model:open="open"
        :model-value="selected ?? undefined"
        by="id"
        ignore-filter
        :reset-search-term-on-blur="false"
        @update:model-value="choose(($event as TItem | undefined) ?? null)"
    >
        <ComboboxAnchor
            class="flex h-9 w-full items-center gap-1 rounded-md border border-input bg-transparent pr-1 shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30"
        >
            <ComboboxInput
                :id="id"
                v-model="search"
                :display-value="(item: TItem | undefined) => item?.name ?? ''"
                :placeholder="placeholder"
                class="h-full min-w-0 flex-1 bg-transparent px-3 text-base outline-none placeholder:text-muted-foreground md:text-sm"
                @focus="open = true"
            />
            <button
                v-if="clearable && selected"
                type="button"
                class="rounded p-1 text-muted-foreground hover:text-foreground"
                aria-label="Clear"
                @click="choose(null)"
            >
                <X class="size-3.5" />
            </button>
            <ComboboxTrigger
                class="rounded p-1 text-muted-foreground hover:text-foreground"
            >
                <LoaderCircle v-if="loading" class="size-4 animate-spin" />
                <ChevronsUpDown v-else class="size-4" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                :side-offset="4"
                class="z-50 max-h-72 w-(--reka-combobox-trigger-width) min-w-56 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md"
            >
                <ComboboxViewport class="p-1">
                    <ComboboxEmpty
                        class="px-2 py-6 text-center text-sm text-muted-foreground"
                    >
                        {{ loading ? 'Searching…' : 'No matches found.' }}
                    </ComboboxEmpty>
                    <ComboboxItem
                        v-for="item in items"
                        :key="item.id"
                        :value="item"
                        class="relative flex cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-none select-none data-highlighted:bg-accent data-highlighted:text-accent-foreground"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block truncate">{{ item.name }}</span>
                            <span
                                v-if="describe?.(item)"
                                class="block truncate text-xs text-muted-foreground"
                                >{{ describe(item) }}</span
                            >
                        </span>
                        <ComboboxItemIndicator class="absolute right-2">
                            <Check class="size-4" />
                        </ComboboxItemIndicator>
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
