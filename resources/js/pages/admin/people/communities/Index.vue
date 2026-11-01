<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Church, House, Pencil, Plus, Trash2, UsersRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DataTable from '@/components/app/DataTable.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import { create, destroy, edit, index } from '@/routes/admin/communities';
import { index as membersIndex } from '@/routes/admin/members';
import type { MemberSummary } from '@/types';

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
                <Button v-if="can.manage" as-child>
                    <Link :href="create()">
                        <Plus class="size-4" /> New community
                    </Link>
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
                        <component
                            :is="can.manage ? Link : 'p'"
                            :href="can.manage ? edit(row.id) : undefined"
                            class="block truncate font-medium"
                            :class="can.manage ? 'hover:underline' : ''"
                            >{{ row.name }}</component
                        >
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
                            as-child
                        >
                            <Link
                                :href="edit(row.id)"
                                :aria-label="`Edit ${row.name}`"
                            >
                                <Pencil class="size-4" />
                            </Link>
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
