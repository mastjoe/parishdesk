<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    History,
    MailPlus,
    Palette,
    ShieldCheck,
    UserCheck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import Can from '@/components/app/Can.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatCard from '@/components/app/StatCard.vue';
import { formatRelative } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import { index as activityIndex } from '@/routes/admin/activity';
import { create as createRole } from '@/routes/admin/roles';
import { edit as appearanceEdit } from '@/routes/admin/settings/appearance';
import { index as usersIndex } from '@/routes/admin/users';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

defineProps<{
    stats: {
        users: {
            total: number;
            active: number;
            pendingInvitations: number;
        } | null;
        roles: number | null;
    };
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
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`${greeting}, ${firstName}`"
            :description="`Here is what is happening at ${page.props.parish.name}.`"
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <template v-if="stats.users">
                <StatCard
                    label="Staff users"
                    :value="stats.users.total"
                    :icon="Users"
                />
                <StatCard
                    label="Active accounts"
                    :value="stats.users.active"
                    :icon="UserCheck"
                />
                <StatCard
                    label="Pending invitations"
                    :value="stats.users.pendingInvitations"
                    :icon="MailPlus"
                />
            </template>
            <StatCard
                v-if="stats.roles !== null"
                label="Roles"
                :value="stats.roles"
                hint="Including Super Admin"
                :icon="ShieldCheck"
            />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <section
                v-if="recentActivity"
                class="flex flex-col rounded-xl border bg-card lg:col-span-2"
            >
                <header
                    class="flex items-center justify-between border-b px-5 py-4"
                >
                    <h2 class="flex items-center gap-2 font-semibold">
                        <History class="size-4 text-primary" /> Recent activity
                    </h2>
                    <Link
                        :href="activityIndex()"
                        class="text-sm font-medium text-primary hover:underline"
                    >
                        View all
                    </Link>
                </header>
                <ul v-if="recentActivity.length" class="divide-y">
                    <li
                        v-for="entry in recentActivity"
                        :key="entry.id"
                        class="flex items-center justify-between gap-4 px-5 py-3 text-sm"
                    >
                        <span class="min-w-0 truncate">{{
                            entry.description
                        }}</span>
                        <span class="shrink-0 text-xs text-muted-foreground">
                            {{ entry.causer ?? 'System' }} ·
                            {{ formatRelative(entry.createdAt) }}
                        </span>
                    </li>
                </ul>
                <p v-else class="p-5 text-sm text-muted-foreground">
                    Nothing recorded yet.
                </p>
            </section>

            <section class="flex flex-col gap-3 rounded-xl border bg-card p-5">
                <h2 class="font-semibold">Get started</h2>
                <p class="text-sm text-muted-foreground">
                    Set up who can do what before inviting the parish team.
                </p>
                <Can permission="roles.create">
                    <Link
                        :href="createRole()"
                        class="flex items-center gap-3 rounded-lg border p-3 text-sm transition-colors hover:bg-accent"
                    >
                        <ShieldCheck class="size-4 text-primary" /> Create a
                        role
                    </Link>
                </Can>
                <Can permission="users.invite">
                    <Link
                        :href="usersIndex()"
                        class="flex items-center gap-3 rounded-lg border p-3 text-sm transition-colors hover:bg-accent"
                    >
                        <MailPlus class="size-4 text-primary" /> Invite staff
                    </Link>
                </Can>
                <Can permission="settings.appearance.update">
                    <Link
                        :href="appearanceEdit()"
                        class="flex items-center gap-3 rounded-lg border p-3 text-sm transition-colors hover:bg-accent"
                    >
                        <Palette class="size-4 text-primary" /> Choose the
                        parish theme
                    </Link>
                </Can>
            </section>
        </div>
    </div>
</template>
