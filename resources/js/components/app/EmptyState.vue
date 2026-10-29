<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

defineProps<{
    title: string;
    description?: string;
    icon?: LucideIcon;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 py-12 text-center"
    >
        <span
            v-if="icon"
            class="flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary"
        >
            <component :is="icon" class="size-5" />
        </span>
        <div class="space-y-1">
            <p class="font-medium">{{ title }}</p>
            <p
                v-if="description"
                class="max-w-sm text-sm text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>
        <slot />
    </div>
</template>
