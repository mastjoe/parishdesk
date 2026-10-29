<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Church } from '@lucide/vue';
import { computed } from 'vue';

const parish = computed(() => usePage().props.parish);
</script>

<template>
    <div
        class="flex aspect-square size-8 shrink-0 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <img
            v-if="parish.logoUrl"
            :src="parish.logoUrl"
            :alt="parish.name"
            class="size-full object-cover"
        />
        <Church v-else class="size-4.5" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="truncate leading-tight font-semibold">{{
            parish.name
        }}</span>
        <span class="truncate text-xs text-muted-foreground">ParishDesk</span>
    </div>
</template>
