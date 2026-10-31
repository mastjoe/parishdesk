<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import {
    House,
    Lightbulb,
    MapPin,
    Phone,
    UserPlus,
    UsersRound,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import OptionSelect from '@/components/app/OptionSelect.vue';
import RecordPicker from '@/components/app/RecordPicker.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store, update } from '@/routes/admin/families';
import { members as lookupMembers } from '@/routes/admin/lookup';
import type { CommunityOption, MemberSummary, Option } from '@/types';

export type FamilyFields = {
    id: number;
    name: string;
    community_id: number | null;
    address: string | null;
    home_phone: string | null;
    notes: string | null;
    membersCount: number;
};

const props = defineProps<{
    /** The family being edited; omit to create one. */
    family?: FamilyFields;
    communities: CommunityOption[];
    /** Roles a member can hold in the family; needed to add members while creating. */
    familyRoles?: Option[];
}>();

type PickedMember = MemberSummary & { family?: string | null };

const form = useForm({
    name: props.family?.name ?? '',
    community_id: props.family?.community_id
        ? String(props.family.community_id)
        : (null as string | null),
    address: props.family?.address ?? '',
    home_phone: props.family?.home_phone ?? '',
    notes: props.family?.notes ?? '',
    members: [] as { member_id: number; family_role: string | null }[],
});

// Members chosen for a new family, kept for their names and photos.
const picked = ref<PickedMember[]>([]);
const pickerKey = ref(0);

/** Suggest the head first, then a spouse, then children. */
const suggestedRole = () => {
    const roles = form.members.map((member) => member.family_role);

    return !roles.includes('head')
        ? 'head'
        : !roles.includes('spouse')
          ? 'spouse'
          : 'child';
};

const addMember = (member: PickedMember | null) => {
    if (member && !form.members.some((row) => row.member_id === member.id)) {
        picked.value.push(member);
        form.members.push({
            member_id: member.id,
            family_role: suggestedRole(),
        });
    }

    // Clear the search box for the next person.
    pickerKey.value++;
};

const removeMember = (position: number) => {
    picked.value.splice(position, 1);
    form.members.splice(position, 1);
};

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const rowError = (position: number) =>
    errors.value[`members.${position}.member_id`] ??
    errors.value[`members.${position}.family_role`];

const communityOptions = computed(() =>
    props.communities.map((community) => ({
        value: String(community.id),
        label: community.name,
    })),
);

const communityName = computed(
    () =>
        communityOptions.value.find(
            (option) => option.value === form.community_id,
        )?.label,
);

const submit = () => {
    if (props.family) {
        form.submit(update(props.family.id));
    } else {
        form.submit(store());
    }
};
</script>

<template>
    <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="submit">
        <div class="flex flex-col gap-6 lg:col-span-2">
            <section class="rounded-xl border bg-card">
                <header class="border-b px-5 py-4">
                    <h2 class="font-semibold">Household</h2>
                    <p class="text-sm text-muted-foreground">
                        How the family is known in the parish, and the community
                        it belongs to.
                    </p>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="family-name">Family name</Label>
                        <Input
                            id="family-name"
                            v-model="form.name"
                            placeholder="Okafor Family"
                            required
                            autofocus
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="family-community">Community</Label>
                        <OptionSelect
                            id="family-community"
                            v-model="form.community_id"
                            :options="communityOptions"
                            none-label="No community"
                        />
                        <p class="text-xs text-muted-foreground">
                            Members registered into this family are placed in
                            the same community.
                        </p>
                        <InputError :message="form.errors.community_id" />
                    </div>
                </div>
            </section>

            <section class="rounded-xl border bg-card">
                <header class="border-b px-5 py-4">
                    <h2 class="font-semibold">Home</h2>
                    <p class="text-sm text-muted-foreground">
                        Where the family lives, for home visits and house
                        blessings.
                    </p>
                </header>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="family-address">Address</Label>
                        <Input
                            id="family-address"
                            v-model="form.address"
                            placeholder="12 Church Road, Ikeja"
                        />
                        <InputError :message="form.errors.address" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="family-phone">Home phone</Label>
                        <Input
                            id="family-phone"
                            v-model="form.home_phone"
                            type="tel"
                        />
                        <InputError :message="form.errors.home_phone" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="family-notes">Notes</Label>
                        <Textarea
                            id="family-notes"
                            v-model="form.notes"
                            rows="4"
                            placeholder="Anything the parish office should know about this household."
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>
            </section>

            <section
                v-if="!family && familyRoles"
                class="rounded-xl border bg-card"
            >
                <header class="border-b px-5 py-4">
                    <h2 class="font-semibold">Members</h2>
                    <p class="text-sm text-muted-foreground">
                        Add people already in the register and say how each
                        belongs to the family. You can also do this later.
                    </p>
                </header>
                <div class="flex flex-col gap-4 p-5">
                    <div class="grid gap-2">
                        <Label for="family-add-member">Add a member</Label>
                        <RecordPicker
                            id="family-add-member"
                            :key="pickerKey"
                            :url="lookupMembers().url"
                            :clearable="false"
                            placeholder="Search by name, number or phone…"
                            :describe="
                                (item: PickedMember) =>
                                    [
                                        item.memberNo,
                                        item.family && `In ${item.family}`,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')
                            "
                            @select="addMember"
                        />
                        <InputError :message="errors.members" />
                    </div>

                    <ul
                        v-if="picked.length"
                        class="divide-y overflow-hidden rounded-lg border"
                    >
                        <li
                            v-for="(member, position) in picked"
                            :key="member.id"
                            class="flex flex-col gap-3 p-3 sm:flex-row sm:items-center"
                        >
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <MemberAvatar
                                    :name="member.name"
                                    :photo-url="member.photoUrl"
                                    class="size-9"
                                />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ member.name }}
                                    </p>
                                    <p
                                        :class="[
                                            'truncate text-xs',
                                            member.family
                                                ? 'text-amber-600 dark:text-amber-400'
                                                : 'text-muted-foreground',
                                        ]"
                                    >
                                        {{
                                            member.family
                                                ? `Moves here from ${member.family}`
                                                : member.memberNo
                                        }}
                                    </p>
                                    <InputError :message="rowError(position)" />
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <OptionSelect
                                    v-model="form.members[position].family_role"
                                    :options="familyRoles"
                                    :aria-label="`Role of ${member.name}`"
                                    class="w-40"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-muted-foreground"
                                    :aria-label="`Remove ${member.name}`"
                                    @click="removeMember(position)"
                                >
                                    <X class="size-4" />
                                </Button>
                            </div>
                        </li>
                    </ul>
                    <div
                        v-else
                        class="flex flex-col items-center gap-2 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                    >
                        <UserPlus class="size-5" />
                        No members added yet. Search above to add the head of
                        the family first.
                    </div>
                </div>
            </section>
        </div>

        <!-- Live preview -->
        <aside class="flex flex-col gap-4">
            <section
                class="relative isolate overflow-hidden rounded-xl border bg-card p-5"
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
                        class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <House class="size-6" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-semibold">
                            {{ form.name || 'Family name' }}
                        </p>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ communityName ?? 'No community' }}
                        </p>
                    </div>
                </div>
                <ul class="mt-4 flex flex-col gap-2 text-sm">
                    <li class="flex items-center gap-2 text-muted-foreground">
                        <MapPin class="size-4 shrink-0" />
                        <span class="truncate">{{
                            form.address || 'No address yet'
                        }}</span>
                    </li>
                    <li class="flex items-center gap-2 text-muted-foreground">
                        <Phone class="size-4 shrink-0" />
                        <span class="truncate">{{
                            form.home_phone || 'No home phone yet'
                        }}</span>
                    </li>
                    <li class="flex items-center gap-2 text-muted-foreground">
                        <UsersRound class="size-4 shrink-0" />
                        {{ family?.membersCount ?? picked.length }}
                        {{
                            (family?.membersCount ?? picked.length) === 1
                                ? 'member'
                                : 'members'
                        }}
                    </li>
                </ul>
                <div v-if="picked.length" class="mt-4 flex items-center">
                    <MemberAvatar
                        v-for="member in picked.slice(0, 6)"
                        :key="member.id"
                        :name="member.name"
                        :photo-url="member.photoUrl"
                        class="-ml-2 size-8 ring-2 ring-card first:ml-0"
                    />
                    <span
                        v-if="picked.length > 6"
                        class="-ml-2 flex size-8 items-center justify-center rounded-full bg-muted text-xs font-medium ring-2 ring-card"
                        >+{{ picked.length - 6 }}</span
                    >
                </div>
            </section>

            <section
                v-if="!family"
                class="flex gap-3 rounded-xl border border-dashed p-4 text-sm"
            >
                <Lightbulb class="mt-0.5 size-4 shrink-0 text-amber-500" />
                <p class="text-muted-foreground">
                    New to the parish? Create the family first, then register
                    new members straight into it from the family page.
                </p>
            </section>
        </aside>

        <div
            class="sticky bottom-0 -mx-4 flex justify-end gap-2 border-t bg-background/90 px-4 py-3 backdrop-blur md:-mx-6 md:px-6 lg:col-span-3"
        >
            <Button variant="outline" as-child>
                <Link :href="family ? show(family.id) : index()">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ family ? 'Save family' : 'Create family' }}
            </Button>
        </div>
    </form>
</template>
