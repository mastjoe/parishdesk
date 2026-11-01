<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import MemberForm from '@/components/people/MemberForm.vue';
import { edit, index, show } from '@/routes/admin/members';
import type {
    CommunityOption,
    GroupedOption,
    MemberFields,
    PeopleOptions,
} from '@/types';

const props = defineProps<{
    member: MemberFields & {
        id: number;
        memberNo: string;
        name: string;
        photoUrl: string | null;
    };
    family: { id: number; name: string } | null;
    communities: CommunityOption[];
    options: PeopleOptions;
    occupations: GroupedOption[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Members', href: index() },
        { title: props.member.name, href: show(props.member.id) },
        { title: 'Edit', href: edit(props.member.id) },
    ],
});
</script>

<template>
    <Head :title="`Edit ${member.name}`" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`Edit ${member.name}`"
            :description="`Member number ${member.memberNo}`"
        />
        <MemberForm
            :member="{
                id: member.id,
                name: member.name,
                photoUrl: member.photoUrl,
            }"
            :initial="props.member"
            :family="family"
            :communities="communities"
            :options="options"
            :occupations="occupations"
        />
    </div>
</template>
