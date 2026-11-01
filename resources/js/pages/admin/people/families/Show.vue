<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    EllipsisVertical,
    House,
    MapPin,
    Pencil,
    Phone,
    Trash2,
    UserPlus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import OptionSelect from '@/components/app/OptionSelect.vue';
import RecordPicker from '@/components/app/RecordPicker.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { optionLabel } from '@/lib/people';
import { destroy, edit, index, show } from '@/routes/admin/families';
import {
    destroy as removeMember,
    store as addMember,
    update as updateMember,
} from '@/routes/admin/families/members';
import { members as lookupMembers } from '@/routes/admin/lookup';
import {
    create as createMember,
    show as memberShow,
} from '@/routes/admin/members';
import type { MemberSummary, PeopleOptions } from '@/types';

type FamilyMember = MemberSummary & { phone: string | null };

const props = defineProps<{
    family: {
        id: number;
        name: string;
        community: string | null;
        address: string | null;
        home_phone: string | null;
        notes: string | null;
        createdAt: string | null;
    };
    members: FamilyMember[];
    options: PeopleOptions;
    can: {
        update: boolean;
        delete: boolean;
        createMember: boolean;
        viewMembers: boolean;
    };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Families', href: index() },
        { title: props.family.name, href: show(props.family.id) },
    ],
});

/** The family tree, oldest generation first; relatives and dependents come last. */
const lineage = [
    { key: 'grandparents', title: 'Grandparents', roles: ['grandparent'] },
    { key: 'parents', title: 'Parents', roles: ['father', 'mother'] },
    { key: 'children', title: 'Children', roles: ['child'] },
    { key: 'grandchildren', title: 'Grandchildren', roles: ['grandchild'] },
];

const generations = computed(() => {
    const tree = lineage.map((generation) => ({
        ...generation,
        members: props.members.filter((member) =>
            generation.roles.includes(member.familyRole ?? ''),
        ),
        connectsDown: false,
    }));

    // Draw a line down from a generation when a later one has people in it.
    tree.forEach((generation, position) => {
        generation.connectsDown =
            generation.members.length > 0 &&
            tree.slice(position + 1).some((later) => later.members.length);
    });

    const inTree = lineage.flatMap((generation) => generation.roles);

    return [
        ...tree,
        {
            key: 'household',
            title: 'Also in the household',
            roles: [],
            members: props.members.filter(
                (member) => !inTree.includes(member.familyRole ?? ''),
            ),
            connectsDown: false,
        },
    ];
});

// Adding an existing member
const addForm = useForm({
    member_id: null as number | null,
    family_role: 'child' as string | null,
});
const pickerKey = ref(0);
const add = () =>
    addForm.submit(addMember(props.family.id), {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            pickerKey.value++;
        },
    });

const setRole = (member: FamilyMember, role: string) =>
    router.patch(
        updateMember([props.family.id, member.id]).url,
        { family_role: role },
        { preserveScroll: true },
    );

const leaving = ref<FamilyMember | null>(null);
const remove = () => {
    if (!leaving.value) {
        return;
    }

    router.delete(removeMember([props.family.id, leaving.value.id]).url, {
        preserveScroll: true,
        onFinish: () => (leaving.value = null),
    });
};

const deleteOpen = ref(false);
const deleteFamily = () => router.delete(destroy(props.family.id).url);
</script>

<template>
    <Head :title="family.name" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <section
            class="flex flex-col gap-4 rounded-xl border bg-card p-5 sm:flex-row sm:items-center"
        >
            <span
                class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
            >
                <House class="size-6" />
            </span>
            <div class="min-w-0 flex-1 space-y-1">
                <h1 class="truncate text-2xl font-semibold tracking-tight">
                    {{ family.name }}
                </h1>
                <p
                    class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground"
                >
                    <span>{{ members.length }} members</span>
                    <span v-if="family.community">{{ family.community }}</span>
                    <span
                        v-if="family.address"
                        class="inline-flex items-center gap-1"
                        ><MapPin class="size-3.5" /> {{ family.address }}</span
                    >
                    <span
                        v-if="family.home_phone"
                        class="inline-flex items-center gap-1"
                        ><Phone class="size-3.5" />
                        {{ family.home_phone }}</span
                    >
                </p>
            </div>
            <div class="flex gap-2">
                <Button v-if="can.update" variant="outline" size="sm" as-child>
                    <Link :href="edit(family.id)">
                        <Pencil class="size-4" /> Edit
                    </Link>
                </Button>
                <Button
                    v-if="can.delete"
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:text-destructive"
                    @click="deleteOpen = true"
                >
                    <Trash2 class="size-4" /> Delete
                </Button>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            <!-- Family tree -->
            <section class="flex flex-col gap-6 xl:col-span-2">
                <EmptyState
                    v-if="members.length === 0"
                    :icon="UserPlus"
                    title="No members yet"
                    description="Add people already in the register, or register someone new straight into this family."
                />
                <template
                    v-for="generation in generations"
                    :key="generation.key"
                >
                    <div
                        v-if="generation.members.length"
                        class="flex flex-col gap-3"
                    >
                        <h2
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ generation.title }}
                        </h2>
                        <div
                            class="relative grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <article
                                v-for="member in generation.members"
                                :key="member.id"
                                class="group relative flex items-center gap-3 rounded-xl border bg-card p-3"
                            >
                                <MemberAvatar
                                    :name="member.name"
                                    :photo-url="member.photoUrl"
                                    class="size-11"
                                />
                                <div class="min-w-0 flex-1">
                                    <component
                                        :is="can.viewMembers ? Link : 'span'"
                                        :href="
                                            can.viewMembers
                                                ? memberShow(member.id)
                                                : undefined
                                        "
                                        class="block truncate text-sm font-medium hover:underline"
                                        >{{ member.name }}</component
                                    >
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{
                                            optionLabel(
                                                options.familyRoles,
                                                member.familyRole,
                                            )
                                        }}
                                        <template v-if="member.age !== null"
                                            >· {{ member.age }} yrs</template
                                        >
                                    </p>
                                </div>
                                <DropdownMenu v-if="can.update">
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-8"
                                            :aria-label="`Options for ${member.name}`"
                                        >
                                            <EllipsisVertical class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="w-52"
                                    >
                                        <DropdownMenuLabel
                                            >Role in family</DropdownMenuLabel
                                        >
                                        <DropdownMenuRadioGroup
                                            :model-value="
                                                member.familyRole ?? undefined
                                            "
                                            @update:model-value="
                                                setRole(member, String($event))
                                            "
                                        >
                                            <DropdownMenuRadioItem
                                                v-for="role in options.familyRoles"
                                                :key="role.value"
                                                :value="role.value"
                                            >
                                                {{ role.label }}
                                            </DropdownMenuRadioItem>
                                        </DropdownMenuRadioGroup>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            class="text-destructive focus:text-destructive"
                                            @select="leaving = member"
                                        >
                                            Remove from family
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </article>
                        </div>
                        <div
                            v-if="generation.connectsDown"
                            class="mx-auto h-5 w-px bg-border"
                        />
                    </div>
                </template>
                <div
                    v-if="family.notes"
                    class="rounded-xl border bg-card p-5 text-sm"
                >
                    <p class="mb-1 text-xs text-muted-foreground">Notes</p>
                    <p class="whitespace-pre-line">{{ family.notes }}</p>
                </div>
            </section>

            <!-- Add members -->
            <aside v-if="can.update" class="flex flex-col gap-4">
                <section class="rounded-xl border bg-card">
                    <header class="border-b px-5 py-4">
                        <h2 class="font-semibold">Add a member</h2>
                        <p class="text-sm text-muted-foreground">
                            Someone already in the register. They move here from
                            any other family.
                        </p>
                    </header>
                    <form class="grid gap-4 p-5" @submit.prevent="add">
                        <div class="grid gap-2">
                            <Label for="add-member">Member</Label>
                            <RecordPicker
                                id="add-member"
                                :key="pickerKey"
                                v-model="addForm.member_id"
                                :url="lookupMembers().url"
                                placeholder="Search by name or number…"
                                :describe="
                                    (
                                        item: MemberSummary & {
                                            family?: string | null;
                                        },
                                    ) =>
                                        [item.memberNo, item.family]
                                            .filter(Boolean)
                                            .join(' · ')
                                "
                            />
                            <InputError :message="addForm.errors.member_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="add-role">Role</Label>
                            <OptionSelect
                                id="add-role"
                                v-model="addForm.family_role"
                                :options="options.familyRoles"
                            />
                            <InputError :message="addForm.errors.family_role" />
                        </div>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="addForm.processing || !addForm.member_id"
                        >
                            Add to family
                        </Button>
                    </form>
                </section>
                <Button v-if="can.createMember" variant="outline" as-child>
                    <Link
                        :href="createMember({ query: { family: family.id } })"
                    >
                        <UserPlus class="size-4" /> Register a new member here
                    </Link>
                </Button>
            </aside>
        </div>
    </div>

    <ConfirmDialog
        :open="leaving !== null"
        :title="`Remove ${leaving?.name} from the family?`"
        description="They stay in the member register without a family."
        confirm-label="Remove"
        destructive
        @update:open="(value) => !value && (leaving = null)"
        @confirm="remove"
    />

    <ConfirmDialog
        v-model:open="deleteOpen"
        :title="`Delete ${family.name}?`"
        description="Its members stay in the register without a family."
        confirm-label="Delete family"
        destructive
        @confirm="deleteFamily"
    />
</template>
