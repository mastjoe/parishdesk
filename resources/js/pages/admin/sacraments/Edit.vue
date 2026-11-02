<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import SacramentForm from '@/components/sacraments/SacramentForm.vue';
import { edit, index, show } from '@/routes/admin/sacraments';
import type {
    SacramentDetail,
    SacramentFields,
    SacramentOptions,
    SacramentPerson,
} from '@/types';

const props = defineProps<{
    record: SacramentDetail & { fields: SacramentFields };
    person: SacramentPerson | null;
    options: SacramentOptions;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Sacraments', href: index() },
        { title: props.record.personName, href: show(props.record.id) },
        { title: 'Edit', href: edit(props.record.id) },
    ],
});
</script>

<template>
    <Head :title="`Edit ${record.typeLabel.toLowerCase()} entry`" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`Edit ${record.typeLabel.toLowerCase()} entry`"
            :description="`Correct the register entry for ${record.personName}.`"
        />
        <SacramentForm :record="record" :person="person" :options="options" />
    </div>
</template>
