<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    /** A role colour or a semantic tone. */
    tone?: string;
}>();

const tones: Record<string, string> = {
    slate: 'bg-slate-500/10 text-slate-700 ring-slate-500/20 dark:text-slate-300',
    blue: 'bg-blue-500/10 text-blue-700 ring-blue-500/20 dark:text-blue-300',
    green: 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/20 dark:text-emerald-300',
    purple: 'bg-violet-500/10 text-violet-700 ring-violet-500/20 dark:text-violet-300',
    red: 'bg-red-500/10 text-red-700 ring-red-500/20 dark:text-red-300',
    amber: 'bg-amber-500/10 text-amber-700 ring-amber-500/20 dark:text-amber-300',
    rose: 'bg-rose-500/10 text-rose-700 ring-rose-500/20 dark:text-rose-300',
    teal: 'bg-teal-500/10 text-teal-700 ring-teal-500/20 dark:text-teal-300',
    primary: 'bg-primary/10 text-primary ring-primary/20',
};

const classes = computed(() => tones[props.tone ?? 'slate'] ?? tones.slate);
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
                classes,
            )
        "
    >
        <slot />
    </span>
</template>
