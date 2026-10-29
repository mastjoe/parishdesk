<script setup lang="ts">
import { Search } from '@lucide/vue';
import { Input } from '@/components/ui/input';

withDefaults(defineProps<{ placeholder?: string }>(), {
    placeholder: 'Search…',
});

const model = defineModel<string>({ default: '' });
</script>

<template>
    <div class="relative w-full sm:max-w-xs">
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            v-model="model"
            type="search"
            :placeholder="placeholder"
            class="pl-9"
        />
    </div>
</template>
