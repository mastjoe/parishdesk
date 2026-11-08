<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import MarriageApplicationForm from '@/components/marriages/MarriageApplicationForm.vue';
import { create, index } from '@/routes/admin/marriages';
import type { MemberSummary } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Marriages & banns', href: index() },
            { title: 'New application', href: create() },
        ],
    },
});

defineProps<{
    member: MemberSummary | null;
}>();
</script>

<template>
    <Head title="New marriage application" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="New marriage application"
            description="Open an application after the couple's first interview. The checklist and the banns follow."
        />
        <MarriageApplicationForm :member="member" />
    </div>
</template>
