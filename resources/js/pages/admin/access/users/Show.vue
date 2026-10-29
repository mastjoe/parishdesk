<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    History,
    KeyRound,
    Lock,
    Power,
    ShieldCheck,
    ShieldPlus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PermissionMatrix from '@/components/access/PermissionMatrix.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getInitials } from '@/composables/useInitials';
import { formatDate, formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, passwordReset, update } from '@/routes/admin/users';
import { update as updateAccess } from '@/routes/admin/users/access';
import { update as updateStatus } from '@/routes/admin/users/status';
import type { CatalogueGroup } from '@/types';

type RoleOption = {
    name: string;
    color: string;
    description: string | null;
    isSuperAdmin: boolean;
    permissions: string[];
};

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        isActive: boolean;
        isSuperAdmin: boolean;
        isSelf: boolean;
        lastLoginAt: string | null;
        createdAt: string | null;
        twoFactorEnabled: boolean;
        roles: string[];
        directPermissions: string[];
        effectivePermissions: string[];
    };
    roles: RoleOption[];
    catalogue: CatalogueGroup[];
    grantable: string[];
    activity: {
        id: number;
        description: string;
        event: string | null;
        causer: string | null;
        createdAt: string | null;
    }[];
    can: {
        update: boolean;
        toggleActive: boolean;
        assignRoles: boolean;
        assignPermissions: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});

const page = usePage();

const roleLabel = (name: string) =>
    name === 'super-admin' ? 'Super Admin' : name;

const details = useForm({ name: props.user.name, email: props.user.email });
const rolesForm = useForm({ roles: [...props.user.roles] });
const permissionsForm = useForm({
    permissions: [...props.user.directPermissions],
});

/** Permissions the user gets from the roles currently ticked. */
const inherited = computed(() => {
    const map: Record<string, string[]> = {};

    for (const role of props.roles) {
        if (!rolesForm.roles.includes(role.name)) {
            continue;
        }

        for (const permission of role.permissions) {
            (map[permission] ??= []).push(roleLabel(role.name));
        }
    }

    return map;
});

const effectiveCount = computed(
    () =>
        new Set([
            ...Object.keys(inherited.value),
            ...permissionsForm.permissions,
        ]).size,
);

const toggleRole = (name: string, checked: boolean) => {
    rolesForm.roles = checked
        ? [...rolesForm.roles, name]
        : rolesForm.roles.filter((role) => role !== name);
};

const saveDetails = () =>
    details.submit(update(props.user.id), { preserveScroll: true });

const saveRoles = () =>
    rolesForm.submit(updateAccess(props.user.id), { preserveScroll: true });

const savePermissions = () =>
    permissionsForm.submit(updateAccess(props.user.id), {
        preserveScroll: true,
    });

const statusOpen = ref(false);
const toggleActive = () =>
    router.patch(
        updateStatus(props.user.id).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (statusOpen.value = false),
        },
    );

const sendReset = () =>
    router.post(passwordReset(props.user.id).url, {}, { preserveScroll: true });

const roleAssignable = (role: RoleOption): boolean =>
    props.can.assignRoles &&
    role.permissions.every((permission) =>
        props.grantable.includes(permission),
    ) &&
    (!role.isSuperAdmin || page.props.auth.isSuperAdmin);
</script>

<template>
    <Head :title="user.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <!-- Profile header -->
        <section
            class="flex flex-col gap-4 rounded-xl border bg-card p-5 sm:flex-row sm:items-center"
        >
            <Avatar class="size-14">
                <AvatarFallback
                    class="bg-primary/10 text-lg font-semibold text-primary"
                >
                    {{ getInitials(user.name) }}
                </AvatarFallback>
            </Avatar>
            <div class="min-w-0 flex-1 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="truncate text-xl font-semibold tracking-tight">
                        {{ user.name }}
                    </h1>
                    <StatusBadge :tone="user.isActive ? 'green' : 'red'">
                        {{ user.isActive ? 'Active' : 'Deactivated' }}
                    </StatusBadge>
                    <StatusBadge v-if="user.isSuperAdmin" tone="amber">
                        Super Admin
                    </StatusBadge>
                    <StatusBadge v-if="user.isSelf" tone="primary"
                        >You</StatusBadge
                    >
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ user.email }} · Last sign in
                    {{ formatRelative(user.lastLoginAt) }} · Joined
                    {{ formatDate(user.createdAt) }} · 2FA
                    {{ user.twoFactorEnabled ? 'on' : 'off' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="can.update"
                    variant="outline"
                    size="sm"
                    @click="sendReset"
                >
                    <KeyRound class="size-4" /> Send password reset
                </Button>
                <Button
                    v-if="can.toggleActive"
                    :variant="user.isActive ? 'destructive' : 'default'"
                    size="sm"
                    @click="statusOpen = true"
                >
                    <Power class="size-4" />
                    {{ user.isActive ? 'Deactivate' : 'Reactivate' }}
                </Button>
            </div>
        </section>

        <div
            v-if="user.isSelf"
            class="flex items-start gap-3 rounded-xl border border-primary/30 bg-primary/5 p-4 text-sm"
        >
            <Lock class="mt-0.5 size-4 shrink-0 text-primary" />
            <p>
                You cannot change your own roles or permissions. This stops
                anyone from accidentally locking themselves out. Ask another
                administrator to make changes.
            </p>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="flex flex-col gap-6 xl:col-span-2">
                <!-- Roles -->
                <section class="rounded-xl border bg-card">
                    <header class="flex items-center gap-2 border-b px-5 py-4">
                        <ShieldCheck class="size-4 text-primary" />
                        <h2 class="font-semibold">Roles</h2>
                    </header>
                    <form
                        class="flex flex-col gap-4 p-5"
                        @submit.prevent="saveRoles"
                    >
                        <p
                            v-if="roles.length <= 1"
                            class="text-sm text-muted-foreground"
                        >
                            No custom roles have been created yet.
                        </p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="role in roles"
                                :key="role.name"
                                :class="
                                    cn(
                                        'flex items-start gap-3 rounded-lg border p-3 transition-colors has-data-[state=checked]:border-primary has-data-[state=checked]:bg-primary/5',
                                        roleAssignable(role)
                                            ? 'cursor-pointer hover:bg-muted/30'
                                            : 'cursor-not-allowed opacity-60',
                                    )
                                "
                            >
                                <Checkbox
                                    class="mt-0.5"
                                    :model-value="
                                        rolesForm.roles.includes(role.name)
                                    "
                                    :disabled="!roleAssignable(role)"
                                    :data-test="`role-${role.name}`"
                                    @update:model-value="
                                        toggleRole(role.name, $event === true)
                                    "
                                />
                                <span class="min-w-0">
                                    <span class="flex items-center gap-2">
                                        <span class="text-sm font-medium">{{
                                            roleLabel(role.name)
                                        }}</span>
                                        <StatusBadge :tone="role.color">
                                            {{ role.permissions.length }}
                                        </StatusBadge>
                                    </span>
                                    <span
                                        v-if="role.description"
                                        class="mt-0.5 block text-xs text-muted-foreground"
                                        >{{ role.description }}</span
                                    >
                                </span>
                            </label>
                        </div>
                        <InputError :message="rolesForm.errors.roles" />
                        <div v-if="can.assignRoles" class="flex justify-end">
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="
                                    rolesForm.processing || !rolesForm.isDirty
                                "
                            >
                                Save roles
                            </Button>
                        </div>
                    </form>
                </section>

                <!-- Direct permissions -->
                <section class="rounded-xl border bg-card">
                    <header
                        class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-4"
                    >
                        <div class="flex items-center gap-2">
                            <ShieldPlus class="size-4 text-primary" />
                            <h2 class="font-semibold">Direct permissions</h2>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            <span
                                class="font-medium text-foreground tabular-nums"
                                >{{
                                    user.isSuperAdmin
                                        ? user.effectivePermissions.length
                                        : effectiveCount
                                }}</span
                            >
                            effective permissions
                        </p>
                    </header>
                    <form
                        class="flex flex-col gap-4 p-5"
                        @submit.prevent="savePermissions"
                    >
                        <p class="text-sm text-muted-foreground">
                            Grant extra permissions on top of this user's roles.
                            Locked items already come from a role.
                        </p>
                        <PermissionMatrix
                            v-model="permissionsForm.permissions"
                            :catalogue="catalogue"
                            :grantable="grantable"
                            :inherited="inherited"
                            :disabled="
                                !can.assignPermissions || user.isSuperAdmin
                            "
                        />
                        <InputError
                            :message="permissionsForm.errors.permissions"
                        />
                        <div
                            v-if="can.assignPermissions"
                            class="flex justify-end"
                        >
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="
                                    permissionsForm.processing ||
                                    !permissionsForm.isDirty
                                "
                            >
                                Save permissions
                            </Button>
                        </div>
                    </form>
                </section>
            </div>

            <div class="flex flex-col gap-6">
                <!-- Details -->
                <section v-if="can.update" class="rounded-xl border bg-card">
                    <header class="border-b px-5 py-4">
                        <h2 class="font-semibold">Details</h2>
                    </header>
                    <form class="grid gap-4 p-5" @submit.prevent="saveDetails">
                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="details.name" required />
                            <InputError :message="details.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                v-model="details.email"
                                type="email"
                                required
                            />
                            <InputError :message="details.errors.email" />
                        </div>
                        <Button
                            type="submit"
                            size="sm"
                            class="justify-self-end"
                            :disabled="details.processing || !details.isDirty"
                        >
                            Save details
                        </Button>
                    </form>
                </section>

                <!-- Activity -->
                <section class="rounded-xl border bg-card">
                    <header class="flex items-center gap-2 border-b px-5 py-4">
                        <History class="size-4 text-primary" />
                        <h2 class="font-semibold">History</h2>
                    </header>
                    <ol v-if="activity.length" class="flex flex-col gap-4 p-5">
                        <li
                            v-for="entry in activity"
                            :key="entry.id"
                            class="relative border-l pl-4"
                        >
                            <span
                                class="absolute top-1.5 -left-1 size-2 rounded-full bg-primary"
                            />
                            <p class="text-sm">{{ entry.description }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ entry.causer ?? 'System' }} ·
                                {{ formatRelative(entry.createdAt) }}
                            </p>
                        </li>
                    </ol>
                    <p v-else class="p-5 text-sm text-muted-foreground">
                        No changes recorded yet.
                    </p>
                </section>
            </div>
        </div>
    </div>

    <ConfirmDialog
        v-model:open="statusOpen"
        :title="
            user.isActive
                ? `Deactivate ${user.name}?`
                : `Reactivate ${user.name}?`
        "
        :description="
            user.isActive
                ? 'They will be signed out and cannot sign in until reactivated. Their records are kept.'
                : 'They will be able to sign in again with their existing access.'
        "
        :confirm-label="user.isActive ? 'Deactivate' : 'Reactivate'"
        :destructive="user.isActive"
        @confirm="toggleActive"
    />
</template>
