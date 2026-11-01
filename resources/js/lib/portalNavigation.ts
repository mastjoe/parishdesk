import { usePage } from '@inertiajs/vue3';
import { Home, User, UsersRound } from '@lucide/vue';
import { computed } from 'vue';
import { dashboard } from '@/routes/portal';
import { show as family } from '@/routes/portal/family';
import { show as profile } from '@/routes/portal/profile';
import type { NavItem } from '@/types';

/**
 * The member portal navigation. Later phases add Giving, Events and
 * Requests here; keep it to five items for the mobile tab bar.
 */
export const portalNavigation: (NavItem & { requiresMember?: boolean })[] = [
    { title: 'Home', href: dashboard(), icon: Home },
    {
        title: 'My family',
        href: family(),
        icon: UsersRound,
        requiresMember: true,
    },
    { title: 'My details', href: profile(), icon: User, requiresMember: true },
];

/**
 * The portal items the signed-in user can open. Pages about "my" records
 * only appear once the account is linked to a member.
 */
export function usePortalNavigation() {
    const page = usePage();

    return computed(() =>
        portalNavigation.filter(
            (item) => !item.requiresMember || page.props.auth.hasMember,
        ),
    );
}
