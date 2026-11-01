<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';

defineProps<{
    name: string;
    photoUrl?: string | null;
    class?: string;
}>();
</script>

<template>
    <Avatar :class="cn('size-9', $props.class)">
        <AvatarImage v-if="photoUrl" :src="photoUrl" :alt="name" />
        <AvatarFallback
            class="bg-primary/10 text-xs font-semibold text-primary"
        >
            {{ getInitials(name) }}
        </AvatarFallback>
    </Avatar>
</template>
