import {
    History,
    LayoutGrid,
    Palette,
    Church,
    ShieldCheck,
    Users,
    Wallet,
} from '@lucide/vue';
import { dashboard } from '@/routes/admin';
import { index as activityIndex } from '@/routes/admin/activity';
import { index as rolesIndex } from '@/routes/admin/roles';
import { edit as appearanceEdit } from '@/routes/admin/settings/appearance';
import { edit as financeEdit } from '@/routes/admin/settings/finance';
import { edit as parishEdit } from '@/routes/admin/settings/parish';
import { index as usersIndex } from '@/routes/admin/users';
import type { NavGroup } from '@/types';

/**
 * The admin sidebar. Each item lists the permissions that reveal it; the
 * server enforces the same permissions on the routes themselves.
 */
export const adminNavigation: NavGroup[] = [
    {
        title: 'Overview',
        items: [{ title: 'Dashboard', href: dashboard(), icon: LayoutGrid }],
    },
    {
        title: 'Access control',
        items: [
            {
                title: 'Users',
                href: usersIndex(),
                icon: Users,
                permissions: ['users.view'],
            },
            {
                title: 'Roles',
                href: rolesIndex(),
                icon: ShieldCheck,
                permissions: ['roles.view'],
            },
            {
                title: 'Audit log',
                href: activityIndex(),
                icon: History,
                permissions: ['activity.view'],
            },
        ],
    },
    {
        title: 'Settings',
        items: [
            {
                title: 'Parish profile',
                href: parishEdit(),
                icon: Church,
                permissions: ['settings.parish.update'],
            },
            {
                title: 'Appearance',
                href: appearanceEdit(),
                icon: Palette,
                permissions: ['settings.appearance.update'],
            },
            {
                title: 'Finance',
                href: financeEdit(),
                icon: Wallet,
                permissions: ['settings.finance.update'],
            },
        ],
    },
];
