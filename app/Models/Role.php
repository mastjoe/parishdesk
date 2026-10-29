<?php

namespace App\Models;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property string|null $description
 * @property string $color
 * @property int|null $users_count
 * @property int|null $permissions_count
 */
class Role extends SpatieRole
{
    use LogsActivity;

    /**
     * The name of the only built-in role. It always holds every permission.
     */
    public const string SUPER_ADMIN = 'super-admin';

    /**
     * The badge colours an admin may pick for a role.
     *
     * @var list<string>
     */
    public const array COLORS = ['slate', 'blue', 'green', 'purple', 'red', 'amber', 'rose', 'teal'];

    /**
     * Determine whether this is the built-in super admin role.
     */
    public function isSuperAdmin(): bool
    {
        return $this->name === self::SUPER_ADMIN;
    }

    /**
     * Configure which attributes are written to the audit log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'color'])
            ->logOnlyDirty()
            ->useLogName('access');
    }
}
