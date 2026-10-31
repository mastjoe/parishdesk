<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import FamilyForm from '@/components/people/FamilyForm.vue';
import type { FamilyFields } from '@/components/people/FamilyForm.vue';
import { edit, index, show } from '@/routes/admin/families';
import type { CommunityOption } from '@/types';

const props = defineProps<{
    family: FamilyFields;
    communities: CommunityOption[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Families', href: index() },
        { title: props.family.name, href: show(props.family.id) },
        { title: 'Edit', href: edit(props.family.id) },
    ],
});
</script>

<template>
    <Head :title="`Edit ${family.name}`" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`Edit ${family.name}`"
            description="Update the household's details. Manage its members from the family page."
        />
        <FamilyForm :family="family" :communities="communities" />
    </div>
</template>
