<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import CommunityForm from '@/components/people/CommunityForm.vue';
import type { CommunityFields } from '@/components/people/CommunityForm.vue';
import { edit, index } from '@/routes/admin/communities';
import type { PeopleOptions } from '@/types';

const props = defineProps<{
    community: CommunityFields;
    options: PeopleOptions;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Communities', href: index() },
        { title: props.community.name, href: edit(props.community.id) },
    ],
});
</script>

<template>
    <Head :title="`Edit ${community.name}`" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`Edit ${community.name}`"
            description="Update the community's details and its leader."
        />
        <CommunityForm :community="community" :options="options" />
    </div>
</template>
