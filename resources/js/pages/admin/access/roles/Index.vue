<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import {
    Copy,
    Crown,
    MoreHorizontal,
    Pencil,
    Plus,
    ShieldCheck,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DataTable from '@/components/app/DataTable.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { create, destroy, duplicate, edit, index } from '@/routes/admin/roles';

type RoleRow = {
    id: number;
    name: string;
    description: string | null;
    color: string;
    isSuperAdmin: boolean;
    usersCount: number;
    permissionsCount: number;
    can: { update: boolean; delete: boolean; duplicate: boolean };
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roles', href: index() }],
    },
});

const props = defineProps<{
    roles: RoleRow[];
    can: { create: boolean };
}>();

// Roles are few and all loaded at once, so search filters them in place.
const search = ref('');
const filteredRoles = computed(() => {
    const words = search.value.toLowerCase().split(/\s+/).filter(Boolean);

    return props.roles.filter((role) => {
        const haystack =
            `${roleName(role)} ${role.description ?? ''}`.toLowerCase();

        return words.every((word) => haystack.includes(word));
    });
});

const columns: ColumnDef<RoleRow, unknown>[] = [
    { id: 'name', header: 'Role' },
    { id: 'type', header: 'Type', meta: { class: 'hidden sm:table-cell' } },
    { id: 'users', header: 'Users', meta: { class: 'w-24' } },
    {
        id: 'permissions',
        header: 'Permissions',
        meta: { class: 'hidden w-32 md:table-cell' },
    },
    { id: 'actions', header: '', meta: { class: 'w-12 text-right' } },
];

const roleName = (role: RoleRow) =>
    role.isSuperAdmin ? 'Super Admin' : role.name;

const roleDescription = (role: RoleRow) =>
    role.description ||
    (role.isSuperAdmin
        ? 'Full access to everything. Cannot be edited or deleted.'
        : 'No description.');

const deleting = ref<RoleRow | null>(null);
const confirmOpen = ref(false);
const processing = ref(false);

const askDelete = (role: RoleRow) => {
    deleting.value = role;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!deleting.value) {
        return;
    }

    router.delete(destroy(deleting.value.id).url, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => {
            processing.value = false;
            confirmOpen.value = false;
        },
    });
};

const duplicateRole = (role: RoleRow) => {
    router.post(duplicate(role.id).url);
};
</script>

<template>
    <Head title="Roles" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Roles"
            description="Each role is a set of permissions. Give people roles, or individual permissions, from the Users page."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()" data-test="create-role">
                        <Plus class="size-4" /> New role
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <SearchInput
            v-model="search"
            placeholder="Search roles by name or description…"
        />

        <DataTable
            :columns="columns"
            :data="filteredRoles"
            :row-key="(row) => row.id"
        >
            <template #empty>
                <div class="flex flex-col items-center gap-2">
                    <ShieldCheck class="size-6" />
                    No roles match "{{ search }}".
                </div>
            </template>
            <template #cell-name="{ row }">
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Crown v-if="row.isSuperAdmin" class="size-4" />
                        <ShieldCheck v-else class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <component
                            :is="row.can.update ? Link : 'span'"
                            :href="row.can.update ? edit(row.id) : undefined"
                            class="block truncate font-medium"
                            :class="row.can.update ? 'hover:underline' : ''"
                            >{{ roleName(row) }}</component
                        >
                        <p
                            class="line-clamp-1 max-w-md text-xs text-muted-foreground"
                        >
                            {{ roleDescription(row) }}
                        </p>
                    </div>
                </div>
            </template>
            <template #cell-type="{ row }">
                <StatusBadge :tone="row.color">
                    {{ row.isSuperAdmin ? 'Built-in' : 'Custom' }}
                </StatusBadge>
            </template>
            <template #cell-users="{ row }">
                <span class="inline-flex items-center gap-1.5 tabular-nums">
                    <Users class="size-4 text-muted-foreground" />
                    {{ row.usersCount }}
                </span>
            </template>
            <template #cell-permissions="{ row }">
                <span class="tabular-nums">
                    {{ row.isSuperAdmin ? 'All' : row.permissionsCount }}
                </span>
            </template>
            <template #cell-actions="{ row }">
                <DropdownMenu
                    v-if="row.can.update || row.can.duplicate || row.can.delete"
                >
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon" class="size-8">
                            <MoreHorizontal class="size-4" />
                            <span class="sr-only">Actions</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-44">
                        <DropdownMenuItem v-if="row.can.update" as-child>
                            <Link :href="edit(row.id)">
                                <Pencil class="mr-2 size-4" /> Edit
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="row.can.duplicate"
                            @click="duplicateRole(row)"
                        >
                            <Copy class="mr-2 size-4" /> Duplicate
                        </DropdownMenuItem>
                        <template v-if="row.can.delete">
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                class="text-destructive focus:text-destructive"
                                @click="askDelete(row)"
                            >
                                <Trash2 class="mr-2 size-4" /> Delete
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
        </DataTable>

        <EmptyState
            v-if="roles.length <= 1"
            :icon="ShieldCheck"
            title="No custom roles yet"
            description="Super Admin is the only role so far. Create roles such as Parish Secretary or Finance Officer and pick exactly what each can do."
        >
            <Button v-if="can.create" as-child class="mt-2">
                <Link :href="create()"
                    ><Plus class="size-4" /> Create the first role</Link
                >
            </Button>
        </EmptyState>
    </div>

    <ConfirmDialog
        v-model:open="confirmOpen"
        :title="`Delete ${deleting?.name}?`"
        :description="
            deleting && deleting.usersCount > 0
                ? `${deleting.usersCount} user(s) still have this role. Remove it from them first.`
                : 'This cannot be undone.'
        "
        confirm-label="Delete role"
        destructive
        :processing="processing"
        @confirm="confirmDelete"
    />
</template>
