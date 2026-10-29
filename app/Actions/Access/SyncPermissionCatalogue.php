<?php

namespace App\Actions\Access;

use App\Enums\Permission;
use App\Models\Role;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mirrors the permission enum into the database.
 *
 * Missing permissions are created, removed ones are deleted, and the super
 * admin role is ensured. Roles defined by admins are never changed, except
 * that a deleted permission disappears from them.
 */
class SyncPermissionCatalogue
{
    public function __construct(
        private PermissionRegistrar $registrar,
    ) {}

    /**
     * Sync the catalogue and return what changed.
     *
     * @return array{created: list<string>, deleted: list<string>}
     */
    public function handle(): array
    {
        $guard = config('auth.defaults.guard');
        $expected = Permission::names();
        $existing = PermissionModel::query()->where('guard_name', $guard)->pluck('name')->all();

        $created = array_values(array_diff($expected, $existing));
        $deleted = array_values(array_diff($existing, $expected));

        foreach ($created as $name) {
            PermissionModel::query()->create(['name' => $name, 'guard_name' => $guard]);
        }

        PermissionModel::query()->where('guard_name', $guard)->whereIn('name', $deleted)->get()
            ->each(fn (PermissionModel $permission) => $permission->delete());

        Role::query()->firstOrCreate(
            ['name' => Role::SUPER_ADMIN, 'guard_name' => $guard],
            ['description' => 'Built-in role with full access to everything.', 'color' => 'amber'],
        );

        $this->registrar->forgetCachedPermissions();

        return ['created' => $created, 'deleted' => $deleted];
    }
}
