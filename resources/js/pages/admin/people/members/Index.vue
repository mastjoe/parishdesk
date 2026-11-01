<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Download, Upload, UserPlus, UsersRound } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/app/DataTable.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import MemberAvatar from '@/components/people/MemberAvatar.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTableQuery } from '@/composables/useTableQuery';
import { memberStatusTone, optionLabel } from '@/lib/people';
import { create, exportMethod, index, show } from '@/routes/admin/members';
import { create as importCreate } from '@/routes/admin/members/import';
import type {
    CommunityOption,
    MemberRow,
    Paginated,
    PeopleOptions,
    TableState,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Members', href: index() }],
    },
});

const props = defineProps<{
    members: Paginated<MemberRow>;
    table: TableState;
    communities: CommunityOption[];
    options: PeopleOptions;
    counts: {
        total: number;
        active: number;
        newThisMonth: number;
        withAccount: number;
    };
    can: { create: boolean; import: boolean; export: boolean };
}>();

const { search, filters, toggleSort, sortDirection } = useTableQuery(
    props.table,
);

const columns: ColumnDef<MemberRow, unknown>[] = [
    { id: 'name', header: 'Member', meta: { sortKey: 'last_name' } },
    {
        id: 'contact',
        header: 'Contact',
        meta: { class: 'hidden lg:table-cell' },
    },
    {
        id: 'community',
        header: 'Community',
        meta: { class: 'hidden md:table-cell' },
    },
    {
        id: 'family',
        header: 'Family',
        meta: { class: 'hidden xl:table-cell' },
    },
    { id: 'status', header: 'Status' },
];

const filterSelects = computed(() => [
    {
        key: 'status',
        placeholder: 'Any status',
        width: 'w-36',
        options: props.options.statuses,
    },
    {
        key: 'community',
        placeholder: 'All communities',
        width: 'w-44',
        options: [
            ...props.communities.map((community) => ({
                value: String(community.id),
                label: community.name,
            })),
            { value: 'none', label: 'No community' },
        ],
    },
    {
        key: 'gender',
        placeholder: 'Any gender',
        width: 'w-32',
        options: props.options.genders,
    },
    {
        key: 'age',
        placeholder: 'Any age',
        width: 'w-36',
        options: [
            { value: 'children', label: 'Children (0–12)' },
            { value: 'teens', label: 'Teens (13–17)' },
            { value: 'youth', label: 'Youth (18–35)' },
            { value: 'adults', label: 'Adults (36–64)' },
            { value: 'seniors', label: 'Seniors (65+)' },
        ],
    },
    {
        key: 'family',
        placeholder: 'Any family',
        width: 'w-36',
        options: [
            { value: 'linked', label: 'In a family' },
            { value: 'none', label: 'No family' },
        ],
    },
    {
        key: 'portal',
        placeholder: 'Portal: any',
        width: 'w-36',
        options: [
            { value: 'linked', label: 'Has portal access' },
            { value: 'not_linked', label: 'No portal access' },
        ],
    },
]);

const activeFilterCount = computed(
    () => Object.values(filters).filter(Boolean).length,
);

const clearFilters = () => {
    for (const key of Object.keys(filters)) {
        filters[key] = '';
    }
};

/** The CSV export follows the current search and filters. */
const downloadExport = () => {
    window.location.href = exportMethod().url + window.location.search;
};
</script>

<template>
    <Head title="Members" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Members"
            description="The parish register of parishioners."
        >
            <template #actions>
                <Button v-if="can.import" variant="outline" as-child>
                    <Link :href="importCreate()">
                        <Upload class="size-4" /> Import
                    </Link>
                </Button>
                <Button
                    v-if="can.export"
                    variant="outline"
                    @click="downloadExport"
                >
                    <Download class="size-4" /> Export
                </Button>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <UserPlus class="size-4" /> Add member
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <dl
            class="grid grid-cols-2 divide-x divide-y overflow-hidden rounded-xl border bg-card sm:grid-cols-4 sm:divide-y-0"
        >
            <div
                v-for="stat in [
                    { label: 'In the register', value: counts.total },
                    { label: 'Active', value: counts.active },
                    { label: 'Added this month', value: counts.newThisMonth },
                    { label: 'Using the portal', value: counts.withAccount },
                ]"
                :key="stat.label"
                class="px-5 py-4"
            >
                <dt class="text-xs text-muted-foreground">{{ stat.label }}</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ stat.value }}
                </dd>
            </div>
        </dl>

        <div class="flex flex-col gap-3">
            <SearchInput
                v-model="search"
                placeholder="Search name, member number, phone or email…"
            />
            <div class="flex flex-wrap items-center gap-2">
                <Select
                    v-for="select in filterSelects"
                    :key="select.key"
                    :model-value="filters[select.key] || 'all'"
                    @update:model-value="
                        filters[select.key] =
                            $event === 'all' ? '' : String($event)
                    "
                >
                    <SelectTrigger :class="select.width" size="sm">
                        <SelectValue :placeholder="select.placeholder" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{
                            select.placeholder
                        }}</SelectItem>
                        <SelectItem
                            v-for="option in select.options"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Button
                    v-if="activeFilterCount > 0"
                    variant="ghost"
                    size="sm"
                    @click="clearFilters"
                >
                    Clear filters
                </Button>
            </div>
        </div>

        <DataTable
            :columns="columns"
            :data="members.data"
            :row-key="(row) => row.id"
            :sort-direction="sortDirection"
            @sort="toggleSort"
        >
            <template #cell-name="{ row }">
                <Link
                    :href="show(row.id)"
                    class="flex items-center gap-3"
                    prefetch
                >
                    <MemberAvatar :name="row.name" :photo-url="row.photoUrl" />
                    <span class="min-w-0">
                        <span
                            class="block truncate font-medium hover:underline"
                            >{{ row.name }}</span
                        >
                        <span
                            class="block truncate text-xs text-muted-foreground tabular-nums"
                        >
                            {{ row.memberNo }}
                            <template v-if="row.age !== null">
                                · {{ row.age }} yrs</template
                            >
                        </span>
                    </span>
                </Link>
            </template>
            <template #cell-contact="{ row }">
                <span class="block truncate">{{ row.phone ?? '—' }}</span>
                <span class="block truncate text-xs text-muted-foreground">{{
                    row.email
                }}</span>
            </template>
            <template #cell-community="{ row }">
                <span class="text-muted-foreground">{{
                    row.community ?? '—'
                }}</span>
            </template>
            <template #cell-family="{ row }">
                <span v-if="row.family" class="text-muted-foreground">
                    {{ row.family.name }}
                    <span v-if="row.familyRole" class="text-xs"
                        >·
                        {{
                            optionLabel(options.familyRoles, row.familyRole)
                        }}</span
                    >
                </span>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-status="{ row }">
                <div class="flex items-center gap-1.5">
                    <StatusBadge :tone="memberStatusTone[row.status]">
                        {{ optionLabel(options.statuses, row.status) }}
                    </StatusBadge>
                    <StatusBadge v-if="row.hasAccount" tone="primary"
                        >Portal</StatusBadge
                    >
                </div>
            </template>
            <template #empty>
                <div class="flex flex-col items-center gap-2">
                    <UsersRound class="size-6" />
                    {{
                        counts.total === 0
                            ? 'No members yet. Add the first one or import a CSV file.'
                            : 'No members match these filters.'
                    }}
                </div>
            </template>
        </DataTable>

        <Pagination :paginator="members" />
    </div>
</template>
