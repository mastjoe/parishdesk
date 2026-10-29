<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

const props = defineProps<{
    paginator: Paginated<unknown>;
}>();

const previous = computed(() => props.paginator.links[0]?.url ?? null);
const next = computed(
    () => props.paginator.links[props.paginator.links.length - 1]?.url ?? null,
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-between gap-3 text-sm text-muted-foreground sm:flex-row"
    >
        <p>
            <template v-if="paginator.total > 0">
                Showing
                <span class="font-medium text-foreground">{{
                    paginator.from
                }}</span>
                to
                <span class="font-medium text-foreground">{{
                    paginator.to
                }}</span>
                of
                <span class="font-medium text-foreground">{{
                    paginator.total
                }}</span>
            </template>
            <template v-else>No results</template>
        </p>
        <div class="flex items-center gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="!previous"
                :as="previous ? Link : 'button'"
                :href="previous ?? undefined"
                preserve-scroll
                preserve-state
            >
                <ChevronLeft class="size-4" /> Previous
            </Button>
            <span class="tabular-nums">
                {{ paginator.current_page }} / {{ paginator.last_page }}
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!next"
                :as="next ? Link : 'button'"
                :href="next ?? undefined"
                preserve-scroll
                preserve-state
            >
                Next <ChevronRight class="size-4" />
            </Button>
        </div>
    </div>
</template>
