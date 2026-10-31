<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Clock, Pencil, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import OptionSelect from '@/components/app/OptionSelect.vue';
import SearchableSelect from '@/components/app/SearchableSelect.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatRelative } from '@/lib/format';
import { formatPlainDate, memberFieldLabels, optionLabel } from '@/lib/people';
import { destroy, store } from '@/routes/portal/profile/changes';
import type { GroupedOption, MemberRow, Option } from '@/types';

type Values = Record<
    | 'title'
    | 'first_name'
    | 'middle_name'
    | 'last_name'
    | 'baptismal_name'
    | 'date_of_birth'
    | 'marital_status'
    | 'wedding_date'
    | 'phone'
    | 'alt_phone'
    | 'whatsapp_phone'
    | 'email'
    | 'address'
    | 'city'
    | 'state'
    | 'occupation'
    | 'emergency_contact_name'
    | 'emergency_contact_phone'
    | 'emergency_contact_relationship',
    string | null
>;

const props = defineProps<{
    member: MemberRow & {
        values: Values;
        genderLabel: string;
        maritalStatus: string | null;
        familyRoleLabel: string | null;
        joinedAt: string | null;
    };
    pending: {
        id: number;
        changes: Partial<Values>;
        note: string | null;
        createdAt: string | null;
    } | null;
    lastReview: {
        status: 'approved' | 'rejected';
        reviewNote: string | null;
        reviewedAt: string | null;
    } | null;
    maritalStatuses: Option[];
    occupations: GroupedOption[];
}>();

const editing = ref(false);

/** Start from the pending request, so members can refine what they sent. */
const startValues = () => {
    const values = {
        ...props.member.values,
        ...props.pending?.changes,
    };

    return Object.fromEntries(
        Object.entries(values).map(([key, value]) => [
            key,
            ['marital_status', 'occupation'].includes(key)
                ? value
                : (value ?? ''),
        ]),
    ) as Record<keyof Values, string> & { marital_status: string | null };
};

const form = useForm({ ...startValues(), note: props.pending?.note ?? '' });

const startEditing = () => {
    form.defaults({ ...startValues(), note: props.pending?.note ?? '' });
    form.reset();
    form.clearErrors();
    editing.value = true;
};

const submit = () =>
    form.submit(store(), {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });

const withdraw = () => router.delete(destroy().url, { preserveScroll: true });

const display = (field: string, value: string | null | undefined) => {
    if (!value) {
        return '—';
    }

    if (field === 'marital_status') {
        return optionLabel(props.maritalStatuses, value);
    }

    if (field === 'occupation') {
        return optionLabel(props.occupations, value);
    }

    return field.includes('date') ? formatPlainDate(value) : value;
};

const rows = computed(() => [
    { label: 'Baptismal name', value: props.member.values.baptismal_name },
    { label: 'Gender', value: props.member.genderLabel },
    {
        label: 'Date of birth',
        value: display('date_of_birth', props.member.values.date_of_birth),
    },
    { label: 'Marital status', value: props.member.maritalStatus },
    { label: 'Phone', value: props.member.values.phone },
    { label: 'Other phone', value: props.member.values.alt_phone },
    { label: 'WhatsApp', value: props.member.values.whatsapp_phone },
    { label: 'Email', value: props.member.values.email },
    { label: 'Address', value: props.member.values.address },
    { label: 'City or town', value: props.member.values.city },
    { label: 'State', value: props.member.values.state },
    {
        label: 'Emergency contact',
        value: [
            props.member.values.emergency_contact_name,
            props.member.values.emergency_contact_phone,
        ]
            .filter(Boolean)
            .join(' · '),
    },
    {
        label: 'Occupation',
        value: display('occupation', props.member.values.occupation),
    },
    { label: 'Community', value: props.member.community },
    { label: 'Family', value: props.member.family?.name },
    {
        label: 'Joined the parish',
        value: display('joined_at', props.member.joinedAt),
    },
]);

const textFields: { key: keyof Values; type?: string; wide?: boolean }[] = [
    { key: 'title' },
    { key: 'first_name' },
    { key: 'middle_name' },
    { key: 'last_name' },
    { key: 'baptismal_name' },
    { key: 'date_of_birth', type: 'date' },
    { key: 'phone', type: 'tel' },
    { key: 'alt_phone', type: 'tel' },
    { key: 'whatsapp_phone', type: 'tel' },
    { key: 'email', type: 'email' },
    { key: 'address', wide: true },
    { key: 'city' },
    { key: 'state' },
    { key: 'emergency_contact_name', wide: true },
    { key: 'emergency_contact_phone', type: 'tel' },
    { key: 'emergency_contact_relationship' },
    { key: 'wedding_date', type: 'date' },
];
</script>

<template>
    <Head title="My details" />

    <div class="flex flex-col gap-6">
        <section class="flex items-center gap-4 rounded-2xl border bg-card p-5">
            <MemberAvatar
                :name="member.name"
                :photo-url="member.photoUrl"
                class="size-16 text-lg"
            />
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-xl font-semibold tracking-tight">
                    {{ member.title }} {{ member.name }}
                </h1>
                <p class="text-sm text-muted-foreground tabular-nums">
                    Member no. {{ member.memberNo }}
                </p>
            </div>
            <Button
                v-if="!editing"
                variant="outline"
                size="sm"
                @click="startEditing"
            >
                <Pencil class="size-4" /> Update
            </Button>
        </section>

        <div
            v-if="pending && !editing"
            class="flex flex-col gap-3 rounded-2xl border border-primary/30 bg-primary/5 p-4"
        >
            <div class="flex items-start gap-3">
                <Clock class="mt-0.5 size-4 shrink-0 text-primary" />
                <div class="flex-1 text-sm">
                    <p class="font-medium">Waiting for the parish office</p>
                    <p class="text-muted-foreground">
                        You sent these changes
                        {{ formatRelative(pending.createdAt) }}.
                    </p>
                </div>
            </div>
            <ul class="grid gap-1 pl-7 text-sm">
                <li v-for="(value, field) in pending.changes" :key="field">
                    <span class="text-muted-foreground"
                        >{{ memberFieldLabels[field] }}:</span
                    >
                    {{ display(field, value) }}
                </li>
            </ul>
            <div class="flex gap-2 pl-7">
                <Button size="sm" variant="outline" @click="startEditing"
                    >Change request</Button
                >
                <Button size="sm" variant="ghost" @click="withdraw"
                    >Withdraw</Button
                >
            </div>
        </div>

        <div
            v-else-if="lastReview?.status === 'rejected' && !editing"
            class="flex items-start gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/5 p-4 text-sm"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0 text-amber-600" />
            <div>
                <p class="font-medium">
                    Your last request was not accepted
                    <StatusBadge tone="amber" class="ml-1">{{
                        formatRelative(lastReview.reviewedAt)
                    }}</StatusBadge>
                </p>
                <p v-if="lastReview.reviewNote" class="text-muted-foreground">
                    {{ lastReview.reviewNote }}
                </p>
            </div>
        </div>

        <!-- Request changes -->
        <form
            v-if="editing"
            class="flex flex-col gap-5 rounded-2xl border bg-card p-5"
            @submit.prevent="submit"
        >
            <div>
                <h2 class="font-semibold">Update my details</h2>
                <p class="text-sm text-muted-foreground">
                    The parish office checks changes before they reach the
                    register.
                </p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div
                    v-for="field in textFields"
                    :key="field.key"
                    :class="['grid gap-2', field.wide && 'sm:col-span-2']"
                >
                    <Label :for="field.key">{{
                        memberFieldLabels[field.key]
                    }}</Label>
                    <Input
                        :id="field.key"
                        v-model="form[field.key]"
                        :type="field.type ?? 'text'"
                        :required="
                            field.key === 'first_name' ||
                            field.key === 'last_name'
                        "
                    />
                    <InputError :message="form.errors[field.key]" />
                </div>
                <div class="grid gap-2">
                    <Label for="marital_status">Marital status</Label>
                    <OptionSelect
                        id="marital_status"
                        v-model="form.marital_status"
                        :options="maritalStatuses"
                        none-label="Not recorded"
                    />
                    <InputError :message="form.errors.marital_status" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="occupation">Occupation</Label>
                    <SearchableSelect
                        id="occupation"
                        v-model="form.occupation"
                        :options="occupations"
                        placeholder="Type to search, e.g. nurse, tailor"
                    />
                    <InputError :message="form.errors.occupation" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="note">Note for the office (optional)</Label>
                    <Textarea
                        id="note"
                        v-model="form.note"
                        rows="2"
                        placeholder="e.g. I got married in June."
                    />
                    <InputError :message="form.errors.note" />
                </div>
            </div>
            <InputError
                :message="(form.errors as Record<string, string>).changes"
            />
            <div class="flex justify-end gap-2">
                <Button variant="outline" type="button" @click="editing = false"
                    >Cancel</Button
                >
                <Button type="submit" :disabled="form.processing"
                    >Send to parish office</Button
                >
            </div>
        </form>

        <dl
            v-else
            class="grid gap-x-6 gap-y-4 rounded-2xl border bg-card p-5 sm:grid-cols-2"
        >
            <div v-for="row in rows" :key="row.label">
                <dt class="text-xs text-muted-foreground">{{ row.label }}</dt>
                <dd class="mt-0.5 text-sm">{{ row.value || '—' }}</dd>
            </div>
        </dl>
    </div>
</template>
