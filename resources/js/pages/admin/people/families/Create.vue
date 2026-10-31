<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import FamilyForm from '@/components/people/FamilyForm.vue';
import { create, index } from '@/routes/admin/families';
import type { CommunityOption, Option } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Families', href: index() },
            { title: 'New family', href: create() },
        ],
    },
});

defineProps<{
    communities: CommunityOption[];
    familyRoles: Option[];
}>();
</script>

<template>
    <Head title="New family" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="New family"
            description="A household in the parish and the people who belong to it."
        />
        <FamilyForm :communities="communities" :family-roles="familyRoles" />
    </div>
</template>
