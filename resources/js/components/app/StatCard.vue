<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

defineProps<{
    label: string;
    value: string | number;
    hint?: string;
    icon?: LucideIcon;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-3 rounded-xl border bg-card p-5 text-card-foreground shadow-xs"
    >
        <div class="flex items-center justify-between gap-2">
            <span class="text-sm font-medium text-muted-foreground">{{
                label
            }}</span>
            <span
                v-if="icon"
                class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
            >
                <component :is="icon" class="size-4" />
            </span>
        </div>
        <div class="text-3xl font-semibold tracking-tight tabular-nums">
            {{ value }}
        </div>
        <p v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</p>
    </div>
</template>
