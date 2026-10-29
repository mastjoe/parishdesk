import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { reactive, ref, watch } from 'vue';
import type { TableState } from '@/types';

/**
 * Keep a list page's search, sort and filters in the query string, matching
 * App\Support\Table\TableQuery on the server.
 */
export function useTableQuery(initial: TableState) {
    const search = ref(initial.search);
    const sort = ref(initial.sort);
    const filters = reactive<Record<string, string>>({ ...initial.filters });

    const visit = () => {
        const params: Record<string, string | Record<string, string>> = {};

        if (search.value) {
            params.search = search.value;
        }

        if (sort.value) {
            params.sort = sort.value;
        }

        const activeFilters = Object.fromEntries(
            Object.entries(filters).filter(([, value]) => value),
        );

        if (Object.keys(activeFilters).length > 0) {
            params.filter = activeFilters;
        }

        router.get(window.location.pathname, params as RequestPayload, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    watch(search, useDebounceFn(visit, 300));
    watch(filters, visit);

    const toggleSort = (column: string) => {
        sort.value =
            sort.value === column
                ? `-${column}`
                : sort.value === `-${column}`
                  ? ''
                  : column;
        visit();
    };

    const sortDirection = (column: string): 'asc' | 'desc' | null => {
        if (sort.value === column) {
            return 'asc';
        }

        return sort.value === `-${column}` ? 'desc' : null;
    };

    return { search, sort, filters, toggleSort, sortDirection };
}
