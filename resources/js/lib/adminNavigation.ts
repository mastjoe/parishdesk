import {
    Cake,
    Church,
    Cross,
    FileClock,
    History,
    House,
    UsersRound,
    LayoutGrid,
    Palette,
    Landmark,
    ShieldCheck,
    Users,
    Wallet,
} from '@lucide/vue';
import { dashboard } from '@/routes/admin';
import { index as activityIndex } from '@/routes/admin/activity';
import { index as changeRequestsIndex } from '@/routes/admin/change-requests';
import { index as clergyIndex } from '@/routes/admin/clergy';
import { index as communitiesIndex } from '@/routes/admin/communities';
import { index as familiesIndex } from '@/routes/admin/families';
import { celebrations, index as membersIndex } from '@/routes/admin/members';
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
        title: 'People',
        items: [
            {
                title: 'Members',
                href: membersIndex(),
                icon: UsersRound,
                permissions: ['members.view'],
            },
            {
                title: 'Families',
                href: familiesIndex(),
                icon: House,
                permissions: ['families.view'],
            },
            {
                title: 'Communities',
                href: communitiesIndex(),
                icon: Church,
                permissions: ['communities.view', 'communities.manage'],
            },
            {
                title: 'Clergy',
                href: clergyIndex(),
                icon: Cross,
                permissions: ['clergy.view', 'clergy.manage'],
            },
            {
                title: 'Celebrations',
                href: celebrations(),
                icon: Cake,
                permissions: ['members.view'],
            },
            {
                title: 'Change requests',
                href: changeRequestsIndex(),
                icon: FileClock,
                permissions: ['members.change-requests.approve'],
            },
        ],
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
                icon: Landmark,
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
