<?php

namespace App\Enums;

enum PermissionGroup: string
{
    case Users = 'users';
    case Roles = 'roles';
    case Settings = 'settings';
    case Activity = 'activity';

    /**
     * Get the human readable label for the group.
     */
    public function label(): string
    {
        return match ($this) {
            self::Users => 'Users & Invitations',
            self::Roles => 'Roles',
            self::Settings => 'Parish Settings',
            self::Activity => 'Audit Log',
        };
    }

    /**
     * Get a short description of what the group covers.
     */
    public function description(): string
    {
        return match ($this) {
            self::Users => 'Staff accounts, invitations, and the access given to each user.',
            self::Roles => 'Creating roles and deciding which permissions each role holds.',
            self::Settings => 'Parish profile, branding, theme, and finance defaults.',
            self::Activity => 'The history of changes made across the system.',
        };
    }
}
