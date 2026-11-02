<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { BookMarked, Plus } from '@lucide/vue';
import { computed } from 'vue';
import DataTable from '@/components/app/DataTable.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTableQuery } from '@/composables/useTableQuery';
import { formatPlainDate } from '@/lib/people';
import { sacramentIcon, sacramentTile, sacramentTone } from '@/lib/sacraments';
import { cn } from '@/lib/utils';
import { create, index, show } from '@/routes/admin/sacraments';
import type {
    Paginated,
    SacramentOptions,
    SacramentRow,
    TableState,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Sacraments', href: index() }],
    },
});

const props = defineProps<{
    records: Paginated<SacramentRow>;
    table: TableState;
    counts: Record<string, number>;
    years: number[];
    options: SacramentOptions;
    can: { create: boolean };
}>();

const { search, filters, toggleSort, sortDirection } = useTableQuery(
    props.table,
);

const total = computed(() =>
    Object.values(props.counts).reduce((sum, count) => sum + count, 0),
);

const activeType = computed(() =>
    props.options.types.find((type) => type.value === filters.type),
);

const columns: ColumnDef<SacramentRow, unknown>[] = [
    { id: 'person', header: 'Name', meta: { sortKey: 'person_name' } },
    { id: 'date', header: 'Date', meta: { sortKey: 'received_on' } },
    {
        id: 'place',
        header: 'Celebrated',
        meta: { class: 'hidden lg:table-cell' },
    },
    {
        id: 'minister',
        header: 'Minister',
        meta: { class: 'hidden xl:table-cell' },
    },
    {
        id: 'reference',
        header: 'Register',
        meta: { class: 'hidden md:table-cell' },
    },
];

const filterSelects = computed(() => [
    {
        key: 'year',
        placeholder: 'Any year',
        width: 'w-32',
        options: props.years.map((year) => ({
            value: String(year),
            label: String(year),
        })),
    },
    {
        key: 'place',
        placeholder: 'Anywhere',
        width: 'w-44',
        options: [
            { value: 'here', label: 'In this parish' },
            { value: 'elsewhere', label: 'In another parish' },
        ],
    },
]);

const hasFilters = computed(() => Boolean(filters.year || filters.place));

const clearFilters = () => {
    filters.year = '';
    filters.place = '';
};
</script>

<template>
    <Head title="Sacraments" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Sacraments"
            description="The sacramental registers of the parish."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link
                        :href="
                            create({
                                query: activeType
                                    ? { type: activeType.value }
                                    : {},
                            })
                        "
                    >
                        <Plus class="size-4" />
                        {{
                            activeType
                                ? `Record ${activeType.label.toLowerCase()}`
                                : 'Record sacrament'
                        }}
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-7">
            <button
                type="button"
                :class="
                    cn(
                        'flex flex-col items-start gap-2 rounded-xl border bg-card p-4 text-left transition hover:border-primary/40',
                        !filters.type && 'border-primary ring-1 ring-primary',
                    )
                "
                @click="filters.type = ''"
            >
                <span
                    class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <BookMarked class="size-4" />
                </span>
                <span class="text-xs text-muted-foreground">All registers</span>
                <span class="text-xl font-semibold tabular-nums">{{
                    total
                }}</span>
            </button>
            <button
                v-for="type in options.types"
                :key="type.value"
                type="button"
                :class="
                    cn(
                        'flex flex-col items-start gap-2 rounded-xl border bg-card p-4 text-left transition hover:border-primary/40',
                        filters.type === type.value &&
                            'border-primary ring-1 ring-primary',
                    )
                "
                @click="filters.type = type.value"
            >
                <span
                    :class="
                        cn(
                            'flex size-8 items-center justify-center rounded-lg',
                            sacramentTile[type.value],
                        )
                    "
                >
                    <component :is="sacramentIcon[type.value]" class="size-4" />
                </span>
                <span class="truncate text-xs text-muted-foreground">{{
                    type.label
                }}</span>
                <span class="text-xl font-semibold tabular-nums">{{
                    counts[type.value] ?? 0
                }}</span>
            </button>
        </div>

        <div class="flex flex-col gap-3">
            <SearchInput
                v-model="search"
                placeholder="Search name, parents, spouse, minister or entry number…"
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
                    v-if="hasFilters"
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
            :data="records.data"
            :row-key="(row) => row.id"
            :sort-direction="sortDirection"
            @sort="toggleSort"
        >
            <template #cell-person="{ row }">
                <Link
                    :href="show(row.id)"
                    class="flex items-center gap-3"
                    prefetch
                >
                    <span
                        :class="
                            cn(
                                'flex size-9 shrink-0 items-center justify-center rounded-lg',
                                sacramentTile[row.type],
                            )
                        "
                    >
                        <component
                            :is="sacramentIcon[row.type]"
                            class="size-4"
                        />
                    </span>
                    <span class="min-w-0">
                        <span
                            class="block truncate font-medium hover:underline"
                        >
                            {{ row.personName }}
                            <template v-if="row.spouseName">
                                &amp; {{ row.spouseName }}</template
                            >
                        </span>
                        <span class="flex items-center gap-1.5 text-xs">
                            <StatusBadge :tone="sacramentTone[row.type]">{{
                                row.typeLabel
                            }}</StatusBadge>
                            <span
                                v-if="row.member"
                                class="truncate text-muted-foreground tabular-nums"
                                >{{ row.member.memberNo }}</span
                            >
                        </span>
                    </span>
                </Link>
            </template>
            <template #cell-date="{ row }">
                <span class="whitespace-nowrap tabular-nums">{{
                    formatPlainDate(row.receivedOn)
                }}</span>
            </template>
            <template #cell-place="{ row }">
                <span
                    v-if="row.elsewhere"
                    class="block max-w-56 truncate text-muted-foreground"
                    >{{ row.place }}</span
                >
                <span v-else class="text-muted-foreground">This parish</span>
            </template>
            <template #cell-minister="{ row }">
                <span class="text-muted-foreground">{{
                    row.minister ?? '—'
                }}</span>
            </template>
            <template #cell-reference="{ row }">
                <span class="text-xs whitespace-nowrap text-muted-foreground">{{
                    row.reference ?? '—'
                }}</span>
            </template>
            <template #empty>
                <div class="flex flex-col items-center gap-2">
                    <BookMarked class="size-6" />
                    {{
                        total === 0
                            ? 'The registers are empty. Record the first sacrament.'
                            : 'No entries match these filters.'
                    }}
                </div>
            </template>
        </DataTable>

        <Pagination :paginator="records" />
    </div>
</template>
