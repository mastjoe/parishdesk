<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * The catalogue of every permission the application can guard.
 *
 * This enum is the single source of truth: `permissions:sync` mirrors it into
 * the database, and policies, routes and the UI only ever check these values.
 */
enum Permission: string
{
    case MembersView = 'members.view';
    case MembersCreate = 'members.create';
    case MembersUpdate = 'members.update';
    case MembersDelete = 'members.delete';
    case MembersImport = 'members.import';
    case MembersExport = 'members.export';
    case MembersInvite = 'members.invite';
    case MembersChangeRequestsApprove = 'members.change-requests.approve';

    case FamiliesView = 'families.view';
    case FamiliesCreate = 'families.create';
    case FamiliesUpdate = 'families.update';
    case FamiliesDelete = 'families.delete';

    case CommunitiesView = 'communities.view';
    case CommunitiesManage = 'communities.manage';

    case ClergyView = 'clergy.view';
    case ClergyManage = 'clergy.manage';

    case UsersView = 'users.view';
    case UsersInvite = 'users.invite';
    case UsersUpdate = 'users.update';
    case UsersRolesAssign = 'users.roles.assign';
    case UsersPermissionsAssign = 'users.permissions.assign';

    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';

    case SettingsParishUpdate = 'settings.parish.update';
    case SettingsAppearanceUpdate = 'settings.appearance.update';
    case SettingsFinanceUpdate = 'settings.finance.update';

    case ActivityView = 'activity.view';

    /**
     * Get the group the permission belongs to.
     */
    public function group(): PermissionGroup
    {
        return PermissionGroup::from(Str::before($this->value, '.'));
    }

    /**
     * Get the human readable label for the permission.
     */
    public function label(): string
    {
        return match ($this) {
            self::MembersView => 'View members',
            self::MembersCreate => 'Add members',
            self::MembersUpdate => 'Edit members',
            self::MembersDelete => 'Remove members',
            self::MembersImport => 'Import members',
            self::MembersExport => 'Export members',
            self::MembersInvite => 'Invite members to the portal',
            self::MembersChangeRequestsApprove => 'Review profile change requests',
            self::FamiliesView => 'View families',
            self::FamiliesCreate => 'Create families',
            self::FamiliesUpdate => 'Edit families and their members',
            self::FamiliesDelete => 'Delete families',
            self::CommunitiesView => 'View communities',
            self::CommunitiesManage => 'Create, edit and delete communities',
            self::ClergyView => 'View clergy history',
            self::ClergyManage => 'Record clergy assignments',
            self::UsersView => 'View users',
            self::UsersInvite => 'Invite users',
            self::UsersUpdate => 'Edit and deactivate users',
            self::UsersRolesAssign => 'Assign roles to users',
            self::UsersPermissionsAssign => 'Grant direct permissions',
            self::RolesView => 'View roles',
            self::RolesCreate => 'Create roles',
            self::RolesUpdate => 'Edit roles and their permissions',
            self::RolesDelete => 'Delete roles',
            self::SettingsParishUpdate => 'Edit parish profile',
            self::SettingsAppearanceUpdate => 'Change theme and branding',
            self::SettingsFinanceUpdate => 'Edit finance defaults',
            self::ActivityView => 'View audit log',
        };
    }

    /**
     * Get a description of what the permission allows.
     */
    public function description(): string
    {
        return match ($this) {
            self::MembersView => 'Browse the member register, profiles and birthdays.',
            self::MembersCreate => 'Register new parishioners.',
            self::MembersUpdate => 'Change member details, photos and status.',
            self::MembersDelete => 'Remove members from the register. Records are kept and can be restored.',
            self::MembersImport => 'Bring in members from a CSV file.',
            self::MembersExport => 'Download the member register as a CSV file.',
            self::MembersInvite => 'Send members a link to set up their portal account.',
            self::MembersChangeRequestsApprove => 'Approve or reject the profile changes members submit from the portal.',
            self::FamiliesView => 'Browse families and who belongs to them.',
            self::FamiliesCreate => 'Create new families.',
            self::FamiliesUpdate => 'Change family details and add or remove family members.',
            self::FamiliesDelete => 'Delete families. Their members stay in the register.',
            self::CommunitiesView => 'See the communities, stations and outstations of the parish.',
            self::CommunitiesManage => 'Create, rename and delete communities and choose their leaders.',
            self::ClergyView => 'See the priests and deacons who have served the parish.',
            self::ClergyManage => 'Add, edit and remove clergy assignments.',
            self::UsersView => 'See the list of staff users and their access.',
            self::UsersInvite => 'Send, resend and revoke staff invitations.',
            self::UsersUpdate => 'Change user details and activate or deactivate accounts.',
            self::UsersRolesAssign => 'Add or remove roles on a user.',
            self::UsersPermissionsAssign => 'Give a user individual permissions on top of their roles.',
            self::RolesView => 'See roles and the permissions they hold.',
            self::RolesCreate => 'Create new roles and duplicate existing ones.',
            self::RolesUpdate => 'Rename roles and change which permissions they hold.',
            self::RolesDelete => 'Delete roles that no longer have users.',
            self::SettingsParishUpdate => 'Change the parish name, contacts and address.',
            self::SettingsAppearanceUpdate => 'Choose the theme colour and parish logo.',
            self::SettingsFinanceUpdate => 'Change currency, fiscal year and bank details.',
            self::ActivityView => 'Browse the audit trail of changes.',
        };
    }

    /**
     * Get the permission catalogue grouped for display.
     *
     * @return list<array{key: string, label: string, description: string, permissions: list<array{name: string, label: string, description: string}>}>
     */
    public static function catalogue(): array
    {
        return array_values(array_map(fn (PermissionGroup $group): array => [
            'key' => $group->value,
            'label' => $group->label(),
            'description' => $group->description(),
            'permissions' => array_map(
                fn (self $permission): array => [
                    'name' => $permission->value,
                    'label' => $permission->label(),
                    'description' => $permission->description(),
                ],
                array_values(array_filter(self::cases(), fn (self $permission): bool => $permission->group() === $group)),
            ),
        ], PermissionGroup::cases()));
    }

    /**
     * Get every permission name.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}
