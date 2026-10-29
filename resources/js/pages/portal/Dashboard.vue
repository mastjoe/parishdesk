<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    HandCoins,
    HeartHandshake,
    Megaphone,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';

const page = usePage();
const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);

const upcoming = [
    { title: 'My family', icon: UsersRound },
    { title: 'Giving & dues', icon: HandCoins },
    { title: 'Events', icon: CalendarDays },
    { title: 'Societies', icon: HeartHandshake },
];
</script>

<template>
    <Head title="Home" />

    <div class="flex flex-col gap-6">
        <section
            class="relative overflow-hidden rounded-2xl bg-primary p-6 text-primary-foreground md:p-8"
        >
            <div
                class="pointer-events-none absolute -top-16 -right-16 size-56 rounded-full bg-primary-foreground/10"
            />
            <p class="text-sm opacity-80">{{ page.props.parish.name }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight md:text-3xl">
                Peace be with you, {{ firstName }}
            </h1>
            <p class="mt-2 max-w-lg text-sm opacity-80">
                Your parish home: announcements, your family, giving and
                sacraments will appear here.
            </p>
        </section>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div
                v-for="item in upcoming"
                :key="item.title"
                class="flex flex-col gap-3 rounded-2xl border bg-card p-4"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <component :is="item.icon" class="size-5" />
                </span>
                <span class="text-sm font-medium">{{ item.title }}</span>
                <span class="text-xs text-muted-foreground">Coming soon</span>
            </div>
        </div>

        <EmptyState
            :icon="Megaphone"
            title="No announcements yet"
            description="Parish announcements will show up here as soon as they are published."
            class="bg-card"
        />
    </div>
</template>
