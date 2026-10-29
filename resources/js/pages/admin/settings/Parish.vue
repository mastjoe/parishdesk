<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, update } from '@/routes/admin/settings/parish';

type Settings = Record<
    | 'name'
    | 'patron_saint'
    | 'diocese'
    | 'deanery'
    | 'address'
    | 'city'
    | 'state'
    | 'country'
    | 'phone'
    | 'email'
    | 'website',
    string | null
>;

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Parish profile', href: edit() }],
    },
});

const props = defineProps<{ settings: Settings }>();

const form = useForm(
    Object.fromEntries(
        Object.entries(props.settings).map(([key, value]) => [
            key,
            value ?? '',
        ]),
    ) as {
        [K in keyof typeof props.settings]: NonNullable<
            (typeof props.settings)[K]
        >;
    },
);

const fields: {
    key: keyof Settings;
    label: string;
    type?: string;
    placeholder?: string;
    wide?: boolean;
}[] = [
    {
        key: 'name',
        label: 'Parish name',
        placeholder: 'St. Joseph Catholic Church',
        wide: true,
    },
    { key: 'patron_saint', label: 'Patron saint', placeholder: 'St. Joseph' },
    { key: 'diocese', label: 'Diocese' },
    { key: 'deanery', label: 'Deanery' },
    { key: 'phone', label: 'Phone', type: 'tel' },
    { key: 'email', label: 'Email', type: 'email' },
    { key: 'website', label: 'Website', type: 'url', placeholder: 'https://' },
    { key: 'address', label: 'Street address', wide: true },
    { key: 'city', label: 'City / Town' },
    { key: 'state', label: 'State / Region' },
    { key: 'country', label: 'Country code', placeholder: 'NG' },
];

const submit = () => form.submit(update(), { preserveScroll: true });
</script>

<template>
    <Head title="Parish profile" />

    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <Heading
            variant="small"
            title="Parish profile"
            description="Shown on certificates, receipts, emails and the public site."
        />

        <div class="grid gap-5 rounded-xl border bg-card p-5 sm:grid-cols-2">
            <div
                v-for="field in fields"
                :key="field.key"
                :class="['grid gap-2', field.wide && 'sm:col-span-2']"
            >
                <Label :for="field.key">{{ field.label }}</Label>
                <Input
                    :id="field.key"
                    v-model="form[field.key]"
                    :type="field.type ?? 'text'"
                    :placeholder="field.placeholder"
                    :required="field.key === 'name'"
                    :maxlength="field.key === 'country' ? 2 : undefined"
                />
                <InputError :message="form.errors[field.key]" />
            </div>
        </div>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing"
                >Save profile</Button
            >
        </div>
    </form>
</template>
