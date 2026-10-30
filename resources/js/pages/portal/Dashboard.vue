<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronRight,
    Clock,
    HandCoins,
    HeartHandshake,
    Megaphone,
    UserRound,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { show as family } from '@/routes/portal/family';
import { show as profile } from '@/routes/portal/profile';

const props = defineProps<{
    member: {
        name: string;
        firstName: string;
        memberNo: string;
        photoUrl: string | null;
        community: string | null;
        family: { name: string; membersCount: number } | null;
        hasPendingChanges: boolean;
    } | null;
}>();

const page = usePage();
const firstName = computed(
    () => props.member?.firstName ?? page.props.auth.user.name.split(' ')[0],
);

const upcoming = [
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
            <div class="relative flex items-center gap-4">
                <MemberAvatar
                    v-if="member"
                    :name="member.name"
                    :photo-url="member.photoUrl"
                    class="size-14 ring-2 ring-primary-foreground/30"
                />
                <div class="min-w-0">
                    <p class="text-sm opacity-80">
                        {{ page.props.parish.name }}
                    </p>
                    <h1
                        class="mt-1 text-2xl font-semibold tracking-tight md:text-3xl"
                    >
                        Peace be with you, {{ firstName }}
                    </h1>
                    <p
                        v-if="member"
                        class="mt-1 text-sm tabular-nums opacity-80"
                    >
                        Member no. {{ member.memberNo }}
                        <template v-if="member.community">
                            · {{ member.community }}</template
                        >
                    </p>
                </div>
            </div>
        </section>

        <div
            v-if="!member"
            class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-4 text-sm"
        >
            <p class="font-medium">
                Your account is not linked to the register yet
            </p>
            <p class="text-muted-foreground">
                Once the parish office links your account to your member record,
                your family, details and parish records will show here.
            </p>
        </div>

        <div v-else class="grid gap-3 sm:grid-cols-2">
            <Link
                :href="family()"
                class="group flex items-center gap-4 rounded-2xl border bg-card p-4 transition-colors hover:bg-accent/50"
            >
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <UsersRound class="size-5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-medium">My family</span>
                    <span class="block truncate text-sm text-muted-foreground">
                        {{
                            member.family
                                ? `${member.family.name} · ${member.family.membersCount} members`
                                : 'Not on record yet'
                        }}
                    </span>
                </span>
                <ChevronRight
                    class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                />
            </Link>
            <Link
                :href="profile()"
                class="group flex items-center gap-4 rounded-2xl border bg-card p-4 transition-colors hover:bg-accent/50"
            >
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <UserRound class="size-5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-medium">My details</span>
                    <span
                        class="flex items-center gap-1 truncate text-sm text-muted-foreground"
                    >
                        <template v-if="member.hasPendingChanges">
                            <Clock class="size-3.5" /> Changes waiting for
                            review
                        </template>
                        <template v-else
                            >Keep your contacts up to date</template
                        >
                    </span>
                </span>
                <ChevronRight
                    class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                />
            </Link>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div
                v-for="item in upcoming"
                :key="item.title"
                class="flex flex-col gap-3 rounded-2xl border bg-card p-4"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-xl bg-muted text-muted-foreground"
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
