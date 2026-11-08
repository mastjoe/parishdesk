<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { HeartHandshake, Megaphone, Plus, ShieldAlert } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/app/DataTable.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useTableQuery } from '@/composables/useTableQuery';
import { marriageTone } from '@/lib/marriages';
import { formatPlainDate } from '@/lib/people';
import { cn } from '@/lib/utils';
import { create, index, show } from '@/routes/admin/marriages';
import type { MarriageRow, Option, Paginated, TableState } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Marriages & banns', href: index() }],
    },
});

const props = defineProps<{
    applications: Paginated<MarriageRow>;
    table: TableState;
    counts: Record<string, number>;
    statuses: Option[];
    can: { create: boolean };
}>();

const { search, filters, toggleSort, sortDirection } = useTableQuery(
    props.table,
);

const closed = ['married', 'withdrawn'];

const total = computed(() =>
    Object.values(props.counts).reduce((sum, count) => sum + count, 0),
);

const openCount = computed(() =>
    Object.entries(props.counts)
        .filter(([status]) => !closed.includes(status))
        .reduce((sum, [, count]) => sum + count, 0),
);

const cards = computed(() => [
    { value: '', label: 'All applications', count: total.value },
    { value: 'open', label: 'In progress', count: openCount.value },
    ...props.statuses.map((status) => ({
        value: status.value,
        label: status.label,
        count: props.counts[status.value] ?? 0,
    })),
]);

const columns: ColumnDef<MarriageRow, unknown>[] = [
    { id: 'couple', header: 'Couple', meta: { sortKey: 'groom_name' } },
    { id: 'banns', header: 'Banns', meta: { class: 'hidden md:table-cell' } },
    {
        id: 'proposed',
        header: 'Wedding date',
        meta: { sortKey: 'proposed_date', class: 'hidden lg:table-cell' },
    },
    { id: 'status', header: 'Stage' },
];
</script>

<template>
    <Head title="Marriages & banns" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Marriages & banns"
            description="Couples preparing to marry, from the first interview to the marriage register."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus class="size-4" /> New application
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-8">
            <button
                v-for="card in cards"
                :key="card.value"
                type="button"
                :class="
                    cn(
                        'flex flex-col items-start gap-1 rounded-xl border bg-card p-4 text-left transition hover:border-primary/40',
                        (filters.status ?? '') === card.value &&
                            'border-primary ring-1 ring-primary',
                    )
                "
                @click="filters.status = card.value"
            >
                <span class="truncate text-xs text-muted-foreground">{{
                    card.label
                }}</span>
                <span class="text-xl font-semibold tabular-nums">{{
                    card.count
                }}</span>
            </button>
        </div>

        <SearchInput
            v-model="search"
            placeholder="Search the couple, application number or officiant…"
        />

        <DataTable
            :columns="columns"
            :data="applications.data"
            :row-key="(row) => row.id"
            :sort-direction="sortDirection"
            @sort="toggleSort"
        >
            <template #cell-couple="{ row }">
                <Link
                    :href="show(row.id)"
                    class="flex items-center gap-3"
                    prefetch
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-300"
                    >
                        <HeartHandshake class="size-4" />
                    </span>
                    <span class="min-w-0">
                        <span
                            class="block truncate font-medium hover:underline"
                        >
                            {{ row.groomName }} &amp; {{ row.brideName }}
                        </span>
                        <span
                            class="block text-xs text-muted-foreground tabular-nums"
                            >{{ row.applicationNo }}</span
                        >
                    </span>
                </Link>
            </template>
            <template #cell-banns="{ row }">
                <span
                    class="inline-flex items-center gap-1.5 text-sm text-muted-foreground tabular-nums"
                >
                    <Megaphone class="size-3.5" />
                    {{ row.bannsPublished }} of 3
                    <ShieldAlert
                        v-if="row.hasOpenObjection"
                        class="size-4 text-destructive"
                        aria-label="Objection waiting to be resolved"
                    />
                </span>
            </template>
            <template #cell-proposed="{ row }">
                <span
                    class="whitespace-nowrap text-muted-foreground tabular-nums"
                    >{{
                        row.proposedDate
                            ? formatPlainDate(row.proposedDate)
                            : '—'
                    }}</span
                >
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :tone="marriageTone[row.status]">{{
                    row.statusLabel
                }}</StatusBadge>
            </template>
            <template #empty>
                <div class="flex flex-col items-center gap-2">
                    <HeartHandshake class="size-6" />
                    {{
                        total === 0
                            ? 'No marriage applications yet.'
                            : 'No applications match these filters.'
                    }}
                </div>
            </template>
        </DataTable>

        <Pagination :paginator="applications" />
    </div>
</template>
