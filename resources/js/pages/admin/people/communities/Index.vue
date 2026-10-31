<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Church, House, Pencil, Plus, Trash2, UsersRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DataTable from '@/components/app/DataTable.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import OptionSelect from '@/components/app/OptionSelect.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import RecordPicker from '@/components/app/RecordPicker.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { destroy, index, store, update } from '@/routes/admin/communities';
import { members as lookupMembers } from '@/routes/admin/lookup';
import { index as membersIndex } from '@/routes/admin/members';
import type { MemberSummary, PeopleOptions } from '@/types';

type CommunityRow = {
    id: number;
    name: string;
    type: string;
    typeLabel: string;
    description: string | null;
    leader: MemberSummary | null;
    membersCount: number;
    familiesCount: number;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Communities', href: index() }],
    },
});

const props = defineProps<{
    communities: CommunityRow[];
    options: PeopleOptions;
    can: { manage: boolean; viewMembers: boolean };
}>();

const columns = computed<ColumnDef<CommunityRow, unknown>[]>(() => [
    { id: 'name', header: 'Community' },
    { id: 'type', header: 'Type', meta: { class: 'hidden sm:table-cell' } },
    { id: 'leader', header: 'Leader', meta: { class: 'hidden md:table-cell' } },
    { id: 'members', header: 'Members', meta: { class: 'w-28' } },
    {
        id: 'families',
        header: 'Families',
        meta: { class: 'hidden w-28 lg:table-cell' },
    },
    ...(props.can.manage
        ? [{ id: 'actions', header: '', meta: { class: 'w-24' } }]
        : []),
]);

// Communities are few and all loaded at once, so search filters them in place.
const search = ref('');
const filteredCommunities = computed(() => {
    const words = search.value.toLowerCase().split(/\s+/).filter(Boolean);

    return props.communities.filter((community) => {
        const haystack = [
            community.name,
            community.typeLabel,
            community.leader?.name,
            community.description,
        ]
            .join(' ')
            .toLowerCase();

        return words.every((word) => haystack.includes(word));
    });
});

const open = ref(false);
const editing = ref<CommunityRow | null>(null);
const form = useForm({
    name: '',
    type: 'scc' as string | null,
    description: '',
    leader_member_id: null as number | null,
});

const openForm = (community: CommunityRow | null) => {
    editing.value = community;
    form.clearErrors();
    form.defaults({
        name: community?.name ?? '',
        type: community?.type ?? 'scc',
        description: community?.description ?? '',
        leader_member_id: community?.leader?.id ?? null,
    });
    form.reset();
    open.value = true;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (editing.value) {
        form.submit(update(editing.value.id), options);
    } else {
        form.submit(store(), options);
    }
};

const deleting = ref<CommunityRow | null>(null);
const remove = () => {
    if (deleting.value) {
        router.delete(destroy(deleting.value.id).url, {
            preserveScroll: true,
            onFinish: () => (deleting.value = null),
        });
    }
};

const typeTone: Record<string, string> = {
    scc: 'primary',
    station: 'blue',
    outstation: 'teal',
    zone: 'slate',
};
</script>

<template>
    <Head title="Communities" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Communities"
            description="Small Christian Communities, stations and outstations of the parish."
        >
            <template #actions>
                <Button v-if="can.manage" @click="openForm(null)">
                    <Plus class="size-4" /> New community
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="communities.length === 0"
            :icon="Church"
            title="No communities yet"
            description="Create the communities members belong to, so you can group and filter the register by them."
        />

        <template v-else>
            <SearchInput
                v-model="search"
                placeholder="Search by name, leader or description…"
            />

            <DataTable
                :columns="columns"
                :data="filteredCommunities"
                :row-key="(row) => row.id"
            >
                <template #cell-name="{ row }">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ row.name }}</p>
                        <p
                            v-if="row.description"
                            class="line-clamp-1 max-w-md text-xs text-muted-foreground"
                        >
                            {{ row.description }}
                        </p>
                    </div>
                </template>
                <template #cell-type="{ row }">
                    <StatusBadge :tone="typeTone[row.type]">{{
                        row.typeLabel
                    }}</StatusBadge>
                </template>
                <template #cell-leader="{ row }">
                    <div v-if="row.leader" class="flex items-center gap-2">
                        <MemberAvatar
                            :name="row.leader.name"
                            :photo-url="row.leader.photoUrl"
                            class="size-7"
                        />
                        <span class="truncate">{{ row.leader.name }}</span>
                    </div>
                    <span v-else class="text-muted-foreground"
                        >None chosen</span
                    >
                </template>
                <template #cell-members="{ row }">
                    <component
                        :is="can.viewMembers ? Link : 'span'"
                        :href="
                            can.viewMembers
                                ? membersIndex({
                                      query: { filter: { community: row.id } },
                                  })
                                : undefined
                        "
                        class="inline-flex items-center gap-1.5 tabular-nums"
                        :class="can.viewMembers ? 'hover:underline' : ''"
                    >
                        <UsersRound class="size-4 text-muted-foreground" />
                        {{ row.membersCount }}
                    </component>
                </template>
                <template #cell-families="{ row }">
                    <span class="inline-flex items-center gap-1.5 tabular-nums">
                        <House class="size-4 text-muted-foreground" />
                        {{ row.familiesCount }}
                    </span>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            :aria-label="`Edit ${row.name}`"
                            @click="openForm(row)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8 text-destructive hover:text-destructive"
                            :aria-label="`Delete ${row.name}`"
                            @click="deleting = row"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </template>
                <template #empty>
                    <div class="flex flex-col items-center gap-2">
                        <Church class="size-6" />
                        No communities match "{{ search }}".
                    </div>
                </template>
            </DataTable>
        </template>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{
                    editing ? 'Edit community' : 'New community'
                }}</DialogTitle>
                <DialogDescription>
                    Members and families can be placed in a community.
                </DialogDescription>
            </DialogHeader>
            <form
                id="community-form"
                class="grid gap-4"
                @submit.prevent="submit"
            >
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="community-name">Name</Label>
                        <Input
                            id="community-name"
                            v-model="form.name"
                            placeholder="St. Jude SCC"
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="community-type">Type</Label>
                        <OptionSelect
                            id="community-type"
                            v-model="form.type"
                            :options="options.communityTypes"
                        />
                        <InputError :message="form.errors.type" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="community-leader">Leader</Label>
                    <RecordPicker
                        id="community-leader"
                        v-model="form.leader_member_id"
                        :url="lookupMembers().url"
                        :initial="editing?.leader ?? null"
                        placeholder="Search members…"
                        :describe="(item: MemberSummary) => item.memberNo"
                    />
                    <InputError :message="form.errors.leader_member_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="community-description">Description</Label>
                    <Textarea
                        id="community-description"
                        v-model="form.description"
                        rows="3"
                        placeholder="Where it meets, which streets it covers…"
                    />
                    <InputError :message="form.errors.description" />
                </div>
            </form>
            <DialogFooter>
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="community-form"
                    :disabled="form.processing"
                >
                    {{ editing ? 'Save community' : 'Create community' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="deleting !== null"
        :title="`Delete ${deleting?.name}?`"
        description="Its members and families stay in the register without a community."
        confirm-label="Delete community"
        destructive
        @update:open="(value) => !value && (deleting = null)"
        @confirm="remove"
    />
</template>
