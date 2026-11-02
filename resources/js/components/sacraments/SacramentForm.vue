<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, MapPin, UserPlus, UserRound, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import OptionSelect from '@/components/app/OptionSelect.vue';
import RecordPicker from '@/components/app/RecordPicker.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatPlainDate } from '@/lib/people';
import { sacramentIcon, sacramentTile, sacramentTone } from '@/lib/sacraments';
import { cn } from '@/lib/utils';
import { members as lookupMembers } from '@/routes/admin/lookup';
import { index, show, store, update } from '@/routes/admin/sacraments';
import type {
    MemberSummary,
    SacramentDetail,
    SacramentFields,
    SacramentOptions,
    SacramentPerson,
    SponsorFields,
} from '@/types';

const props = defineProps<{
    /** The entry being corrected; omit to write a new one. */
    record?: SacramentDetail & { fields: SacramentFields };
    /** The sacrament a new entry starts with. */
    type?: string;
    /** Details copied from the member picked for the entry. */
    person: SacramentPerson | null;
    options: SacramentOptions;
}>();

const fields = props.record?.fields;

// Text inputs work with empty strings; the server stores them as null.
const form = useForm({
    type: fields?.type ?? props.type ?? 'baptism',
    member_id: fields?.member_id ?? props.person?.id ?? null,
    person_name: fields?.person_name ?? props.person?.name ?? '',
    gender: fields?.gender ?? props.person?.gender ?? null,
    date_of_birth: fields?.date_of_birth ?? props.person?.dateOfBirth ?? '',
    received_on: fields?.received_on ?? '',
    elsewhere: fields?.elsewhere ?? false,
    place: fields?.place ?? '',
    minister: fields?.minister ?? '',
    father_name: fields?.father_name ?? props.person?.fatherName ?? '',
    mother_name: fields?.mother_name ?? props.person?.motherName ?? '',
    spouse_member_id: fields?.spouse_member_id ?? null,
    spouse_name: fields?.spouse_name ?? '',
    details: {
        confirmation_name: fields?.details.confirmation_name ?? '',
        order: fields?.details.order ?? null,
    },
    register_book: fields?.register_book ?? '',
    register_page: fields?.register_page ?? '',
    entry_no: fields?.entry_no ?? '',
    notes: fields?.notes ?? '',
    sponsors: (fields?.sponsors ?? []) as SponsorFields[],
});

const sacrament = computed(
    () =>
        props.options.types.find((type) => type.value === form.type) ??
        props.options.types[0],
);

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

// The member receiving the sacrament, kept for their photo in the preview.
const member = ref<MemberSummary | null>(
    props.record?.member ?? props.person ?? null,
);

/**
 * Ask the server for what the register can copy from the picked member,
 * such as their date of birth and their parents' names.
 */
const pickMember = (picked: MemberSummary | null) => {
    member.value = picked;

    if (picked) {
        form.person_name = picked.name;
        form.gender = picked.gender;
        router.reload({ data: { member: picked.id }, only: ['person'] });
    }
};

watch(
    () => props.person,
    (person) => {
        if (!person || person.id !== form.member_id) {
            return;
        }

        form.date_of_birth = person.dateOfBirth ?? form.date_of_birth;
        form.father_name = form.father_name || (person.fatherName ?? '');
        form.mother_name = form.mother_name || (person.motherName ?? '');
    },
);

// Sponsors from the member register, kept for their names and photos.
const sponsorMembers = ref<Record<number, MemberSummary>>(
    Object.fromEntries(
        (props.record?.sponsors ?? [])
            .filter((sponsor) => sponsor.member)
            .map((sponsor) => [sponsor.member!.id, sponsor.member!]),
    ),
);
const sponsorPickerKey = ref(0);

const canAddSponsor = computed(
    () => form.sponsors.length < sacrament.value.maxSponsors,
);

/**
 * Suggest godfather or godmother from the sponsor's gender, otherwise the
 * sacrament's only sponsor role.
 */
const suggestedRole = (gender?: string | null) => {
    const roles = sacrament.value.sponsorRoles.map((role) => role.value);

    if (roles.includes('godfather')) {
        return gender === 'female' ? 'godmother' : 'godfather';
    }

    return roles[0];
};

const addSponsorMember = (picked: MemberSummary | null) => {
    if (
        picked &&
        canAddSponsor.value &&
        !form.sponsors.some((sponsor) => sponsor.member_id === picked.id)
    ) {
        sponsorMembers.value[picked.id] = picked;
        form.sponsors.push({
            member_id: picked.id,
            name: picked.name,
            role: suggestedRole(picked.gender),
        });
    }

    // Clear the search box for the next person.
    sponsorPickerKey.value++;
};

const addSponsorByName = () => {
    form.sponsors.push({ member_id: null, name: '', role: suggestedRole() });
};

const sponsorError = (position: number) =>
    errors.value[`sponsors.${position}.name`] ??
    errors.value[`sponsors.${position}.role`] ??
    errors.value[`sponsors.${position}.member_id`];

// Sponsors only make sense for the sacrament they were added under.
watch(
    () => form.type,
    () => {
        const roles = sacrament.value.sponsorRoles.map((role) => role.value);
        form.sponsors = form.sponsors
            .slice(0, sacrament.value.maxSponsors)
            .map((sponsor) =>
                roles.includes(sponsor.role)
                    ? sponsor
                    : { ...sponsor, role: suggestedRole() },
            );
    },
);

const pickSpouse = (picked: MemberSummary | null) => {
    if (picked) {
        form.spouse_name = picked.name;
    }
};

const preview = computed(() => [
    {
        icon: CalendarDays,
        text: form.received_on
            ? formatPlainDate(form.received_on, {
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
              })
            : 'Date not set',
    },
    {
        icon: MapPin,
        text: form.elsewhere ? form.place || 'Another parish' : 'This parish',
    },
    { icon: UserRound, text: form.minister || 'Minister not set' },
]);

const submit = () => {
    if (props.record) {
        form.submit(update(props.record.id));
    } else {
        form.submit(store());
    }
};
</script>

<template>
    <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="submit">
        <div class="flex flex-col gap-6 lg:col-span-2">
            <section v-if="!record" class="rounded-xl border bg-card">
                <header class="border-b px-5 py-4">
                    <h2 class="font-semibold">Sacrament</h2>
                    <p class="text-sm text-muted-foreground">
                        The register this entry is written in.
                    </p>
                </header>
                <div class="grid grid-cols-2 gap-2 p-5 sm:grid-cols-3">
                    <button
                        v-for="type in options.types"
                        :key="type.value"
                        type="button"
                        :class="
                            cn(
                                'flex items-center gap-3 rounded-lg border p-3 text-left text-sm transition hover:border-primary/40',
                                form.type === type.value &&
                                    'border-primary bg-primary/5 ring-1 ring-primary',
                            )
                        "
                        :aria-pressed="form.type === type.value"
                        @click="form.type = type.value"
                    >
                        <span
                            :class="
                                cn(
                                    'flex size-8 shrink-0 items-center justify-center rounded-lg',
                                    sacramentTile[type.value],
                                )
                            "
                        >
                            <component
                                :is="sacramentIcon[type.value]"
                                class="size-4"
                            />
                        </span>
                        <span class="font-medium">{{ type.label }}</span>
                    </button>
                </div>
                <InputError class="px-5 pb-4" :message="form.errors.type" />
            </section>

            <section class="rounded-xl border bg-card">
                <header class="border-b px-5 py-4">
                    <h2 class="font-semibold">
                        {{
                            form.type === 'matrimony'
                                ? 'The couple'
                                : 'Who received it'
                        }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Pick the member to copy their details, or type the name
                        of someone who is not in the member register.
                    </p>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="sacrament-member">
                            {{
                                form.type === 'matrimony'
                                    ? 'Groom or bride in the member register'
                                    : 'Member'
                            }}
                        </Label>
                        <RecordPicker
                            id="sacrament-member"
                            v-model="form.member_id"
                            :url="lookupMembers().url"
                            :initial="member"
                            placeholder="Search members by name or number…"
                            :describe="(item: MemberSummary) => item.memberNo"
                            @select="pickMember"
                        />
                        <InputError :message="form.errors.member_id" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="sacrament-name"
                            >Full name, as in the register</Label
                        >
                        <Input
                            id="sacrament-name"
                            v-model="form.person_name"
                            required
                        />
                        <InputError :message="form.errors.person_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="sacrament-gender">Gender</Label>
                        <OptionSelect
                            id="sacrament-gender"
                            v-model="form.gender"
                            :options="options.genders"
                            none-label="Not recorded"
                        />
                        <InputError :message="form.errors.gender" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="sacrament-dob">Date of birth</Label>
                        <Input
                            id="sacrament-dob"
                            v-model="form.date_of_birth"
                            type="date"
                        />
                        <InputError :message="form.errors.date_of_birth" />
                    </div>

                    <template v-if="form.type === 'matrimony'">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="sacrament-spouse">Spouse</Label>
                            <RecordPicker
                                id="sacrament-spouse"
                                v-model="form.spouse_member_id"
                                :url="lookupMembers().url"
                                :initial="record?.spouse ?? null"
                                placeholder="Search members, or type the name below…"
                                :describe="
                                    (item: MemberSummary) => item.memberNo
                                "
                                @select="pickSpouse"
                            />
                            <InputError
                                :message="form.errors.spouse_member_id"
                            />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="sacrament-spouse-name"
                                >Spouse's full name</Label
                            >
                            <Input
                                id="sacrament-spouse-name"
                                v-model="form.spouse_name"
                                required
                            />
                            <InputError :message="form.errors.spouse_name" />
                        </div>
                    </template>

                    <template v-if="sacrament.recordsParents">
                        <div class="grid gap-2">
                            <Label for="sacrament-father">Father's name</Label>
                            <Input
                                id="sacrament-father"
                                v-model="form.father_name"
                            />
                            <InputError :message="form.errors.father_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="sacrament-mother">Mother's name</Label>
                            <Input
                                id="sacrament-mother"
                                v-model="form.mother_name"
                            />
                            <InputError :message="form.errors.mother_name" />
                        </div>
                    </template>
                </div>
            </section>

            <section class="rounded-xl border bg-card">
                <header class="border-b px-5 py-4">
                    <h2 class="font-semibold">Celebration</h2>
                    <p class="text-sm text-muted-foreground">
                        When, where and by whom the sacrament was celebrated.
                    </p>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="sacrament-date">Date</Label>
                        <Input
                            id="sacrament-date"
                            v-model="form.received_on"
                            type="date"
                            required
                        />
                        <InputError :message="form.errors.received_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="sacrament-minister">Minister</Label>
                        <Input
                            id="sacrament-minister"
                            v-model="form.minister"
                            placeholder="Rev. Fr. …"
                        />
                        <InputError :message="form.errors.minister" />
                    </div>

                    <div v-if="form.type === 'confirmation'" class="grid gap-2">
                        <Label for="sacrament-confirmation-name"
                            >Confirmation name</Label
                        >
                        <Input
                            id="sacrament-confirmation-name"
                            v-model="form.details.confirmation_name"
                            placeholder="e.g. Joseph"
                        />
                        <InputError
                            :message="errors['details.confirmation_name']"
                        />
                    </div>
                    <div v-if="form.type === 'holy_orders'" class="grid gap-2">
                        <Label for="sacrament-order">Ordained to the</Label>
                        <OptionSelect
                            id="sacrament-order"
                            v-model="form.details.order"
                            :options="options.holyOrders"
                        />
                        <InputError :message="errors['details.order']" />
                    </div>

                    <div class="flex items-start gap-3 sm:col-span-2">
                        <Checkbox
                            id="sacrament-elsewhere"
                            :model-value="form.elsewhere"
                            @update:model-value="
                                form.elsewhere = $event === true
                            "
                        />
                        <div class="grid gap-1">
                            <Label for="sacrament-elsewhere"
                                >Celebrated in another parish</Label
                            >
                            <p class="text-xs text-muted-foreground">
                                For sacraments received before joining, so the
                                member's record is complete.
                            </p>
                        </div>
                    </div>
                    <div v-if="form.elsewhere" class="grid gap-2 sm:col-span-2">
                        <Label for="sacrament-place">Church and parish</Label>
                        <Input
                            id="sacrament-place"
                            v-model="form.place"
                            placeholder="St. Mary Catholic Church, Onitsha"
                        />
                        <InputError :message="form.errors.place" />
                    </div>
                </div>
            </section>

            <section
                v-if="sacrament.maxSponsors > 0"
                class="rounded-xl border bg-card"
            >
                <header class="border-b px-5 py-4">
                    <h2 class="font-semibold">
                        {{
                            form.type === 'matrimony'
                                ? 'Witnesses'
                                : form.type === 'baptism'
                                  ? 'Godparents'
                                  : 'Sponsor'
                        }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Up to {{ sacrament.maxSponsors }}. Pick members or add
                        people who are not in the register.
                    </p>
                </header>
                <div class="flex flex-col gap-3 p-5">
                    <ul
                        v-if="form.sponsors.length > 0"
                        class="divide-y rounded-lg border"
                    >
                        <li
                            v-for="(sponsor, position) in form.sponsors"
                            :key="position"
                            class="flex flex-col gap-2 p-3"
                        >
                            <div class="flex items-center gap-3">
                                <template v-if="sponsor.member_id">
                                    <MemberAvatar
                                        :name="sponsor.name"
                                        :photo-url="
                                            sponsorMembers[sponsor.member_id]
                                                ?.photoUrl
                                        "
                                        class="size-8"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">
                                            {{ sponsor.name }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground tabular-nums"
                                        >
                                            {{
                                                sponsorMembers[
                                                    sponsor.member_id
                                                ]?.memberNo ?? 'Member'
                                            }}
                                        </p>
                                    </div>
                                </template>
                                <Input
                                    v-else
                                    v-model="sponsor.name"
                                    class="flex-1"
                                    placeholder="Full name"
                                    :aria-label="`Name of sponsor ${position + 1}`"
                                />
                                <OptionSelect
                                    v-model="sponsor.role"
                                    :options="sacrament.sponsorRoles"
                                    class="w-36"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Remove ${sponsor.name || 'sponsor'}`"
                                    @click="form.sponsors.splice(position, 1)"
                                >
                                    <X class="size-4" />
                                </Button>
                            </div>
                            <InputError :message="sponsorError(position)" />
                        </li>
                    </ul>

                    <div
                        v-if="canAddSponsor"
                        class="flex flex-col gap-2 sm:flex-row"
                    >
                        <div class="flex-1">
                            <RecordPicker
                                :key="sponsorPickerKey"
                                :url="lookupMembers().url"
                                placeholder="Add a member…"
                                :clearable="false"
                                :describe="
                                    (item: MemberSummary) => item.memberNo
                                "
                                @select="addSponsorMember"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            @click="addSponsorByName"
                        >
                            <UserPlus class="size-4" /> Someone else
                        </Button>
                    </div>
                    <InputError :message="form.errors.sponsors" />
                </div>
            </section>

            <section class="rounded-xl border bg-card">
                <header class="border-b px-5 py-4">
                    <h2 class="font-semibold">Register</h2>
                    <p class="text-sm text-muted-foreground">
                        Where the entry is written in the paper register.
                    </p>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="sacrament-book">Book</Label>
                        <Input
                            id="sacrament-book"
                            v-model="form.register_book"
                        />
                        <InputError :message="form.errors.register_book" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="sacrament-page">Page</Label>
                        <Input
                            id="sacrament-page"
                            v-model="form.register_page"
                        />
                        <InputError :message="form.errors.register_page" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="sacrament-entry">Entry number</Label>
                        <Input id="sacrament-entry" v-model="form.entry_no" />
                        <InputError :message="form.errors.entry_no" />
                    </div>
                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="sacrament-notes">Notes</Label>
                        <Textarea
                            id="sacrament-notes"
                            v-model="form.notes"
                            rows="3"
                            placeholder="Marginal notes, later annotations…"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>
            </section>
        </div>

        <!-- Live preview -->
        <aside>
            <section
                class="relative isolate overflow-hidden rounded-xl border bg-card p-5 lg:sticky lg:top-4"
            >
                <div
                    class="pointer-events-none absolute -top-12 -right-12 -z-10 size-36 rounded-full bg-primary/15 blur-2xl"
                />
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Preview
                </p>
                <div class="mt-3 flex items-center gap-3">
                    <span
                        :class="
                            cn(
                                'flex size-12 shrink-0 items-center justify-center rounded-xl',
                                sacramentTile[form.type],
                            )
                        "
                    >
                        <component
                            :is="sacramentIcon[form.type]"
                            class="size-6"
                        />
                    </span>
                    <div class="min-w-0 space-y-1">
                        <p class="truncate font-semibold">
                            {{ form.person_name || 'Name' }}
                        </p>
                        <p
                            v-if="form.type === 'matrimony'"
                            class="truncate text-sm text-muted-foreground"
                        >
                            &amp; {{ form.spouse_name || 'Spouse' }}
                        </p>
                        <StatusBadge :tone="sacramentTone[form.type]">{{
                            sacrament.label
                        }}</StatusBadge>
                    </div>
                </div>
                <ul class="mt-4 flex flex-col gap-2 border-t pt-4 text-sm">
                    <li
                        v-for="item in preview"
                        :key="item.text"
                        class="flex items-center gap-2 text-muted-foreground"
                    >
                        <component :is="item.icon" class="size-4 shrink-0" />
                        <span class="truncate">{{ item.text }}</span>
                    </li>
                </ul>
                <div v-if="form.sponsors.length > 0" class="mt-4 border-t pt-4">
                    <p class="text-xs text-muted-foreground">
                        {{
                            form.type === 'matrimony' ? 'Witnesses' : 'Sponsors'
                        }}
                    </p>
                    <ul class="mt-2 flex flex-col gap-1 text-sm">
                        <li
                            v-for="(sponsor, position) in form.sponsors"
                            :key="position"
                            class="truncate"
                        >
                            {{ sponsor.name || 'Unnamed' }}
                        </li>
                    </ul>
                </div>
                <div
                    v-if="member"
                    class="mt-4 flex items-center gap-3 border-t pt-4"
                >
                    <MemberAvatar
                        :name="member.name"
                        :photo-url="member.photoUrl"
                        class="size-9"
                    />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ member.name }}
                        </p>
                        <p class="text-xs text-muted-foreground tabular-nums">
                            {{ member.memberNo }}
                        </p>
                    </div>
                </div>
            </section>
        </aside>

        <div
            class="sticky bottom-0 -mx-4 flex justify-end gap-2 border-t bg-background/90 px-4 py-3 backdrop-blur md:-mx-6 md:px-6 lg:col-span-3"
        >
            <Button variant="outline" as-child>
                <Link :href="record ? show(record.id) : index()">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ record ? 'Save entry' : 'Record ' + sacrament.label }}
            </Button>
        </div>
    </form>
</template>
