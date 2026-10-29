<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ChevronDown } from '@lucide/vue';
import DataTable from '@/components/app/DataTable.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTableQuery } from '@/composables/useTableQuery';
import { formatDate, formatRelative } from '@/lib/format';
import { index } from '@/routes/admin/activity';
import type { Paginated, TableState } from '@/types';

type Entry = {
    id: number;
    log: string | null;
    event: string | null;
    description: string;
    subject: string | null;
    causer: string | null;
    changes: {
        attributes?: Record<string, unknown>;
        old?: Record<string, unknown>;
    } | null;
    properties: Record<string, unknown> | null;
    createdAt: string | null;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Audit log', href: index() }],
    },
});

const props = defineProps<{
    entries: Paginated<Entry>;
    table: TableState;
    logs: string[];
}>();

const { search, filters, toggleSort, sortDirection } = useTableQuery(
    props.table,
);

const columns: ColumnDef<Entry, unknown>[] = [
    { id: 'description', header: 'Change' },
    { id: 'causer', header: 'By', meta: { class: 'hidden md:table-cell' } },
    {
        id: 'created_at',
        header: 'When',
        meta: { sortKey: 'created_at', class: 'whitespace-nowrap' },
    },
];

const hasDetails = (entry: Entry) =>
    !!entry.changes?.attributes ||
    (!!entry.properties && Object.keys(entry.properties).length > 0);

const show = (value: unknown) =>
    Array.isArray(value)
        ? value.join(', ') || '—'
        : value === null || value === undefined || value === ''
          ? '—'
          : String(value);
</script>

<template>
    <Head title="Audit log" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Audit log"
            description="Every change to access, settings and records, with who made it and when."
        />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <SearchInput v-model="search" placeholder="Search changes…" />
            <Select
                :model-value="filters.log || 'all'"
                @update:model-value="
                    filters.log = $event === 'all' ? '' : String($event)
                "
            >
                <SelectTrigger class="w-40">
                    <SelectValue placeholder="All areas" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All areas</SelectItem>
                    <SelectItem
                        v-for="log in logs"
                        :key="log"
                        :value="log"
                        class="capitalize"
                    >
                        {{ log }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <DataTable
            :columns="columns"
            :data="entries.data"
            :row-key="(row) => row.id"
            :sort-direction="sortDirection"
            @sort="toggleSort"
        >
            <template #cell-description="{ row }">
                <div class="flex flex-col gap-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <StatusBadge
                            v-if="row.log"
                            tone="primary"
                            class="capitalize"
                        >
                            {{ row.log }}
                        </StatusBadge>
                        <span class="font-medium">{{ row.description }}</span>
                    </div>
                    <span
                        v-if="row.subject"
                        class="text-xs text-muted-foreground"
                        >{{ row.subject }}</span
                    >
                    <details v-if="hasDetails(row)" class="group text-xs">
                        <summary
                            class="inline-flex cursor-pointer list-none items-center gap-1 text-muted-foreground hover:text-foreground"
                        >
                            Details
                            <ChevronDown
                                class="size-3 transition group-open:rotate-180"
                            />
                        </summary>
                        <dl
                            class="mt-2 grid gap-1 rounded-lg bg-muted/50 p-3 font-mono"
                        >
                            <template v-if="row.changes?.attributes">
                                <div
                                    v-for="(value, key) in row.changes
                                        .attributes"
                                    :key="`a-${key}`"
                                    class="flex flex-wrap gap-2"
                                >
                                    <dt class="text-muted-foreground">
                                        {{ key }}:
                                    </dt>
                                    <dd>
                                        <span
                                            v-if="row.changes.old"
                                            class="text-red-600 line-through dark:text-red-400"
                                        >
                                            {{ show(row.changes.old[key]) }}
                                        </span>
                                        <span
                                            class="text-emerald-700 dark:text-emerald-400"
                                            >{{ show(value) }}</span
                                        >
                                    </dd>
                                </div>
                            </template>
                            <template v-if="row.properties">
                                <div
                                    v-for="(value, key) in row.properties"
                                    :key="`p-${key}`"
                                    class="flex flex-wrap gap-2"
                                >
                                    <dt class="text-muted-foreground">
                                        {{ key }}:
                                    </dt>
                                    <dd>
                                        {{
                                            typeof value === 'object' &&
                                            value &&
                                            !Array.isArray(value)
                                                ? JSON.stringify(value)
                                                : show(value)
                                        }}
                                    </dd>
                                </div>
                            </template>
                        </dl>
                    </details>
                </div>
            </template>
            <template #cell-causer="{ row }">
                <span class="text-muted-foreground">{{
                    row.causer ?? 'System'
                }}</span>
            </template>
            <template #cell-created_at="{ row }">
                <span
                    :title="formatDate(row.createdAt)"
                    class="text-muted-foreground"
                >
                    {{ formatRelative(row.createdAt) }}
                </span>
            </template>
        </DataTable>

        <Pagination :paginator="entries" />
    </div>
</template>
