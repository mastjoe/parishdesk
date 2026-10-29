<script setup lang="ts" generic="TData">
import type { ColumnDef } from '@tanstack/vue-table';
import { FlexRender, getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import { ArrowDown, ArrowUp, ChevronsUpDown } from '@lucide/vue';
import { cn } from '@/lib/utils';

/**
 * Column meta understood by the table:
 *  - sortKey: the server-side sort column (enables click-to-sort)
 *  - class:   classes applied to the header and cells of the column
 */
export type DataTableColumnMeta = {
    sortKey?: string;
    class?: string;
};

const props = defineProps<{
    columns: ColumnDef<TData, unknown>[];
    data: TData[];
    sortDirection?: (column: string) => 'asc' | 'desc' | null;
    rowKey?: (row: TData) => string | number;
}>();

const emit = defineEmits<{
    sort: [column: string];
}>();

const table = useVueTable({
    get data() {
        return props.data;
    },
    get columns() {
        return props.columns;
    },
    getCoreRowModel: getCoreRowModel(),
    manualSorting: true,
    manualPagination: true,
    getRowId: (row, index) => String(props.rowKey?.(row) ?? index),
});

const meta = (column: { columnDef: ColumnDef<TData, unknown> }) =>
    (column.columnDef.meta ?? {}) as DataTableColumnMeta;
</script>

<template>
    <div class="overflow-hidden rounded-xl border bg-card">
        <div class="overflow-x-auto">
            <table class="w-full caption-bottom text-sm">
                <thead class="border-b bg-muted/40">
                    <tr
                        v-for="headerGroup in table.getHeaderGroups()"
                        :key="headerGroup.id"
                    >
                        <th
                            v-for="header in headerGroup.headers"
                            :key="header.id"
                            :class="
                                cn(
                                    'h-10 px-4 text-left align-middle text-xs font-medium tracking-wide whitespace-nowrap text-muted-foreground uppercase',
                                    meta(header.column).class,
                                )
                            "
                        >
                            <button
                                v-if="meta(header.column).sortKey"
                                type="button"
                                class="-ml-1 inline-flex items-center gap-1 rounded px-1 py-0.5 uppercase hover:text-foreground"
                                @click="
                                    emit('sort', meta(header.column).sortKey!)
                                "
                            >
                                <FlexRender
                                    :render="header.column.columnDef.header"
                                    :props="header.getContext()"
                                />
                                <ArrowUp
                                    v-if="
                                        sortDirection?.(
                                            meta(header.column).sortKey!,
                                        ) === 'asc'
                                    "
                                    class="size-3.5"
                                />
                                <ArrowDown
                                    v-else-if="
                                        sortDirection?.(
                                            meta(header.column).sortKey!,
                                        ) === 'desc'
                                    "
                                    class="size-3.5"
                                />
                                <ChevronsUpDown
                                    v-else
                                    class="size-3.5 opacity-40"
                                />
                            </button>
                            <FlexRender
                                v-else-if="!header.isPlaceholder"
                                :render="header.column.columnDef.header"
                                :props="header.getContext()"
                            />
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in table.getRowModel().rows"
                        :key="row.id"
                        class="border-b transition-colors last:border-0 hover:bg-muted/30"
                    >
                        <td
                            v-for="cell in row.getVisibleCells()"
                            :key="cell.id"
                            :class="
                                cn(
                                    'px-4 py-3 align-middle',
                                    meta(cell.column).class,
                                )
                            "
                        >
                            <slot
                                :name="`cell-${cell.column.id}`"
                                :row="row.original"
                            >
                                <FlexRender
                                    :render="cell.column.columnDef.cell"
                                    :props="cell.getContext()"
                                />
                            </slot>
                        </td>
                    </tr>
                    <tr v-if="data.length === 0">
                        <td
                            :colspan="columns.length"
                            class="px-4 py-12 text-center text-muted-foreground"
                        >
                            <slot name="empty">No results found.</slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
