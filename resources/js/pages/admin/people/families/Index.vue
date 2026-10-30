<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { House, Plus } from '@lucide/vue';
import { ref } from 'vue';
import DataTable from '@/components/app/DataTable.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import FamilyFormDialog from '@/components/people/FamilyFormDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTableQuery } from '@/composables/useTableQuery';
import { index, show } from '@/routes/admin/families';
import type { CommunityOption, Paginated, TableState } from '@/types';

type FamilyRow = {
    id: number;
    name: string;
    address: string | null;
    community: string | null;
    head: string | null;
    membersCount: number;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Families', href: index() }],
    },
});

const props = defineProps<{
    families: Paginated<FamilyRow>;
    table: TableState;
    communities: CommunityOption[];
    can: { create: boolean };
}>();

const { search, filters, toggleSort, sortDirection } = useTableQuery(
    props.table,
);

const creating = ref(false);

const columns: ColumnDef<FamilyRow, unknown>[] = [
    { id: 'name', header: 'Family', meta: { sortKey: 'name' } },
    { id: 'head', header: 'Head', meta: { class: 'hidden md:table-cell' } },
    {
        id: 'community',
        header: 'Community',
        meta: { class: 'hidden lg:table-cell' },
    },
    {
        id: 'members_count',
        header: 'Members',
        meta: { sortKey: 'members_count', class: 'text-right' },
    },
];
</script>

<template>
    <Head title="Families" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Families"
            description="Households in the parish and the members who belong to them."
        >
            <template #actions>
                <Button v-if="can.create" @click="creating = true">
                    <Plus class="size-4" /> New family
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <SearchInput
                v-model="search"
                placeholder="Search family name, address or phone…"
            />
            <Select
                :model-value="filters.community || 'all'"
                @update:model-value="
                    filters.community = $event === 'all' ? '' : String($event)
                "
            >
                <SelectTrigger class="w-48">
                    <SelectValue placeholder="All communities" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All communities</SelectItem>
                    <SelectItem
                        v-for="community in communities"
                        :key="community.id"
                        :value="String(community.id)"
                    >
                        {{ community.name }}
                    </SelectItem>
                    <SelectItem value="none">No community</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <DataTable
            :columns="columns"
            :data="families.data"
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
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <House class="size-4" />
                    </span>
                    <span class="min-w-0">
                        <span
                            class="block truncate font-medium hover:underline"
                            >{{ row.name }}</span
                        >
                        <span
                            class="block truncate text-xs text-muted-foreground"
                            >{{ row.address ?? 'No address' }}</span
                        >
                    </span>
                </Link>
            </template>
            <template #cell-head="{ row }">
                <span class="text-muted-foreground">{{ row.head ?? '—' }}</span>
            </template>
            <template #cell-community="{ row }">
                <span class="text-muted-foreground">{{
                    row.community ?? '—'
                }}</span>
            </template>
            <template #cell-members_count="{ row }">
                <span class="block text-right tabular-nums">{{
                    row.membersCount
                }}</span>
            </template>
            <template #empty>
                <div class="flex flex-col items-center gap-2">
                    <House class="size-6" />
                    No families match these filters.
                </div>
            </template>
        </DataTable>

        <Pagination :paginator="families" />
    </div>

    <FamilyFormDialog v-model:open="creating" :communities="communities" />
</template>
