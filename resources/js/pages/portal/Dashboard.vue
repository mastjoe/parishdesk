<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    Cake,
    ChevronRight,
    Clock,
    HandCoins,
    HeartHandshake,
    Hourglass,
    MapPin,
    Megaphone,
    UserRound,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { formatPlainDate, inDays } from '@/lib/people';
import { cn } from '@/lib/utils';
import { show as family } from '@/routes/portal/family';
import { show as profile } from '@/routes/portal/profile';

type Celebration = {
    type: 'birthday' | 'anniversary';
    date: string;
    inDays: number;
    years: number;
};

const props = defineProps<{
    member: {
        name: string;
        firstName: string;
        memberNo: string;
        photoUrl: string | null;
        community: string | null;
        memberSince: number | null;
        family: {
            name: string;
            membersCount: number;
            people: { id: number; name: string; photoUrl: string | null }[];
        } | null;
        celebrations: Celebration[];
        hasPendingChanges: boolean;
    } | null;
}>();

const page = usePage();
const firstName = computed(
    () => props.member?.firstName ?? page.props.auth.user.name.split(' ')[0],
);

const greeting = computed(() => {
    const hour = new Date().getHours();

    return hour < 12
        ? 'Good morning'
        : hour < 17
          ? 'Good afternoon'
          : 'Good evening';
});

const celebrationText = (celebration: Celebration) => {
    const isToday = celebration.inDays === 0;

    if (celebration.type === 'birthday') {
        return isToday
            ? {
                  title: `Happy birthday, ${firstName.value}!`,
                  body: `The parish family celebrates you as you turn ${celebration.years}. May God bless you abundantly.`,
              }
            : {
                  title: `Your birthday is ${inDays(celebration.inDays).toLowerCase()}`,
                  body: `You turn ${celebration.years} on ${formatPlainDate(celebration.date, { weekday: 'long', day: 'numeric', month: 'long' })}.`,
              };
    }

    return isToday
        ? {
              title: 'Happy wedding anniversary!',
              body: `${celebration.years} years of marriage today. May God keep blessing your home.`,
          }
        : {
              title: `Your wedding anniversary is ${inDays(celebration.inDays).toLowerCase()}`,
              body: `${celebration.years} years on ${formatPlainDate(celebration.date, { weekday: 'long', day: 'numeric', month: 'long' })}.`,
          };
};

const upcoming = [
    {
        title: 'Giving & dues',
        description: 'Pay dues and see your giving',
        icon: HandCoins,
        tone: 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-400',
    },
    {
        title: 'Events',
        description: 'Register for parish events',
        icon: CalendarDays,
        tone: 'bg-sky-500/12 text-sky-600 dark:text-sky-400',
    },
    {
        title: 'Societies',
        description: 'Your groups and their news',
        icon: HeartHandshake,
        tone: 'bg-violet-500/12 text-violet-600 dark:text-violet-400',
    },
];
</script>

<template>
    <Head title="Home" />

    <div class="flex flex-col gap-6">
        <!-- Welcome -->
        <section
            class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-primary via-primary to-primary/70 p-6 text-primary-foreground shadow-sm md:p-8"
        >
            <div
                class="pointer-events-none absolute -top-20 -right-16 -z-10 size-64 rounded-full bg-primary-foreground/15 blur-2xl"
            />
            <div
                class="pointer-events-none absolute -bottom-24 -left-10 -z-10 size-56 rounded-full bg-primary-foreground/10 blur-3xl"
            />
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                <MemberAvatar
                    v-if="member"
                    :name="member.name"
                    :photo-url="member.photoUrl"
                    class="size-20 text-xl ring-4 ring-primary-foreground/25"
                />
                <div class="min-w-0 space-y-2">
                    <p class="text-sm opacity-85">
                        {{ greeting }} · {{ page.props.parish.name }}
                    </p>
                    <h1
                        class="text-2xl font-semibold tracking-tight md:text-3xl"
                    >
                        Peace be with you, {{ firstName }}
                    </h1>
                    <div v-if="member" class="flex flex-wrap gap-2 text-xs">
                        <span
                            class="rounded-full bg-primary-foreground/15 px-2.5 py-1 font-medium tabular-nums"
                        >
                            {{ member.memberNo }}
                        </span>
                        <span
                            v-if="member.community"
                            class="inline-flex items-center gap-1 rounded-full bg-primary-foreground/15 px-2.5 py-1 font-medium"
                        >
                            <MapPin class="size-3" /> {{ member.community }}
                        </span>
                        <span
                            v-if="member.memberSince"
                            class="rounded-full bg-primary-foreground/15 px-2.5 py-1 font-medium"
                        >
                            Member since {{ member.memberSince }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <div
            v-if="!member"
            class="flex items-start gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/5 p-4 text-sm"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400"
            >
                <Hourglass class="size-4" />
            </span>
            <div>
                <p class="font-medium">
                    Your account is not linked to the register yet
                </p>
                <p class="text-muted-foreground">
                    Once the parish office links your account to your member
                    record, your family, details and parish records will show
                    here.
                </p>
            </div>
        </div>

        <template v-else>
            <!-- Birthdays and anniversaries coming up -->
            <section
                v-for="celebration in member.celebrations"
                :key="celebration.type"
                :class="
                    cn(
                        'relative isolate flex items-center gap-4 overflow-hidden rounded-2xl p-5',
                        celebration.inDays === 0
                            ? 'bg-gradient-to-r from-rose-500 to-amber-500 text-white shadow-sm'
                            : 'border bg-gradient-to-r from-rose-500/10 via-amber-500/5 to-transparent',
                    )
                "
            >
                <span
                    :class="
                        cn(
                            'flex size-12 shrink-0 items-center justify-center rounded-2xl',
                            celebration.inDays === 0
                                ? 'bg-white/20'
                                : 'bg-rose-500/15 text-rose-600 dark:text-rose-400',
                        )
                    "
                >
                    <Cake
                        v-if="celebration.type === 'birthday'"
                        class="size-6"
                    />
                    <HeartHandshake v-else class="size-6" />
                </span>
                <div class="min-w-0">
                    <p class="font-semibold">
                        {{ celebrationText(celebration).title }}
                    </p>
                    <p
                        :class="
                            cn(
                                'text-sm',
                                celebration.inDays === 0
                                    ? 'opacity-90'
                                    : 'text-muted-foreground',
                            )
                        "
                    >
                        {{ celebrationText(celebration).body }}
                    </p>
                </div>
            </section>

            <!-- My family and my details -->
            <div class="grid gap-4 sm:grid-cols-2">
                <Link
                    :href="family()"
                    class="group relative isolate flex flex-col gap-4 overflow-hidden rounded-2xl border bg-card p-5 shadow-xs transition-all hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div
                        class="pointer-events-none absolute -top-12 -right-12 -z-10 size-36 rounded-full bg-sky-500/15 blur-2xl"
                    />
                    <div class="flex items-center justify-between">
                        <span
                            class="flex size-11 items-center justify-center rounded-xl bg-sky-500/12 text-sky-600 dark:text-sky-400"
                        >
                            <UsersRound class="size-5" />
                        </span>
                        <ChevronRight
                            class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                        />
                    </div>
                    <div>
                        <p class="font-semibold">My family</p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                member.family
                                    ? `${member.family.name} · ${member.family.membersCount} members`
                                    : 'Not on record yet'
                            }}
                        </p>
                    </div>
                    <div
                        v-if="member.family?.people.length"
                        class="flex items-center"
                    >
                        <MemberAvatar
                            v-for="person in member.family.people"
                            :key="person.id"
                            :name="person.name"
                            :photo-url="person.photoUrl"
                            class="-ml-2 size-9 ring-2 ring-card first:ml-0"
                        />
                        <span
                            v-if="
                                member.family.membersCount >
                                member.family.people.length
                            "
                            class="-ml-2 flex size-9 items-center justify-center rounded-full bg-muted text-xs font-medium ring-2 ring-card"
                        >
                            +{{
                                member.family.membersCount -
                                member.family.people.length
                            }}
                        </span>
                    </div>
                </Link>

                <Link
                    :href="profile()"
                    class="group relative isolate flex flex-col gap-4 overflow-hidden rounded-2xl border bg-card p-5 shadow-xs transition-all hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div
                        class="pointer-events-none absolute -top-12 -right-12 -z-10 size-36 rounded-full bg-violet-500/15 blur-2xl"
                    />
                    <div class="flex items-center justify-between">
                        <span
                            class="flex size-11 items-center justify-center rounded-xl bg-violet-500/12 text-violet-600 dark:text-violet-400"
                        >
                            <UserRound class="size-5" />
                        </span>
                        <ChevronRight
                            class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                        />
                    </div>
                    <div>
                        <p class="font-semibold">My details</p>
                        <p class="text-sm text-muted-foreground">
                            Keep your phone, address and emergency contact up to
                            date.
                        </p>
                    </div>
                    <span
                        v-if="member.hasPendingChanges"
                        class="inline-flex w-fit items-center gap-1.5 rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-400"
                    >
                        <Clock class="size-3.5" /> Changes waiting for review
                    </span>
                </Link>
            </div>
        </template>

        <!-- On the way -->
        <section class="flex flex-col gap-3">
            <h2
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                Coming soon
            </h2>
            <div class="grid gap-3 sm:grid-cols-3">
                <div
                    v-for="item in upcoming"
                    :key="item.title"
                    class="flex items-center gap-3 rounded-2xl border border-dashed bg-card/60 p-4 sm:flex-col sm:items-start"
                >
                    <span
                        :class="
                            cn(
                                'flex size-10 shrink-0 items-center justify-center rounded-xl',
                                item.tone,
                            )
                        "
                    >
                        <component :is="item.icon" class="size-5" />
                    </span>
                    <div>
                        <p class="text-sm font-medium">{{ item.title }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ item.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Announcements -->
        <section
            class="flex flex-col items-center gap-3 rounded-2xl border bg-gradient-to-b from-card to-muted/30 px-6 py-10 text-center"
        >
            <span
                class="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary"
            >
                <Megaphone class="size-6" />
            </span>
            <div>
                <p class="font-semibold">No announcements yet</p>
                <p class="text-sm text-muted-foreground">
                    Parish announcements will show up here as soon as they are
                    published.
                </p>
            </div>
        </section>
    </div>
</template>
