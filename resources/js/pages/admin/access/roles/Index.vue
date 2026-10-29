<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
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
import { ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
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

defineProps<{
    roles: RoleRow[];
    can: { create: boolean };
}>();

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

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="role in roles"
                :key="role.id"
                class="group flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-xs transition-shadow hover:shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Crown v-if="role.isSuperAdmin" class="size-5" />
                            <ShieldCheck v-else class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="truncate font-semibold">
                                {{
                                    role.isSuperAdmin
                                        ? 'Super Admin'
                                        : role.name
                                }}
                            </h2>
                            <StatusBadge :tone="role.color">
                                {{ role.isSuperAdmin ? 'Built-in' : 'Custom' }}
                            </StatusBadge>
                        </div>
                    </div>

                    <DropdownMenu
                        v-if="
                            role.can.update ||
                            role.can.duplicate ||
                            role.can.delete
                        "
                    >
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="-mr-2 size-8"
                            >
                                <MoreHorizontal class="size-4" />
                                <span class="sr-only">Actions</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-44">
                            <DropdownMenuItem v-if="role.can.update" as-child>
                                <Link :href="edit(role.id)">
                                    <Pencil class="mr-2 size-4" /> Edit
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="role.can.duplicate"
                                @click="duplicateRole(role)"
                            >
                                <Copy class="mr-2 size-4" /> Duplicate
                            </DropdownMenuItem>
                            <template v-if="role.can.delete">
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    class="text-destructive focus:text-destructive"
                                    @click="askDelete(role)"
                                >
                                    <Trash2 class="mr-2 size-4" /> Delete
                                </DropdownMenuItem>
                            </template>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <p class="line-clamp-2 min-h-10 text-sm text-muted-foreground">
                    {{
                        role.description ||
                        (role.isSuperAdmin
                            ? 'Full access to everything. Cannot be edited or deleted.'
                            : 'No description.')
                    }}
                </p>

                <div
                    class="mt-auto flex items-center gap-4 border-t pt-4 text-sm text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1.5">
                        <Users class="size-4" />
                        <span
                            class="font-medium text-foreground tabular-nums"
                            >{{ role.usersCount }}</span
                        >
                        {{ role.usersCount === 1 ? 'user' : 'users' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <ShieldCheck class="size-4" />
                        <span
                            class="font-medium text-foreground tabular-nums"
                            >{{ role.permissionsCount }}</span
                        >
                        permissions
                    </span>
                </div>
            </article>
        </div>

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
