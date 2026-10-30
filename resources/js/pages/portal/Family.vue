<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { House, MapPin } from '@lucide/vue';
import EmptyState from '@/components/app/EmptyState.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import type { MemberSummary } from '@/types';

defineProps<{
    family: {
        name: string;
        address: string | null;
        community: string | null;
        members: (MemberSummary & { isMe: boolean })[];
    } | null;
}>();

const roleLabel: Record<string, string> = {
    head: 'Head of family',
    spouse: 'Spouse',
    child: 'Child',
    dependant: 'Dependant',
    other: 'Family member',
};
</script>

<template>
    <Head title="My family" />

    <div class="flex flex-col gap-6">
        <template v-if="family">
            <section
                class="relative overflow-hidden rounded-2xl bg-primary p-6 text-primary-foreground"
            >
                <div
                    class="pointer-events-none absolute -top-12 -right-12 size-44 rounded-full bg-primary-foreground/10"
                />
                <House class="size-6 opacity-80" />
                <h1 class="mt-3 text-2xl font-semibold tracking-tight">
                    {{ family.name }}
                </h1>
                <p class="mt-1 flex flex-wrap gap-x-3 text-sm opacity-80">
                    <span>{{ family.members.length }} members</span>
                    <span v-if="family.community">{{ family.community }}</span>
                    <span
                        v-if="family.address"
                        class="inline-flex items-center gap-1"
                        ><MapPin class="size-3.5" /> {{ family.address }}</span
                    >
                </p>
            </section>

            <ul class="grid gap-3 sm:grid-cols-2">
                <li
                    v-for="relative in family.members"
                    :key="relative.id"
                    class="flex items-center gap-3 rounded-2xl border bg-card p-4"
                >
                    <MemberAvatar
                        :name="relative.name"
                        :photo-url="relative.photoUrl"
                        class="size-12"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ relative.name }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ roleLabel[relative.familyRole ?? 'other'] }}
                        </p>
                    </div>
                    <StatusBadge v-if="relative.isMe" tone="primary"
                        >You</StatusBadge
                    >
                </li>
            </ul>

            <p class="text-center text-sm text-muted-foreground">
                Something missing? Let the parish office know so they can update
                the family record.
            </p>
        </template>

        <EmptyState
            v-else
            :icon="House"
            title="No family on record"
            description="The parish office has not placed you in a family yet. Let them know who lives with you."
            class="bg-card"
        />
    </div>
</template>
