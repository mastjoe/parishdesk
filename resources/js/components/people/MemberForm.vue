<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Camera, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import OptionSelect from '@/components/app/OptionSelect.vue';
import RecordPicker from '@/components/app/RecordPicker.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { families as lookupFamilies } from '@/routes/admin/lookup';
import { index, show, store, update } from '@/routes/admin/members';
import type { CommunityOption, MemberFields, PeopleOptions } from '@/types';

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
    email: text(initial.email),
    address: text(initial.address),
    occupation: text(initial.occupation),
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

const onFamilySelected = (family: FamilyChoice | null) => {
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

const onPhotoChosen = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }

    form.photo = file;
    form.remove_photo = false;
    preview.value = file ? URL.createObjectURL(file) : null;
};

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

const submit = () => {
    if (props.member) {
        // Files only travel in POST bodies, so the update is spoofed as PUT.
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            update(props.member.id).url,
            { preserveScroll: true },
        );
    } else {
        form.post(store().url, { preserveScroll: true });
    }
};

const displayName = computed(
    () =>
        [form.first_name, form.last_name].filter(Boolean).join(' ') ||
        props.member?.name ||
        'New member',
);
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <!-- Personal -->
        <section class="grid gap-x-8 gap-y-4 lg:grid-cols-3">
            <div class="space-y-1">
                <h2 class="font-semibold">Personal details</h2>
                <p class="text-sm text-muted-foreground">
                    The member's name as it appears in the parish register.
                </p>
            </div>
            <div class="grid gap-5 rounded-xl border bg-card p-5 lg:col-span-2">
                <div class="flex items-center gap-4">
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
                            <Camera class="size-4" />
                            {{ photoUrl ? 'Change photo' : 'Add photo' }}
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
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="gender">Gender</Label>
                        <OptionSelect
                            id="gender"
                            v-model="form.gender"
                            :options="options.genders"
                        />
                        <InputError :message="form.errors.gender" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="date_of_birth">Date of birth</Label>
                        <Input
                            id="date_of_birth"
                            v-model="form.date_of_birth"
                            type="date"
                        />
                        <InputError :message="form.errors.date_of_birth" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="marital_status">Marital status</Label>
                        <OptionSelect
                            id="marital_status"
                            v-model="form.marital_status"
                            :options="options.maritalStatuses"
                            none-label="Not recorded"
                        />
                        <InputError :message="form.errors.marital_status" />
                    </div>
                    <div
                        v-if="showWeddingDate"
                        class="grid gap-2 sm:col-span-2"
                    >
                        <Label for="wedding_date">Wedding date</Label>
                        <Input
                            id="wedding_date"
                            v-model="form.wedding_date"
                            type="date"
                        />
                        <InputError :message="form.errors.wedding_date" />
                    </div>
                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="occupation">Occupation</Label>
                        <Input id="occupation" v-model="form.occupation" />
                        <InputError :message="form.errors.occupation" />
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact -->
        <section class="grid gap-x-8 gap-y-4 lg:grid-cols-3">
            <div class="space-y-1">
                <h2 class="font-semibold">Contact</h2>
                <p class="text-sm text-muted-foreground">
                    How the parish office reaches this member. The email is also
                    used for their portal invitation.
                </p>
            </div>
            <div
                class="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2 lg:col-span-2"
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
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="address">Home address</Label>
                    <Input id="address" v-model="form.address" />
                    <InputError :message="form.errors.address" />
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
        </section>

        <!-- Parish -->
        <section class="grid gap-x-8 gap-y-4 lg:grid-cols-3">
            <div class="space-y-1">
                <h2 class="font-semibold">In the parish</h2>
                <p class="text-sm text-muted-foreground">
                    Where the member belongs: their community, their family and
                    their standing in the register.
                </p>
            </div>
            <div
                class="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2 lg:col-span-2"
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
        </section>

        <div
            class="sticky bottom-0 -mx-4 flex justify-end gap-2 border-t bg-background/90 px-4 py-3 backdrop-blur md:-mx-6 md:px-6"
        >
            <Button variant="outline" as-child>
                <Link :href="member ? show(member.id) : index()">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ member ? 'Save changes' : 'Register member' }}
            </Button>
        </div>
    </form>
</template>
