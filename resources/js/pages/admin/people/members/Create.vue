<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import MemberForm from '@/components/people/MemberForm.vue';
import { create, index } from '@/routes/admin/members';
import type { CommunityOption, PeopleOptions } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Members', href: index() },
            { title: 'Add member', href: create() },
        ],
    },
});

const props = defineProps<{
    communities: CommunityOption[];
    options: PeopleOptions;
    family: { id: number; name: string; communityId: number | null } | null;
}>();

const initial = {
    title: null,
    first_name: '',
    middle_name: null,
    last_name: '',
    baptismal_name: null,
    gender: 'female',
    date_of_birth: null,
    marital_status: null,
    wedding_date: null,
    phone: null,
    alt_phone: null,
    email: null,
    address: null,
    occupation: null,
    state_of_origin: null,
    lga_of_origin: null,
    community_id: props.family?.communityId ?? null,
    family_id: props.family?.id ?? null,
    family_role: props.family ? 'child' : null,
    status: 'active',
    joined_at: new Date().toISOString().slice(0, 10),
    notes: null,
};
</script>

<template>
    <Head title="Add member" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Add member"
            description="A member number is given automatically when you save."
        />
        <MemberForm
            :initial="initial"
            :family="family"
            :communities="communities"
            :options="options"
        />
    </div>
</template>
