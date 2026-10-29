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
        return array_map(fn (PermissionGroup $group): array => [
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
        ], PermissionGroup::cases());
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
