<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { MailCheck, RotateCw, Users, X } from '@lucide/vue';
import InviteUserDialog from '@/components/access/InviteUserDialog.vue';
import DataTable from '@/components/app/DataTable.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { getInitials } from '@/composables/useInitials';
import { useTableQuery } from '@/composables/useTableQuery';
import { formatDate, formatRelative } from '@/lib/format';
import { destroy, resend } from '@/routes/admin/invitations';
import { index, show } from '@/routes/admin/users';
import type { CatalogueGroup, Paginated, RoleBadge, TableState } from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    isActive: boolean;
    lastLoginAt: string | null;
    createdAt: string | null;
    roles: RoleBadge[];
    directPermissionsCount: number;
};

type InvitationRow = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    status: 'pending' | 'expired' | 'revoked' | 'accepted';
    expiresAt: string;
    invitedBy: string | null;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});

const props = defineProps<{
    users: Paginated<UserRow>;
    table: TableState;
    roles: RoleBadge[];
    catalogue: CatalogueGroup[];
    grantable: string[];
    invitations: InvitationRow[];
    can: { invite: boolean };
}>();

const { search, filters, toggleSort, sortDirection } = useTableQuery(
    props.table,
);

const roleLabel = (name: string) =>
    name === 'super-admin' ? 'Super Admin' : name;

const columns: ColumnDef<UserRow, unknown>[] = [
    { id: 'name', header: 'User', meta: { sortKey: 'name' } },
    { id: 'roles', header: 'Access' },
    {
        id: 'status',
        header: 'Status',
        meta: { class: 'hidden md:table-cell' },
    },
    {
        id: 'last_login_at',
        header: 'Last sign in',
        meta: { sortKey: 'last_login_at', class: 'hidden lg:table-cell' },
    },
];

const invitationTone: Record<InvitationRow['status'], string> = {
    pending: 'blue',
    expired: 'amber',
    revoked: 'slate',
    accepted: 'green',
};

const resendInvitation = (invitation: InvitationRow) =>
    router.post(resend(invitation.id).url, {}, { preserveScroll: true });

const revokeInvitation = (invitation: InvitationRow) =>
    router.delete(destroy(invitation.id).url, { preserveScroll: true });
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Users"
            description="Staff accounts and the roles and permissions each one holds."
        >
            <template #actions>
                <InviteUserDialog
                    v-if="can.invite"
                    :roles="roles"
                    :catalogue="catalogue"
                    :grantable="grantable"
                />
            </template>
        </PageHeader>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <SearchInput v-model="search" placeholder="Search name or email…" />
            <div class="flex gap-2">
                <Select
                    :model-value="filters.role || 'all'"
                    @update:model-value="
                        filters.role = $event === 'all' ? '' : String($event)
                    "
                >
                    <SelectTrigger class="w-40">
                        <SelectValue placeholder="All roles" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All roles</SelectItem>
                        <SelectItem
                            v-for="role in roles"
                            :key="role.name"
                            :value="role.name"
                        >
                            {{ roleLabel(role.name) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select
                    :model-value="filters.status || 'all'"
                    @update:model-value="
                        filters.status = $event === 'all' ? '' : String($event)
                    "
                >
                    <SelectTrigger class="w-36">
                        <SelectValue placeholder="Any status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Any status</SelectItem>
                        <SelectItem value="active">Active</SelectItem>
                        <SelectItem value="inactive">Deactivated</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <DataTable
            :columns="columns"
            :data="users.data"
            :row-key="(row) => row.id"
            :sort-direction="sortDirection"
            @sort="toggleSort"
        >
            <template #cell-name="{ row }">
                <Link
                    :href="show(row.id)"
                    class="flex items-center gap-3"
                    prefetch
                >
                    <Avatar class="size-9">
                        <AvatarFallback
                            class="bg-primary/10 text-xs font-semibold text-primary"
                        >
                            {{ getInitials(row.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <span class="min-w-0">
                        <span
                            class="block truncate font-medium hover:underline"
                            >{{ row.name }}</span
                        >
                        <span
                            class="block truncate text-xs text-muted-foreground"
                            >{{ row.email }}</span
                        >
                    </span>
                </Link>
            </template>
            <template #cell-roles="{ row }">
                <div class="flex flex-wrap items-center gap-1.5">
                    <StatusBadge
                        v-for="role in row.roles"
                        :key="role.name"
                        :tone="role.color"
                    >
                        {{ roleLabel(role.name) }}
                    </StatusBadge>
                    <StatusBadge
                        v-if="row.directPermissionsCount > 0"
                        tone="primary"
                    >
                        +{{ row.directPermissionsCount }} direct
                    </StatusBadge>
                    <span
                        v-if="
                            row.roles.length === 0 &&
                            row.directPermissionsCount === 0
                        "
                        class="text-xs text-muted-foreground"
                        >Member portal only</span
                    >
                </div>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :tone="row.isActive ? 'green' : 'red'">
                    {{ row.isActive ? 'Active' : 'Deactivated' }}
                </StatusBadge>
            </template>
            <template #cell-last_login_at="{ row }">
                <span class="text-muted-foreground">{{
                    formatRelative(row.lastLoginAt)
                }}</span>
            </template>
            <template #empty>
                <div class="flex flex-col items-center gap-2">
                    <Users class="size-6" />
                    No users match these filters.
                </div>
            </template>
        </DataTable>

        <Pagination :paginator="users" />

        <section
            v-if="can.invite && invitations.length > 0"
            class="flex flex-col gap-3"
        >
            <h2 class="flex items-center gap-2 text-base font-semibold">
                <MailCheck class="size-4 text-primary" /> Invitations
            </h2>
            <ul class="divide-y overflow-hidden rounded-xl border bg-card">
                <li
                    v-for="invitation in invitations"
                    :key="invitation.id"
                    class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">
                            {{ invitation.name }}
                            <span class="font-normal text-muted-foreground"
                                >· {{ invitation.email }}</span
                            >
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="invitation.roles.length">
                                {{ invitation.roles.map(roleLabel).join(', ') }}
                                ·
                            </template>
                            Invited by {{ invitation.invitedBy ?? 'someone' }} ·
                            expires {{ formatDate(invitation.expiresAt) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <StatusBadge :tone="invitationTone[invitation.status]">
                            {{ invitation.status }}
                        </StatusBadge>
                        <template v-if="invitation.status !== 'revoked'">
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="resendInvitation(invitation)"
                            >
                                <RotateCw class="size-3.5" /> Resend
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:text-destructive"
                                @click="revokeInvitation(invitation)"
                            >
                                <X class="size-3.5" /> Revoke
                            </Button>
                        </template>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
