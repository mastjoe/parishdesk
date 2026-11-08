<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import MarriageApplicationForm from '@/components/marriages/MarriageApplicationForm.vue';
import { edit, index, show } from '@/routes/admin/marriages';
import type { MarriageFields, MarriageRow } from '@/types';

const props = defineProps<{
    application: MarriageRow & { fields: MarriageFields };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Marriages & banns', href: index() },
        {
            title: props.application.applicationNo,
            href: show(props.application.id),
        },
        { title: 'Edit', href: edit(props.application.id) },
    ],
});
</script>

<template>
    <Head :title="`Edit ${application.applicationNo}`" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Edit marriage application"
            :description="`Change the details of ${application.groomName} and ${application.brideName}.`"
        />
        <MarriageApplicationForm :application="application" />
    </div>
</template>
