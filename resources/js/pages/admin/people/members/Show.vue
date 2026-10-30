<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    Cake,
    Home,
    KeyRound,
    MailCheck,
    Pencil,
    Phone,
    Trash2,
    UsersRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate, formatRelative } from '@/lib/format';
import {
    formatPlainDate,
    memberFieldLabels,
    memberStatusTone,
    optionLabel,
} from '@/lib/people';
import { index as changeRequestsIndex } from '@/routes/admin/change-requests';
import { show as familyShow } from '@/routes/admin/families';
import { destroy, edit, index, show } from '@/routes/admin/members';
import { store as storeInvitation } from '@/routes/admin/members/invitation';
import type {
    MemberFields,
    MemberRow,
    MemberSummary,
    PeopleOptions,
} from '@/types';

type ChangeRequest = {
    id: number;
    status: 'pending' | 'approved' | 'rejected';
    changes: Record<string, string | null>;
    original: Record<string, string | null>;
    note: string | null;
    reviewNote: string | null;
    reviewer: string | null;
    createdAt: string | null;
    reviewedAt: string | null;
};

const props = defineProps<{
    member: MemberRow &
        MemberFields & {
            genderLabel: string;
            maritalStatus: string | null;
            familyRoleLabel: string | null;
            statusLabel: string;
            createdAt: string | null;
        };
    family: {
        id: number;
        name: string;
        address: string | null;
        community: string | null;
        members: MemberSummary[];
    } | null;
    portal: {
        account: { email: string; lastLoginAt: string | null } | null;
        invitation: {
            id: number;
            email: string;
            status: 'pending' | 'expired' | 'revoked' | 'accepted';
            expiresAt: string;
        } | null;
    };
    changeRequests: ChangeRequest[];
    activity: {
        id: number;
        description: string;
        event: string | null;
        causer: string | null;
        createdAt: string | null;
    }[];
    options: PeopleOptions;
    can: {
        update: boolean;
        delete: boolean;
        invite: boolean;
        reviewChanges: boolean;
        viewFamily: boolean;
    };
    invitationValidDays: number;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Members', href: index() },
        { title: props.member.name, href: show(props.member.id) },
    ],
});

const details = computed(() => [
    { label: 'Baptismal name', value: props.member.baptismal_name },
    { label: 'Gender', value: props.member.genderLabel },
    {
        label: 'Date of birth',
        value: props.member.date_of_birth
            ? `${formatPlainDate(props.member.date_of_birth)}${props.member.age !== null ? ` (${props.member.age})` : ''}`
            : null,
    },
    { label: 'Marital status', value: props.member.maritalStatus },
    {
        label: 'Wedding date',
        value: props.member.wedding_date
            ? formatPlainDate(props.member.wedding_date)
            : null,
    },
    { label: 'Occupation', value: props.member.occupation },
    { label: 'Phone', value: props.member.phone },
    { label: 'Other phone', value: props.member.alt_phone },
    { label: 'Email', value: props.member.email },
    { label: 'Address', value: props.member.address, wide: true },
    { label: 'State of origin', value: props.member.state_of_origin },
    { label: 'LGA of origin', value: props.member.lga_of_origin },
    { label: 'Community', value: props.member.community },
    {
        label: 'Joined the parish',
        value: props.member.joined_at
            ? formatPlainDate(props.member.joined_at)
            : null,
    },
]);

const pendingChanges = computed(() =>
    props.changeRequests.filter((change) => change.status === 'pending'),
);

const changeTone = { pending: 'amber', approved: 'green', rejected: 'red' };

// Removing the member
const deleteOpen = ref(false);
const deleting = ref(false);
const remove = () => {
    deleting.value = true;
    router.delete(destroy(props.member.id).url, {
        onFinish: () => (deleting.value = false),
    });
};

// Portal invitation
const invite = useForm({
    email: props.portal.invitation?.email ?? props.member.email ?? '',
});
const sendInvitation = () =>
    invite.submit(storeInvitation(props.member.id), { preserveScroll: true });

const invitationTone: Record<string, string> = {
    pending: 'blue',
    expired: 'amber',
    revoked: 'slate',
    accepted: 'green',
};

const valueOf = (field: string, value: string | null) => {
    if (value === null || value === '') {
        return '(empty)';
    }

    if (field === 'marital_status') {
        return optionLabel(props.options.maritalStatuses, value);
    }

    return field.endsWith('date') || field === 'date_of_birth'
        ? formatPlainDate(value)
        : value;
};
</script>

<template>
    <Head :title="member.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <!-- Profile header -->
        <section
            class="relative overflow-hidden rounded-xl border bg-card p-5 md:p-6"
        >
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-20 bg-gradient-to-r from-primary/15 via-primary/5 to-transparent"
            />
            <div
                class="relative flex flex-col gap-4 sm:flex-row sm:items-center"
            >
                <MemberAvatar
                    :name="member.name"
                    :photo-url="member.photoUrl"
                    class="size-20 text-xl ring-4 ring-card"
                />
                <div class="min-w-0 flex-1 space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1
                            class="truncate text-2xl font-semibold tracking-tight"
                        >
                            <span
                                v-if="member.title"
                                class="font-normal text-muted-foreground"
                                >{{ member.title }}
                            </span>
                            {{ member.name }}
                        </h1>
                        <StatusBadge :tone="memberStatusTone[member.status]">
                            {{ member.statusLabel }}
                        </StatusBadge>
                        <StatusBadge v-if="member.hasAccount" tone="primary">
                            Portal
                        </StatusBadge>
                    </div>
                    <p
                        class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground"
                    >
                        <span
                            class="font-medium text-foreground tabular-nums"
                            >{{ member.memberNo }}</span
                        >
                        <span
                            v-if="member.phone"
                            class="inline-flex items-center gap-1"
                            ><Phone class="size-3.5" /> {{ member.phone }}</span
                        >
                        <span
                            v-if="member.date_of_birth"
                            class="inline-flex items-center gap-1"
                            ><Cake class="size-3.5" />
                            {{
                                formatPlainDate(member.date_of_birth, {
                                    day: 'numeric',
                                    month: 'long',
                                })
                            }}</span
                        >
                        <span
                            v-if="family"
                            class="inline-flex items-center gap-1"
                            ><Home class="size-3.5" /> {{ family.name }}</span
                        >
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="can.update"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link :href="edit(member.id)">
                            <Pencil class="size-4" /> Edit
                        </Link>
                    </Button>
                    <Button
                        v-if="can.delete"
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="deleteOpen = true"
                    >
                        <Trash2 class="size-4" /> Remove
                    </Button>
                </div>
            </div>
        </section>

        <div
            v-if="pendingChanges.length && can.reviewChanges"
            class="flex flex-col gap-3 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 text-sm sm:flex-row sm:items-center"
        >
            <p class="flex-1">
                <span class="font-medium">{{ member.name }}</span> asked to
                update their details from the portal.
            </p>
            <Button size="sm" variant="outline" as-child>
                <Link :href="changeRequestsIndex()">Review request</Link>
            </Button>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <Tabs default-value="overview" class="xl:col-span-2">
                <TabsList>
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="family">
                        Family
                        <span
                            v-if="family"
                            class="text-xs text-muted-foreground tabular-nums"
                            >{{ family.members.length + 1 }}</span
                        >
                    </TabsTrigger>
                    <TabsTrigger value="changes">
                        Change requests
                        <span
                            v-if="pendingChanges.length"
                            class="size-1.5 rounded-full bg-amber-500"
                        />
                    </TabsTrigger>
                    <TabsTrigger value="history">History</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" class="flex flex-col gap-4">
                    <dl
                        class="grid gap-x-6 gap-y-5 rounded-xl border bg-card p-5 sm:grid-cols-2"
                    >
                        <div
                            v-for="item in details"
                            :key="item.label"
                            :class="item.wide && 'sm:col-span-2'"
                        >
                            <dt class="text-xs text-muted-foreground">
                                {{ item.label }}
                            </dt>
                            <dd class="mt-0.5 text-sm">
                                {{ item.value || '—' }}
                            </dd>
                        </div>
                    </dl>
                    <div
                        v-if="member.notes"
                        class="rounded-xl border bg-card p-5 text-sm"
                    >
                        <p class="mb-1 text-xs text-muted-foreground">Notes</p>
                        <p class="whitespace-pre-line">{{ member.notes }}</p>
                    </div>
                </TabsContent>

                <TabsContent value="family">
                    <div v-if="family" class="rounded-xl border bg-card">
                        <header
                            class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-4"
                        >
                            <div>
                                <h2 class="font-semibold">{{ family.name }}</h2>
                                <p class="text-xs text-muted-foreground">
                                    {{ member.familyRoleLabel }}
                                    <template v-if="family.community">
                                        · {{ family.community }}</template
                                    >
                                </p>
                            </div>
                            <Button
                                v-if="can.viewFamily"
                                size="sm"
                                variant="outline"
                                as-child
                            >
                                <Link :href="familyShow(family.id)"
                                    >Open family</Link
                                >
                            </Button>
                        </header>
                        <ul v-if="family.members.length" class="divide-y">
                            <li
                                v-for="relative in family.members"
                                :key="relative.id"
                            >
                                <Link
                                    :href="show(relative.id)"
                                    class="flex items-center gap-3 px-5 py-3 hover:bg-muted/40"
                                >
                                    <MemberAvatar
                                        :name="relative.name"
                                        :photo-url="relative.photoUrl"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="block truncate text-sm font-medium"
                                            >{{ relative.name }}</span
                                        >
                                        <span
                                            class="block text-xs text-muted-foreground"
                                        >
                                            {{
                                                optionLabel(
                                                    options.familyRoles,
                                                    relative.familyRole,
                                                )
                                            }}
                                            <template
                                                v-if="relative.age !== null"
                                                >·
                                                {{ relative.age }} yrs</template
                                            >
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        </ul>
                        <p
                            v-else
                            class="px-5 py-6 text-sm text-muted-foreground"
                        >
                            No one else is in this family yet.
                        </p>
                    </div>
                    <EmptyState
                        v-else
                        :icon="UsersRound"
                        title="Not in a family"
                        description="Edit the member to place them in a family, or add them from the family's page."
                    />
                </TabsContent>

                <TabsContent value="changes">
                    <ul
                        v-if="changeRequests.length"
                        class="flex flex-col gap-3"
                    >
                        <li
                            v-for="change in changeRequests"
                            :key="change.id"
                            class="rounded-xl border bg-card p-4"
                        >
                            <div
                                class="mb-3 flex flex-wrap items-center justify-between gap-2"
                            >
                                <p class="text-sm text-muted-foreground">
                                    Sent {{ formatRelative(change.createdAt) }}
                                    <template v-if="change.reviewer">
                                        · {{ change.status }} by
                                        {{ change.reviewer }}</template
                                    >
                                </p>
                                <StatusBadge :tone="changeTone[change.status]">
                                    {{ change.status }}
                                </StatusBadge>
                            </div>
                            <dl class="grid gap-2 text-sm">
                                <div
                                    v-for="(value, field) in change.changes"
                                    :key="field"
                                    class="grid gap-1 sm:grid-cols-3"
                                >
                                    <dt class="text-muted-foreground">
                                        {{ memberFieldLabels[field] ?? field }}
                                    </dt>
                                    <dd class="sm:col-span-2">
                                        <span
                                            class="text-muted-foreground line-through"
                                            >{{
                                                valueOf(
                                                    field,
                                                    change.original[field] ??
                                                        null,
                                                )
                                            }}</span
                                        >
                                        →
                                        <span class="font-medium">{{
                                            valueOf(field, value)
                                        }}</span>
                                    </dd>
                                </div>
                            </dl>
                            <p
                                v-if="change.note"
                                class="mt-3 rounded-lg bg-muted/50 px-3 py-2 text-sm"
                            >
                                “{{ change.note }}”
                            </p>
                            <p
                                v-if="change.reviewNote"
                                class="mt-2 text-sm text-muted-foreground"
                            >
                                Office note: {{ change.reviewNote }}
                            </p>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        :icon="KeyRound"
                        title="No change requests"
                        description="Changes this member asks for from the portal will appear here."
                    />
                </TabsContent>

                <TabsContent value="history">
                    <div class="rounded-xl border bg-card p-5">
                        <ol v-if="activity.length" class="flex flex-col gap-4">
                            <li
                                v-for="entry in activity"
                                :key="entry.id"
                                class="relative border-l pl-4"
                            >
                                <span
                                    class="absolute top-1.5 -left-[5px] size-2.5 rounded-full border-2 border-card bg-primary"
                                />
                                <p class="text-sm">{{ entry.description }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ entry.causer ?? 'System' }} ·
                                    {{ formatRelative(entry.createdAt) }}
                                </p>
                            </li>
                        </ol>
                        <p v-else class="text-sm text-muted-foreground">
                            No changes recorded yet.
                        </p>
                    </div>
                </TabsContent>
            </Tabs>

            <!-- Portal access -->
            <aside class="flex flex-col gap-6">
                <section class="rounded-xl border bg-card">
                    <header class="flex items-center gap-2 border-b px-5 py-4">
                        <MailCheck class="size-4 text-primary" />
                        <h2 class="font-semibold">Portal access</h2>
                    </header>
                    <div class="flex flex-col gap-4 p-5 text-sm">
                        <template v-if="portal.account">
                            <p>
                                Signs in as
                                <span class="font-medium">{{
                                    portal.account.email
                                }}</span>
                            </p>
                            <p class="text-muted-foreground">
                                Last sign in
                                {{ formatRelative(portal.account.lastLoginAt) }}
                            </p>
                        </template>
                        <template v-else>
                            <div
                                v-if="portal.invitation"
                                class="flex items-center justify-between gap-2 rounded-lg bg-muted/50 px-3 py-2"
                            >
                                <span class="min-w-0 truncate"
                                    >Invited {{ portal.invitation.email }}</span
                                >
                                <StatusBadge
                                    :tone="
                                        invitationTone[portal.invitation.status]
                                    "
                                >
                                    {{ portal.invitation.status }}
                                </StatusBadge>
                            </div>
                            <p class="text-muted-foreground">
                                <template
                                    v-if="
                                        portal.invitation?.status === 'pending'
                                    "
                                >
                                    The link expires
                                    {{
                                        formatDate(portal.invitation.expiresAt)
                                    }}. Sending again replaces it.
                                </template>
                                <template v-else>
                                    Email {{ member.name }} a link to set up
                                    their portal account. It stays valid for
                                    {{ invitationValidDays }} days. If someone
                                    already has an account with this email, it
                                    is linked straight away.
                                </template>
                            </p>
                            <form
                                v-if="can.invite"
                                class="grid gap-3"
                                @submit.prevent="sendInvitation"
                            >
                                <div class="grid gap-2">
                                    <Label for="invite-email">Email</Label>
                                    <Input
                                        id="invite-email"
                                        v-model="invite.email"
                                        type="email"
                                        required
                                    />
                                    <InputError
                                        :message="invite.errors.email"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    size="sm"
                                    :disabled="invite.processing"
                                >
                                    {{
                                        portal.invitation?.status === 'pending'
                                            ? 'Send again'
                                            : 'Invite to portal'
                                    }}
                                </Button>
                            </form>
                        </template>
                    </div>
                </section>

                <section class="rounded-xl border bg-card p-5 text-sm">
                    <p class="text-muted-foreground">
                        Added to the register
                        {{ formatDate(member.createdAt) }}
                    </p>
                </section>
            </aside>
        </div>
    </div>

    <ConfirmDialog
        v-model:open="deleteOpen"
        :title="`Remove ${member.name}?`"
        description="They will no longer appear in the register or be able to use the portal. The record is kept for the parish history."
        confirm-label="Remove member"
        destructive
        :processing="deleting"
        @confirm="remove"
    />
</template>
