import { Home } from '@lucide/vue';
import { dashboard } from '@/routes/portal';
import type { NavItem } from '@/types';

/**
 * The member portal navigation. Later phases add My family, Giving,
 * Events and Requests here; keep it to five items for the mobile tab bar.
 */
export const portalNavigation: NavItem[] = [
    { title: 'Home', href: dashboard(), icon: Home },
];
