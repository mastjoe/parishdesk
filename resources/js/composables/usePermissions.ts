import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Read the signed-in user's effective permissions.
 *
 * This only decides what the UI shows. Every action is still authorised on
 * the server, so hiding something here is never the protection.
 */
export function usePermissions() {
    const page = usePage();

    const permissions = computed(
        () => new Set(page.props.auth?.permissions ?? []),
    );

    const can = (permission: string): boolean =>
        permissions.value.has(permission);

    const canAny = (list: string[]): boolean => list.some(can);

    const canAll = (list: string[]): boolean => list.every(can);

    return {
        permissions,
        can,
        canAny,
        canAll,
        isSuperAdmin: computed(() => page.props.auth?.isSuperAdmin ?? false),
    };
}
