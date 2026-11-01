<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import {
    Camera,
    Check,
    ImageUp,
    ChevronLeft,
    ChevronRight,
    Church,
    ClipboardCheck,
    HeartHandshake,
    Phone,
    Siren,
    Trash2,
    UserRound,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import OptionSelect from '@/components/app/OptionSelect.vue';
import RecordPicker from '@/components/app/RecordPicker.vue';
import SearchableSelect from '@/components/app/SearchableSelect.vue';
import CameraCapture from '@/components/people/CameraCapture.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatPlainDate, optionLabel } from '@/lib/people';
import { cn } from '@/lib/utils';
import { families as lookupFamilies } from '@/routes/admin/lookup';
import { index, show, store, update } from '@/routes/admin/members';
import type {
    CommunityOption,
    GroupedOption,
    MemberFields,
    PeopleOptions,
} from '@/types';

type FamilyChoice = {
    id: number;
    name: string;
    head?: string | null;
    membersCount?: number;
    communityId?: number | null;
};

const props = defineProps<{
    /** The member being edited; omit when registering a new one. */
    member?: { id: number; name: string; photoUrl: string | null };
    initial: MemberFields;
    family: FamilyChoice | null;
    communities: CommunityOption[];
    options: PeopleOptions;
    occupations: GroupedOption[];
}>();

const text = (value: string | null) => value ?? '';
const initial = props.initial;

// Text inputs work with strings; the server turns empty strings back into null.
const form = useForm({
    title: text(initial.title),
    first_name: initial.first_name,
    middle_name: text(initial.middle_name),
    last_name: initial.last_name,
    baptismal_name: text(initial.baptismal_name),
    gender: initial.gender as string | null,
    date_of_birth: text(initial.date_of_birth),
    marital_status: initial.marital_status,
    wedding_date: text(initial.wedding_date),
    phone: text(initial.phone),
    alt_phone: text(initial.alt_phone),
    whatsapp_phone: text(initial.whatsapp_phone),
    email: text(initial.email),
    communication_consent: initial.communication_consent,
    address: text(initial.address),
    city: text(initial.city),
    state: text(initial.state),
    occupation: initial.occupation,
    emergency_contact_name: text(initial.emergency_contact_name),
    emergency_contact_phone: text(initial.emergency_contact_phone),
    emergency_contact_relationship: text(
        initial.emergency_contact_relationship,
    ),
    state_of_origin: text(initial.state_of_origin),
    lga_of_origin: text(initial.lga_of_origin),
    community_id: initial.community_id,
    family_id: initial.family_id,
    family_role: initial.family_role,
    status: initial.status as string | null,
    joined_at: text(initial.joined_at),
    notes: text(initial.notes),
    photo: null as File | null,
    remove_photo: false,
});

const communityOptions = computed(() =>
    props.communities.map((community) => ({
        value: String(community.id),
        label: community.name,
    })),
);

const communityId = computed({
    get: () => (form.community_id === null ? null : String(form.community_id)),
    set: (value) => (form.community_id = value === null ? null : Number(value)),
});

const showWeddingDate = computed(
    () =>
        ['married', 'widowed', 'separated'].includes(
            form.marital_status ?? '',
        ) || form.wedding_date !== '',
);

const selectedFamily = ref<FamilyChoice | null>(props.family);

const onFamilySelected = (family: FamilyChoice | null) => {
    selectedFamily.value = family;

    if (!family) {
        form.family_role = null;

        return;
    }

    form.family_role ??= 'child';

    if (form.community_id === null && family.communityId) {
        form.community_id = family.communityId;
    }
};

// Photo preview
const preview = ref<string | null>(null);
const photoInput = ref<HTMLInputElement | null>(null);
const photoUrl = computed(() =>
    form.remove_photo
        ? null
        : (preview.value ?? props.member?.photoUrl ?? null),
);

const setPhoto = (file: File | null) => {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }

    form.photo = file;
    form.remove_photo = false;
    preview.value = file ? URL.createObjectURL(file) : null;
};

const onPhotoChosen = (event: Event) =>
    setPhoto((event.target as HTMLInputElement).files?.[0] ?? null);

const cameraOpen = ref(false);

const removePhoto = () => {
    form.photo = null;
    form.remove_photo = true;
    preview.value = null;

    if (photoInput.value) {
        photoInput.value.value = '';
    }
};

onBeforeUnmount(() => {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }
});

type Step = {
    key: string;
    title: string;
    description: string;
    icon: Component;
    /** The form fields on the step, so server errors can lead back to it. */
    fields: string[];
};

const allSteps: Step[] = [
    {
        key: 'personal',
        title: 'Personal',
        description: "The member's name as it appears in the parish register.",
        icon: UserRound,
        fields: [
            'photo',
            'title',
            'first_name',
            'middle_name',
            'last_name',
            'baptismal_name',
            'gender',
            'date_of_birth',
        ],
    },
    {
        key: 'life',
        title: 'Life details',
        description: 'Marriage and the kind of work the member does.',
        icon: HeartHandshake,
        fields: ['marital_status', 'wedding_date', 'occupation'],
    },
    {
        key: 'contact',
        title: 'Contact',
        description:
            'How the parish office reaches this member. The email is also used for their portal invitation.',
        icon: Phone,
        fields: [
            'phone',
            'alt_phone',
            'whatsapp_phone',
            'email',
            'communication_consent',
            'address',
            'city',
            'state',
            'state_of_origin',
            'lga_of_origin',
        ],
    },
    {
        key: 'emergency',
        title: 'Emergency contact',
        description:
            'Who to call if something happens to this member, for example during a sick call or a parish outing.',
        icon: Siren,
        fields: [
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_relationship',
        ],
    },
    {
        key: 'parish',
        title: 'In the parish',
        description:
            'Where the member belongs: their community, their family and their standing in the register.',
        icon: Church,
        fields: [
            'family_id',
            'family_role',
            'community_id',
            'status',
            'joined_at',
            'notes',
        ],
    },
    {
        key: 'review',
        title: 'Review',
        description:
            'Check the details before adding the member to the register.',
        icon: ClipboardCheck,
        fields: [],
    },
];

// Editing jumps freely between sections, so the review step is only for new members.
const isEditing = computed(() => props.member !== undefined);
const steps = computed(() =>
    isEditing.value
        ? allSteps.filter((step) => step.key !== 'review')
        : allSteps,
);

const current = ref(0);
const furthest = ref(isEditing.value ? allSteps.length : 0);
const stepPanels = ref<HTMLElement[]>([]);

const isLast = computed(() => current.value === steps.value.length - 1);
const currentStep = computed(() => steps.value[current.value]);

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const hasErrors = (step: Step) =>
    step.fields.some((field) => errors.value[field]);

const canVisit = (index: number) => index <= furthest.value;

const goTo = async (index: number) => {
    current.value = index;
    furthest.value = Math.max(furthest.value, index);

    await nextTick();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

/** Let the browser check required and formatted fields on the open step. */
const currentStepIsValid = () => {
    const panel = stepPanels.value[current.value];
    const invalid = panel?.querySelector<HTMLInputElement>(':invalid');

    invalid?.reportValidity();

    return !invalid;
};

const next = () => {
    if (currentStepIsValid() && !isLast.value) {
        goTo(current.value + 1);
    }
};

const back = () => {
    if (current.value > 0) {
        goTo(current.value - 1);
    }
};

/** After a failed save, open the first step with an error. */
const showFirstError = () => {
    const index = steps.value.findIndex(hasErrors);

    if (index !== -1) {
        goTo(index);
    }
};

const save = () => {
    const options = { preserveScroll: true, onError: showFirstError };

    if (props.member) {
        // Files only travel in POST bodies, so the update is spoofed as PUT.
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            update(props.member.id).url,
            options,
        );
    } else {
        form.post(store().url, options);
    }
};

// Pressing Enter moves a new registration along instead of saving it half done.
const submit = () => {
    if (isEditing.value || isLast.value) {
        save();
    } else {
        next();
    }
};

const occupationLabels = computed(
    () =>
        new Map(
            props.occupations.map((option) => [option.value, option.label]),
        ),
);

const date = (value: string) => (value ? formatPlainDate(value) : null);

/** What the review step shows, one block per step. */
const summary = computed(() => [
    {
        step: 0,
        rows: [
            {
                label: 'Name',
                value: [
                    form.title,
                    form.first_name,
                    form.middle_name,
                    form.last_name,
                ]
                    .filter(Boolean)
                    .join(' '),
            },
            { label: 'Baptismal name', value: form.baptismal_name },
            {
                label: 'Gender',
                value: optionLabel(props.options.genders, form.gender),
            },
            { label: 'Date of birth', value: date(form.date_of_birth) },
        ],
    },
    {
        step: 1,
        rows: [
            {
                label: 'Marital status',
                value: form.marital_status
                    ? optionLabel(
                          props.options.maritalStatuses,
                          form.marital_status,
                      )
                    : null,
            },
            { label: 'Wedding date', value: date(form.wedding_date) },
            {
                label: 'Occupation',
                value: form.occupation
                    ? occupationLabels.value.get(form.occupation)
                    : null,
            },
        ],
    },
    {
        step: 2,
        rows: [
            { label: 'Phone', value: form.phone },
            { label: 'Other phone', value: form.alt_phone },
            { label: 'WhatsApp', value: form.whatsapp_phone },
            { label: 'Email', value: form.email },
            {
                label: 'Parish messages',
                value: form.communication_consent ? 'Agreed' : 'Not agreed',
            },
            {
                label: 'Address',
                value: [form.address, form.city, form.state]
                    .filter(Boolean)
                    .join(', '),
            },
            {
                label: 'Origin',
                value: [form.lga_of_origin, form.state_of_origin]
                    .filter(Boolean)
                    .join(', '),
            },
        ],
    },
    {
        step: 3,
        rows: [
            {
                label: 'Name',
                value: [
                    form.emergency_contact_name,
                    form.emergency_contact_relationship &&
                        `(${form.emergency_contact_relationship})`,
                ]
                    .filter(Boolean)
                    .join(' '),
            },
            { label: 'Phone', value: form.emergency_contact_phone },
        ],
    },
    {
        step: 4,
        rows: [
            {
                label: 'Family',
                value: selectedFamily.value
                    ? `${selectedFamily.value.name}${form.family_role ? ` · ${optionLabel(props.options.familyRoles, form.family_role)}` : ''}`
                    : null,
            },
            {
                label: 'Community',
                value: props.communities.find(
                    (community) => community.id === form.community_id,
                )?.name,
            },
            {
                label: 'Status',
                value: optionLabel(props.options.statuses, form.status),
            },
            { label: 'Joined the parish', value: date(form.joined_at) },
            { label: 'Notes', value: form.notes },
        ],
    },
]);

const displayName = computed(
    () =>
        [form.first_name, form.last_name].filter(Boolean).join(' ') ||
        props.member?.name ||
        'New member',
);
</script>

<template>
    <form class="flex flex-col gap-6" novalidate @submit.prevent="submit">
        <!-- Steps: the full bar on wider screens, a progress line on phones -->
        <nav aria-label="Form steps">
            <ol class="hidden gap-2 md:flex">
                <li
                    v-for="(step, index) in steps"
                    :key="step.key"
                    class="flex-1"
                >
                    <button
                        type="button"
                        :disabled="!canVisit(index)"
                        :aria-current="index === current ? 'step' : undefined"
                        :class="
                            cn(
                                'group flex w-full flex-col gap-2 border-t-2 pt-3 text-left transition-colors disabled:cursor-not-allowed',
                                index === current
                                    ? 'border-primary'
                                    : index < furthest || isEditing
                                      ? 'border-primary/40 hover:border-primary/70'
                                      : 'border-border',
                                hasErrors(step) && 'border-destructive',
                            )
                        "
                        @click="goTo(index)"
                    >
                        <span class="flex items-center gap-2">
                            <span
                                :class="
                                    cn(
                                        'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums',
                                        hasErrors(step)
                                            ? 'bg-destructive text-white'
                                            : index === current
                                              ? 'bg-primary text-primary-foreground'
                                              : index < furthest && !isEditing
                                                ? 'bg-primary/15 text-primary'
                                                : 'bg-muted text-muted-foreground',
                                    )
                                "
                            >
                                <Check
                                    v-if="
                                        !isEditing &&
                                        index < furthest &&
                                        index !== current &&
                                        !hasErrors(step)
                                    "
                                    class="size-3.5"
                                />
                                <template v-else>{{ index + 1 }}</template>
                            </span>
                            <span
                                :class="
                                    cn(
                                        'truncate text-sm font-medium',
                                        index !== current &&
                                            'text-muted-foreground group-enabled:group-hover:text-foreground',
                                    )
                                "
                                >{{ step.title }}</span
                            >
                        </span>
                    </button>
                </li>
            </ol>

            <div class="flex flex-col gap-2 md:hidden">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium">{{ currentStep.title }}</span>
                    <span class="text-muted-foreground tabular-nums"
                        >Step {{ current + 1 }} of {{ steps.length }}</span
                    >
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full bg-primary transition-[width] duration-300"
                        :style="{
                            width: `${((current + 1) / steps.length) * 100}%`,
                        }"
                    />
                </div>
            </div>
        </nav>

        <section class="rounded-xl border bg-card">
            <header class="flex items-start gap-3 border-b px-5 py-4">
                <span
                    class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <component :is="currentStep.icon" class="size-4" />
                </span>
                <div class="min-w-0">
                    <h2 class="font-semibold">{{ currentStep.title }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ currentStep.description }}
                    </p>
                </div>
            </header>

            <!-- Personal -->
            <div
                v-show="currentStep.key === 'personal'"
                :ref="(el) => el && (stepPanels[0] = el as HTMLElement)"
                class="grid gap-5 p-5"
            >
                <div class="flex flex-wrap items-center gap-4">
                    <MemberAvatar
                        :name="displayName"
                        :photo-url="photoUrl"
                        class="size-16 text-base"
                    />
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="photoInput?.click()"
                        >
                            <ImageUp class="size-4" />
                            {{ photoUrl ? 'Upload another' : 'Upload photo' }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="cameraOpen = true"
                        >
                            <Camera class="size-4" /> Take photo
                        </Button>
                        <Button
                            v-if="photoUrl"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            @click="removePhoto"
                        >
                            <Trash2 class="size-4" /> Remove
                        </Button>
                        <input
                            ref="photoInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            @change="onPhotoChosen"
                        />
                    </div>
                    <InputError :message="form.errors.photo" />
                </div>

                <div class="grid gap-4 sm:grid-cols-6">
                    <div class="grid gap-2 sm:col-span-1">
                        <Label for="title">Title</Label>
                        <Input
                            id="title"
                            v-model="form.title"
                            placeholder="Mr."
                        />
                        <InputError :message="form.errors.title" />
                    </div>
                    <div class="grid gap-2 sm:col-span-5 md:col-span-2">
                        <Label for="first_name">First name</Label>
                        <Input
                            id="first_name"
                            v-model="form.first_name"
                            required
                        />
                        <InputError :message="form.errors.first_name" />
                    </div>
                    <div class="grid gap-2 sm:col-span-3 md:col-span-3">
                        <Label for="middle_name">Middle name</Label>
                        <Input id="middle_name" v-model="form.middle_name" />
                        <InputError :message="form.errors.middle_name" />
                    </div>
                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="last_name">Last name</Label>
                        <Input
                            id="last_name"
                            v-model="form.last_name"
                            required
                        />
                        <InputError :message="form.errors.last_name" />
                    </div>
                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="baptismal_name">Baptismal name</Label>
                        <Input
                            id="baptismal_name"
                            v-model="form.baptismal_name"
                        />
                        <InputError :message="form.errors.baptismal_name" />
                    </div>
                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="gender">Gender</Label>
                        <OptionSelect
                            id="gender"
                            v-model="form.gender"
                            :options="options.genders"
                        />
                        <InputError :message="form.errors.gender" />
                    </div>
                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="date_of_birth">Date of birth</Label>
                        <Input
                            id="date_of_birth"
                            v-model="form.date_of_birth"
                            type="date"
                        />
                        <InputError :message="form.errors.date_of_birth" />
                    </div>
                </div>
            </div>

            <!-- Life details -->
            <div
                v-show="currentStep.key === 'life'"
                :ref="(el) => el && (stepPanels[1] = el as HTMLElement)"
                class="grid gap-4 p-5 sm:grid-cols-2"
            >
                <div class="grid gap-2">
                    <Label for="marital_status">Marital status</Label>
                    <OptionSelect
                        id="marital_status"
                        v-model="form.marital_status"
                        :options="options.maritalStatuses"
                        none-label="Not recorded"
                    />
                    <InputError :message="form.errors.marital_status" />
                </div>
                <div v-if="showWeddingDate" class="grid gap-2">
                    <Label for="wedding_date">Wedding date</Label>
                    <Input
                        id="wedding_date"
                        v-model="form.wedding_date"
                        type="date"
                    />
                    <InputError :message="form.errors.wedding_date" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="occupation">Occupation</Label>
                    <SearchableSelect
                        id="occupation"
                        v-model="form.occupation"
                        :options="occupations"
                        placeholder="Type to search, e.g. nurse, tailor, accountant"
                    />
                    <InputError :message="form.errors.occupation" />
                </div>
            </div>

            <!-- Contact -->
            <div
                v-show="currentStep.key === 'contact'"
                :ref="(el) => el && (stepPanels[2] = el as HTMLElement)"
                class="grid gap-4 p-5 sm:grid-cols-2"
            >
                <div class="grid gap-2">
                    <Label for="phone">Phone</Label>
                    <Input id="phone" v-model="form.phone" type="tel" />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="alt_phone">Other phone</Label>
                    <Input id="alt_phone" v-model="form.alt_phone" type="tel" />
                    <InputError :message="form.errors.alt_phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="whatsapp_phone">WhatsApp number</Label>
                    <Input
                        id="whatsapp_phone"
                        v-model="form.whatsapp_phone"
                        type="tel"
                    />
                    <InputError :message="form.errors.whatsapp_phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" />
                    <InputError :message="form.errors.email" />
                </div>
                <div
                    class="flex items-start gap-3 rounded-lg border bg-muted/30 p-3 sm:col-span-2"
                >
                    <Checkbox
                        id="communication_consent"
                        :model-value="form.communication_consent"
                        class="mt-0.5"
                        @update:model-value="
                            form.communication_consent = $event === true
                        "
                    />
                    <div class="grid gap-1">
                        <Label for="communication_consent"
                            >Happy to receive parish messages</Label
                        >
                        <p class="text-xs text-muted-foreground">
                            Announcements and reminders by email, SMS or
                            WhatsApp. Leave unticked until the member agrees.
                        </p>
                        <InputError
                            :message="form.errors.communication_consent"
                        />
                    </div>
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="address">Home address</Label>
                    <Input id="address" v-model="form.address" />
                    <InputError :message="form.errors.address" />
                </div>
                <div class="grid gap-2">
                    <Label for="city">City or town</Label>
                    <Input id="city" v-model="form.city" />
                    <InputError :message="form.errors.city" />
                </div>
                <div class="grid gap-2">
                    <Label for="state">State</Label>
                    <Input id="state" v-model="form.state" />
                    <InputError :message="form.errors.state" />
                </div>
                <div class="grid gap-2">
                    <Label for="state_of_origin">State of origin</Label>
                    <Input
                        id="state_of_origin"
                        v-model="form.state_of_origin"
                    />
                    <InputError :message="form.errors.state_of_origin" />
                </div>
                <div class="grid gap-2">
                    <Label for="lga_of_origin">LGA of origin</Label>
                    <Input id="lga_of_origin" v-model="form.lga_of_origin" />
                    <InputError :message="form.errors.lga_of_origin" />
                </div>
            </div>

            <!-- Emergency contact -->
            <div
                v-show="currentStep.key === 'emergency'"
                :ref="(el) => el && (stepPanels[3] = el as HTMLElement)"
                class="grid gap-4 p-5 sm:grid-cols-2"
            >
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="emergency_contact_name">Name</Label>
                    <Input
                        id="emergency_contact_name"
                        v-model="form.emergency_contact_name"
                    />
                    <InputError :message="form.errors.emergency_contact_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="emergency_contact_phone">Phone</Label>
                    <Input
                        id="emergency_contact_phone"
                        v-model="form.emergency_contact_phone"
                        type="tel"
                    />
                    <InputError
                        :message="form.errors.emergency_contact_phone"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="emergency_contact_relationship"
                        >Relationship</Label
                    >
                    <Input
                        id="emergency_contact_relationship"
                        v-model="form.emergency_contact_relationship"
                        placeholder="Sister, son, friend…"
                    />
                    <InputError
                        :message="form.errors.emergency_contact_relationship"
                    />
                </div>
            </div>

            <!-- In the parish -->
            <div
                v-show="currentStep.key === 'parish'"
                :ref="(el) => el && (stepPanels[4] = el as HTMLElement)"
                class="grid gap-4 p-5 sm:grid-cols-2"
            >
                <div class="grid gap-2">
                    <Label for="family_id">Family</Label>
                    <RecordPicker
                        id="family_id"
                        v-model="form.family_id"
                        :url="lookupFamilies().url"
                        :initial="family"
                        placeholder="Search families…"
                        :describe="
                            (item: FamilyChoice) =>
                                [
                                    item.head && `Head: ${item.head}`,
                                    item.membersCount !== undefined &&
                                        `${item.membersCount} members`,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')
                        "
                        @select="onFamilySelected"
                    />
                    <InputError :message="form.errors.family_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="family_role">Role in family</Label>
                    <OptionSelect
                        id="family_role"
                        v-model="form.family_role"
                        :options="options.familyRoles"
                        :class="form.family_id ? undefined : 'opacity-50'"
                        placeholder="Pick a family first"
                    />
                    <InputError :message="form.errors.family_role" />
                </div>
                <div class="grid gap-2">
                    <Label for="community_id">Community</Label>
                    <OptionSelect
                        id="community_id"
                        v-model="communityId"
                        :options="communityOptions"
                        none-label="No community"
                    />
                    <InputError :message="form.errors.community_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="status">Status</Label>
                    <OptionSelect
                        id="status"
                        v-model="form.status"
                        :options="options.statuses"
                    />
                    <InputError :message="form.errors.status" />
                </div>
                <div class="grid gap-2">
                    <Label for="joined_at">Joined the parish</Label>
                    <Input
                        id="joined_at"
                        v-model="form.joined_at"
                        type="date"
                    />
                    <InputError :message="form.errors.joined_at" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="notes">Notes</Label>
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        placeholder="Anything the parish office should know."
                    />
                    <InputError :message="form.errors.notes" />
                </div>
            </div>

            <!-- Review -->
            <div
                v-if="!isEditing"
                v-show="currentStep.key === 'review'"
                :ref="(el) => el && (stepPanels[5] = el as HTMLElement)"
                class="flex flex-col gap-4 p-5"
            >
                <div class="flex items-center gap-4 rounded-lg bg-muted/40 p-4">
                    <MemberAvatar
                        :name="displayName"
                        :photo-url="photoUrl"
                        class="size-14 text-base"
                    />
                    <div class="min-w-0">
                        <p class="truncate text-lg font-semibold">
                            {{ displayName }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            A member number is given when you register them.
                        </p>
                    </div>
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div
                        v-for="block in summary"
                        :key="block.step"
                        class="rounded-lg border"
                    >
                        <div
                            class="flex items-center justify-between border-b px-4 py-2.5"
                        >
                            <h3 class="text-sm font-medium">
                                {{ steps[block.step].title }}
                            </h3>
                            <Button
                                type="button"
                                variant="link"
                                size="sm"
                                class="h-auto p-0"
                                @click="goTo(block.step)"
                                >Edit</Button
                            >
                        </div>
                        <dl class="divide-y text-sm">
                            <div
                                v-for="row in block.rows"
                                :key="row.label"
                                class="grid grid-cols-3 gap-3 px-4 py-2"
                            >
                                <dt class="text-muted-foreground">
                                    {{ row.label }}
                                </dt>
                                <dd class="col-span-2 break-words">
                                    {{ row.value || '—' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </section>

        <div
            class="sticky bottom-0 -mx-4 flex flex-wrap items-center gap-2 border-t bg-background/90 px-4 py-3 backdrop-blur md:-mx-6 md:px-6"
        >
            <Button variant="ghost" as-child>
                <Link :href="member ? show(member.id) : index()">Cancel</Link>
            </Button>
            <div class="ml-auto flex gap-2">
                <Button
                    v-if="current > 0"
                    type="button"
                    variant="outline"
                    @click="back"
                >
                    <ChevronLeft class="size-4" /> Back
                </Button>
                <Button
                    v-if="!isLast"
                    type="button"
                    :variant="isEditing ? 'outline' : 'default'"
                    @click="next"
                >
                    Next <ChevronRight class="size-4" />
                </Button>
                <Button
                    v-if="isEditing || isLast"
                    type="button"
                    :disabled="form.processing"
                    @click="save"
                >
                    {{ member ? 'Save changes' : 'Register member' }}
                </Button>
            </div>
        </div>
        <CameraCapture v-model:open="cameraOpen" @captured="setPhoto" />
    </form>
</template>
