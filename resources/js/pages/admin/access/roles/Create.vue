<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RoleForm from '@/components/access/RoleForm.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { create, index } from '@/routes/admin/roles';
import type { CatalogueGroup } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Roles', href: index() },
            { title: 'New role', href: create() },
        ],
    },
});

defineProps<{
    catalogue: CatalogueGroup[];
    colors: string[];
    grantable: string[];
}>();
</script>

<template>
    <Head title="New role" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="New role"
            description="Name the role, then choose the permissions it holds."
        />
        <RoleForm
            :catalogue="catalogue"
            :colors="colors"
            :grantable="grantable"
        />
    </div>
</template>
