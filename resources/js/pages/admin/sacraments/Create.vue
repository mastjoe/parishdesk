<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import SacramentForm from '@/components/sacraments/SacramentForm.vue';
import { create, index } from '@/routes/admin/sacraments';
import type { SacramentOptions, SacramentPerson } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sacraments', href: index() },
            { title: 'Record sacrament', href: create() },
        ],
    },
});

defineProps<{
    type: string;
    person: SacramentPerson | null;
    options: SacramentOptions;
}>();
</script>

<template>
    <Head title="Record sacrament" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Record sacrament"
            description="Write a new entry in one of the sacramental registers."
        />
        <SacramentForm :type="type" :person="person" :options="options" />
    </div>
</template>
