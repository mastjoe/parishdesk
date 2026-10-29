<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RoleForm from '@/components/access/RoleForm.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { index } from '@/routes/admin/roles';
import type { CatalogueGroup } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roles', href: index() }],
    },
});

defineProps<{
    catalogue: CatalogueGroup[];
    colors: string[];
    grantable: string[];
    role: {
        id: number;
        name: string;
        description: string | null;
        color: string;
        permissions: string[];
        usersCount: number;
    };
}>();
</script>

<template>
    <Head :title="`Edit ${role.name}`" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="role.name"
            :description="`${role.usersCount} ${role.usersCount === 1 ? 'user has' : 'users have'} this role. Changes apply to them immediately.`"
        />
        <RoleForm
            :catalogue="catalogue"
            :colors="colors"
            :grantable="grantable"
            :role="role"
        />
    </div>
</template>
