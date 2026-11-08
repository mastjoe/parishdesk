<?php

namespace App\Enums;

enum PermissionGroup: string
{
    case Members = 'members';
    case Families = 'families';
    case Communities = 'communities';
    case Clergy = 'clergy';
    case Sacraments = 'sacraments';
    case Marriages = 'marriages';
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
            self::Members => 'Members',
            self::Families => 'Families',
            self::Communities => 'Communities',
            self::Clergy => 'Clergy',
            self::Sacraments => 'Sacraments',
            self::Marriages => 'Marriage & Banns',
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
            self::Members => 'The parish register of parishioners, their portal access and change requests.',
            self::Families => 'Households and how members are related to each other.',
            self::Communities => 'Small Christian Communities, stations, outstations and zones.',
            self::Clergy => 'The priests and deacons who serve or have served the parish.',
            self::Sacraments => 'The sacramental registers and their godparents, sponsors and witnesses.',
            self::Marriages => 'Marriage applications, their documents, the banns and any objections raised.',
            self::Users => 'Staff accounts, invitations, and the access given to each user.',
            self::Roles => 'Creating roles and deciding which permissions each role holds.',
            self::Settings => 'Parish profile, branding, theme, and finance defaults.',
            self::Activity => 'The history of changes made across the system.',
        };
    }
}
