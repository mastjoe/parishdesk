<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Cake,
    CalendarDays,
    FileClock,
    History,
    House,
    MailPlus,
    Palette,
    ShieldCheck,
    Sparkles,
    Upload,
    UserCheck,
    UserPlus,
    Users,
    UsersRound,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
import Can from '@/components/app/Can.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { getInitials } from '@/composables/useInitials';
import { formatRelative } from '@/lib/format';
import { formatPlainDate, inDays } from '@/lib/people';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import { index as activityIndex } from '@/routes/admin/activity';
import { index as changeRequestsIndex } from '@/routes/admin/change-requests';
import { index as familiesIndex } from '@/routes/admin/families';
import {
    celebrations,
    create as createMember,
    index as membersIndex,
    show as memberShow,
} from '@/routes/admin/members';
import { create as importMembers } from '@/routes/admin/members/import';
import {
    create as createRole,
    index as rolesIndex,
} from '@/routes/admin/roles';
import { edit as appearanceEdit } from '@/routes/admin/settings/appearance';
import { index as usersIndex } from '@/routes/admin/users';
import type { Celebration } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const props = defineProps<{
    stats: {
        members: {
            total: number;
            newThisMonth: number;
            families: number;
            active: number;
            ageGroups: {
                groups: { label: string; count: number }[];
                unknown: number;
            };
        } | null;
        pendingChangeRequests: number | null;
        users: {
            total: number;
            active: number;
            pendingInvitations: number;
        } | null;
        roles: number | null;
    };
    birthdays: Celebration[] | null;
    recentActivity:
        | {
              id: number;
              description: string;
              causer: string | null;
              createdAt: string | null;
          }[]
        | null;
}>();

const page = usePage();
const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);

const greeting = computed(() => {
    const hour = new Date().getHours();

    return hour < 12
        ? 'Good morning'
        : hour < 17
          ? 'Good afternoon'
          : 'Good evening';
});

const today = new Intl.DateTimeFormat(undefined, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
}).format(new Date());

type Tile = {
    label: string;
    value: number;
    hint?: string;
    icon: LucideIcon;
    /** Classes for the icon chip and the glow behind it. */
    tone: { chip: string; glow: string };
    href?: ReturnType<typeof membersIndex>;
    attention?: boolean;
};

const tones = {
    sky: {
        chip: 'bg-sky-500/12 text-sky-600 dark:text-sky-400',
        glow: 'bg-sky-500/20',
    },
    emerald: {
        chip: 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-400',
        glow: 'bg-emerald-500/20',
    },
    violet: {
        chip: 'bg-violet-500/12 text-violet-600 dark:text-violet-400',
        glow: 'bg-violet-500/20',
    },
    amber: {
        chip: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        glow: 'bg-amber-500/25',
    },
};

const tiles = computed<Tile[]>(() => {
    const { members, pendingChangeRequests } = props.stats;
    const list: Tile[] = [];

    if (members) {
        list.push(
            {
                label: 'Members',
                value: members.total,
                hint: `${members.active} active`,
                icon: UsersRound,
                tone: tones.sky,
                href: membersIndex(),
            },
            {
                label: 'Families',
                value: members.families,
                hint: members.families
                    ? `About ${Math.round(members.total / members.families)} people each`
                    : 'None yet',
                icon: House,
                tone: tones.emerald,
                href: familiesIndex(),
            },
            {
                label: 'New this month',
                value: members.newThisMonth,
                hint: 'Added to the register',
                icon: Sparkles,
                tone: tones.violet,
            },
        );
    }

    if (pendingChangeRequests !== null) {
        list.push({
            label: 'Change requests',
            value: pendingChangeRequests,
            hint: pendingChangeRequests
                ? 'Waiting for review'
                : 'All caught up',
            icon: FileClock,
            tone: tones.amber,
            href: changeRequestsIndex(),
            attention: pendingChangeRequests > 0,
        });
    }

    return list;
});

// Members by age: one series, so one hue and no legend; the title names it.
const ageChart = computed(() => {
    const groups = props.stats.members?.ageGroups.groups ?? [];
    const max = Math.max(1, ...groups.map((group) => group.count));
    const total = groups.reduce((sum, group) => sum + group.count, 0);

    return groups.map((group) => ({
        ...group,
        height: (group.count / max) * 100,
        share: total ? Math.round((group.count / total) * 100) : 0,
    }));
});
const hoveredAge = ref<string | null>(null);

const team = computed(() =>
    [
        props.stats.users && {
            label: 'Staff users',
            value: props.stats.users.total,
            icon: Users,
            href: usersIndex(),
        },
        props.stats.users && {
            label: 'Active accounts',
            value: props.stats.users.active,
            icon: UserCheck,
            href: usersIndex(),
        },
        props.stats.users && {
            label: 'Invitations out',
            value: props.stats.users.pendingInvitations,
            icon: MailPlus,
            href: usersIndex(),
        },
        props.stats.roles !== null && {
            label: 'Roles',
            value: props.stats.roles,
            icon: ShieldCheck,
            href: rolesIndex(),
        },
    ].filter((item) => !!item),
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <!-- Welcome -->
        <section
            class="relative isolate overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-primary/75 p-6 text-primary-foreground shadow-sm md:p-8"
        >
            <div
                class="pointer-events-none absolute -top-24 -right-16 -z-10 size-72 rounded-full bg-primary-foreground/10 blur-2xl"
            />
            <div
                class="pointer-events-none absolute -bottom-28 left-1/3 -z-10 size-64 rounded-full bg-primary-foreground/10 blur-3xl"
            />
            <div
                class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="space-y-2">
                    <p
                        class="inline-flex items-center gap-1.5 rounded-full bg-primary-foreground/15 px-2.5 py-1 text-xs font-medium"
                    >
                        <CalendarDays class="size-3.5" /> {{ today }}
                    </p>
                    <h1
                        class="text-2xl font-semibold tracking-tight md:text-3xl"
                    >
                        {{ greeting }}, {{ firstName }}
                    </h1>
                    <p class="max-w-xl text-sm opacity-85">
                        Here is what is happening at
                        {{ page.props.parish.name }}.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Can permission="members.create">
                        <Link
                            :href="createMember()"
                            class="inline-flex h-9 items-center gap-2 rounded-lg bg-primary-foreground px-3.5 text-sm font-medium text-primary shadow-sm transition-transform hover:-translate-y-0.5"
                        >
                            <UserPlus class="size-4" /> Register a member
                        </Link>
                    </Can>
                    <Can permission="members.import">
                        <Link
                            :href="importMembers()"
                            class="inline-flex h-9 items-center gap-2 rounded-lg bg-primary-foreground/15 px-3.5 text-sm font-medium ring-1 ring-primary-foreground/25 transition-colors hover:bg-primary-foreground/25"
                        >
                            <Upload class="size-4" /> Import
                        </Link>
                    </Can>
                    <Can permission="members.view">
                        <Link
                            :href="celebrations()"
                            class="inline-flex h-9 items-center gap-2 rounded-lg bg-primary-foreground/15 px-3.5 text-sm font-medium ring-1 ring-primary-foreground/25 transition-colors hover:bg-primary-foreground/25"
                        >
                            <Cake class="size-4" /> Celebrations
                        </Link>
                    </Can>
                </div>
            </div>
        </section>

        <!-- Headline numbers -->
        <div
            v-if="tiles.length"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <component
                :is="tile.href ? Link : 'div'"
                v-for="tile in tiles"
                :key="tile.label"
                :href="tile.href"
                :class="
                    cn(
                        'group relative isolate flex flex-col gap-4 overflow-hidden rounded-2xl border bg-card p-5 shadow-xs transition-all',
                        tile.href && 'hover:-translate-y-0.5 hover:shadow-md',
                        tile.attention && 'ring-2 ring-amber-500/40',
                    )
                "
            >
                <div
                    :class="
                        cn(
                            'pointer-events-none absolute -top-10 -right-10 -z-10 size-32 rounded-full blur-2xl transition-opacity group-hover:opacity-100',
                            tile.tone.glow,
                            'opacity-60',
                        )
                    "
                />
                <div class="flex items-center justify-between">
                    <span
                        :class="
                            cn(
                                'flex size-10 items-center justify-center rounded-xl',
                                tile.tone.chip,
                            )
                        "
                    >
                        <component :is="tile.icon" class="size-5" />
                    </span>
                    <ArrowRight
                        v-if="tile.href"
                        class="size-4 text-muted-foreground opacity-0 transition-all group-hover:translate-x-0.5 group-hover:opacity-100"
                    />
                </div>
                <div>
                    <p
                        class="text-3xl font-semibold tracking-tight tabular-nums"
                    >
                        {{ tile.value.toLocaleString() }}
                    </p>
                    <p class="mt-1 text-sm font-medium">{{ tile.label }}</p>
                    <p v-if="tile.hint" class="text-xs text-muted-foreground">
                        {{ tile.hint }}
                    </p>
                </div>
            </component>
        </div>

        <div
            v-if="stats.members || birthdays"
            class="grid gap-6 lg:grid-cols-3"
        >
            <!-- Members by age -->
            <section
                v-if="stats.members"
                class="flex flex-col rounded-2xl border bg-card p-5 shadow-xs lg:col-span-2"
            >
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-semibold">Members by age</h2>
                        <p class="text-sm text-muted-foreground">
                            Living members, in the groups parish ministries work
                            with.
                        </p>
                    </div>
                    <span
                        v-if="stats.members.ageGroups.unknown"
                        class="shrink-0 rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                    >
                        {{ stats.members.ageGroups.unknown }} without a birth
                        date
                    </span>
                </header>

                <div
                    class="mt-6 grid h-52 grid-cols-5 items-end gap-2 border-b sm:gap-4"
                    role="img"
                    :aria-label="`Members by age: ${ageChart.map((group) => `${group.label} ${group.count}`).join(', ')}`"
                >
                    <div
                        v-for="group in ageChart"
                        :key="group.label"
                        class="relative flex h-full flex-col items-center justify-end"
                        @mouseenter="hoveredAge = group.label"
                        @mouseleave="hoveredAge = null"
                    >
                        <div
                            v-if="hoveredAge === group.label"
                            class="absolute z-10 -translate-y-2 rounded-md border bg-popover px-2.5 py-1.5 text-xs whitespace-nowrap text-popover-foreground shadow-md"
                            :style="{ bottom: `${group.height}%` }"
                        >
                            <span class="font-medium">{{ group.label }}</span>
                            <span class="block text-muted-foreground"
                                >{{ group.count }} members ·
                                {{ group.share }}%</span
                            >
                        </div>
                        <span class="mb-1 text-sm font-semibold tabular-nums">{{
                            group.count
                        }}</span>
                        <div
                            :class="
                                cn(
                                    'w-full max-w-16 rounded-t-[4px] bg-primary transition-[height,opacity] duration-500',
                                    hoveredAge &&
                                        hoveredAge !== group.label &&
                                        'opacity-40',
                                )
                            "
                            :style="{
                                height: `${Math.max(group.height, group.count ? 2 : 0)}%`,
                            }"
                        />
                    </div>
                </div>
                <div class="mt-2 grid grid-cols-5 gap-2 sm:gap-4">
                    <span
                        v-for="group in ageChart"
                        :key="group.label"
                        class="text-center text-xs leading-tight text-muted-foreground"
                    >
                        {{ group.label.split(' (')[0] }}
                        <span class="block opacity-75">{{
                            group.label.match(/\((.*)\)/)?.[1]
                        }}</span>
                    </span>
                </div>
                <table class="sr-only">
                    <caption>
                        Members by age
                    </caption>
                    <tr v-for="group in ageChart" :key="group.label">
                        <th scope="row">{{ group.label }}</th>
                        <td>{{ group.count }}</td>
                    </tr>
                </table>
            </section>

            <!-- Birthdays -->
            <section
                v-if="birthdays"
                class="flex flex-col rounded-2xl border bg-card shadow-xs"
            >
                <header class="flex items-center justify-between px-5 pt-5">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <span
                            class="flex size-8 items-center justify-center rounded-lg bg-rose-500/12 text-rose-600 dark:text-rose-400"
                        >
                            <Cake class="size-4" />
                        </span>
                        Birthdays this week
                    </h2>
                    <Link
                        :href="celebrations()"
                        class="text-sm font-medium text-primary hover:underline"
                    >
                        All
                    </Link>
                </header>
                <ul v-if="birthdays.length" class="flex flex-col gap-1 p-3">
                    <li v-for="person in birthdays" :key="person.id">
                        <Link
                            :href="memberShow(person.id)"
                            class="flex items-center gap-3 rounded-lg px-2 py-2 text-sm transition-colors hover:bg-muted/60"
                        >
                            <MemberAvatar
                                :name="person.name"
                                :photo-url="person.photoUrl"
                                class="size-9"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{
                                    person.name
                                }}</span>
                                <span class="text-xs text-muted-foreground"
                                    >Turns {{ person.years }}</span
                                >
                            </span>
                            <span
                                :class="
                                    cn(
                                        'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                                        person.inDays === 0
                                            ? 'bg-rose-500 text-white'
                                            : 'bg-muted text-muted-foreground',
                                    )
                                "
                            >
                                {{
                                    person.inDays < 2
                                        ? inDays(person.inDays)
                                        : formatPlainDate(person.date, {
                                              weekday: 'short',
                                              day: 'numeric',
                                          })
                                }}
                            </span>
                        </Link>
                    </li>
                </ul>
                <div
                    v-else
                    class="flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center text-sm text-muted-foreground"
                >
                    <Cake class="size-6 opacity-50" />
                    No birthdays in the next seven days.
                </div>
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Recent activity -->
            <section
                v-if="recentActivity"
                class="flex flex-col rounded-2xl border bg-card shadow-xs lg:col-span-2"
            >
                <header class="flex items-center justify-between px-5 pt-5">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <span
                            class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <History class="size-4" />
                        </span>
                        Recent activity
                    </h2>
                    <Link
                        :href="activityIndex()"
                        class="text-sm font-medium text-primary hover:underline"
                    >
                        View all
                    </Link>
                </header>
                <ol v-if="recentActivity.length" class="relative p-5 pt-4">
                    <li
                        v-for="(entry, position) in recentActivity"
                        :key="entry.id"
                        class="relative flex gap-3 pb-4 last:pb-0"
                    >
                        <span
                            v-if="position < recentActivity.length - 1"
                            class="absolute top-8 bottom-0 left-4 w-px bg-border"
                        />
                        <span
                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-muted-foreground"
                        >
                            {{ getInitials(entry.causer ?? 'System') }}
                        </span>
                        <div class="min-w-0 flex-1 pt-1">
                            <p class="text-sm">{{ entry.description }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ entry.causer ?? 'System' }} ·
                                {{ formatRelative(entry.createdAt) }}
                            </p>
                        </div>
                    </li>
                </ol>
                <p v-else class="p-5 text-sm text-muted-foreground">
                    Nothing recorded yet.
                </p>
            </section>

            <div class="flex flex-col gap-6">
                <!-- Team and access -->
                <section
                    v-if="team.length"
                    class="rounded-2xl border bg-card p-5 shadow-xs"
                >
                    <h2 class="font-semibold">Team and access</h2>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <Link
                            v-for="item in team"
                            :key="item.label"
                            :href="item.href"
                            class="group rounded-xl bg-muted/50 p-3 transition-colors hover:bg-muted"
                        >
                            <component
                                :is="item.icon"
                                class="size-4 text-muted-foreground group-hover:text-primary"
                            />
                            <p class="mt-2 text-xl font-semibold tabular-nums">
                                {{ item.value }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ item.label }}
                            </p>
                        </Link>
                    </div>
                </section>

                <!-- Setup shortcuts -->
                <Can
                    :any="[
                        'roles.create',
                        'users.invite',
                        'settings.appearance.update',
                    ]"
                >
                    <section class="rounded-2xl border bg-card p-5 shadow-xs">
                        <h2 class="font-semibold">Set up the parish</h2>
                        <div class="mt-3 flex flex-col gap-1">
                            <Can permission="roles.create">
                                <Link
                                    :href="createRole()"
                                    class="group flex items-center gap-3 rounded-lg px-2 py-2 text-sm transition-colors hover:bg-muted/60"
                                >
                                    <ShieldCheck class="size-4 text-primary" />
                                    <span class="flex-1">Create a role</span>
                                    <ArrowRight
                                        class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                    />
                                </Link>
                            </Can>
                            <Can permission="users.invite">
                                <Link
                                    :href="usersIndex()"
                                    class="group flex items-center gap-3 rounded-lg px-2 py-2 text-sm transition-colors hover:bg-muted/60"
                                >
                                    <MailPlus class="size-4 text-primary" />
                                    <span class="flex-1">Invite staff</span>
                                    <ArrowRight
                                        class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                    />
                                </Link>
                            </Can>
                            <Can permission="settings.appearance.update">
                                <Link
                                    :href="appearanceEdit()"
                                    class="group flex items-center gap-3 rounded-lg px-2 py-2 text-sm transition-colors hover:bg-muted/60"
                                >
                                    <Palette class="size-4 text-primary" />
                                    <span class="flex-1"
                                        >Choose the parish theme</span
                                    >
                                    <ArrowRight
                                        class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                    />
                                </Link>
                            </Can>
                        </div>
                    </section>
                </Can>
            </div>
        </div>
    </div>
</template>
