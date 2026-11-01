<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import CommunityForm from '@/components/people/CommunityForm.vue';
import { create, index } from '@/routes/admin/communities';
import type { PeopleOptions } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Communities', href: index() },
            { title: 'New community', href: create() },
        ],
    },
});

defineProps<{
    options: PeopleOptions;
}>();
</script>

<template>
    <Head title="New community" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="New community"
            description="A Small Christian Community, station or outstation that members and families can belong to."
        />
        <CommunityForm :options="options" />
    </div>
</template>
