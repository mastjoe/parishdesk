<script setup lang="ts">
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Option } from '@/types';

/**
 * A select over value and label options whose value may be empty.
 *
 * reka-ui reserves the empty string, so "no value" is mapped to a
 * placeholder item and back to null.
 */
const props = withDefaults(
    defineProps<{
        options: Option[];
        placeholder?: string;
        /** Label of the item that clears the value; omit to require a value. */
        noneLabel?: string;
        id?: string;
        class?: string;
    }>(),
    { placeholder: 'Choose…' },
);

const model = defineModel<string | null>({ default: null });

const NONE = '__none__';

const value = computed({
    get: () => model.value ?? (props.noneLabel ? NONE : undefined),
    set: (next) => {
        model.value = next === NONE || next === undefined ? null : next;
    },
});
</script>

<template>
    <Select
        :model-value="value"
        @update:model-value="value = $event === null ? NONE : String($event)"
    >
        <SelectTrigger :id="id" :class="['w-full', props.class]">
            <SelectValue :placeholder="placeholder" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-if="noneLabel" :value="NONE">{{
                noneLabel
            }}</SelectItem>
            <SelectItem
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
