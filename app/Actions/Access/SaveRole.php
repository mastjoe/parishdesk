<?php

namespace App\Actions\Access;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\GrantGuard;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a role together with the permissions it holds.
 */
class SaveRole
{
    public function __construct(
        private GrantGuard $guard,
    ) {}

    /**
     * Save the role and record the permission changes in the audit log.
     *
     * @param  array{name: string, description?: string|null, color: string, permissions: list<string>}  $data
     */
    public function handle(User $actor, array $data, ?Role $role = null): Role
    {
        $this->guard->ensureCanGrantPermissions($actor, $data['permissions']);

        return DB::transaction(function () use ($actor, $data, $role): Role {
            $role ??= new Role(['guard_name' => config('auth.defaults.guard')]);
            $before = $role->exists ? $role->permissions->pluck('name')->all() : [];

            $role->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'color' => $data['color'],
            ])->save();

            $role->syncPermissions($data['permissions']);

            $granted = array_values(array_diff($data['permissions'], $before));
            $revoked = array_values(array_diff($before, $data['permissions']));

            if ($granted !== [] || $revoked !== []) {
                activity('access')
                    ->performedOn($role)
                    ->causedBy($actor)
                    ->event('permissions_updated')
                    ->withProperties(['granted' => $granted, 'revoked' => $revoked])
                    ->log("Updated permissions of role {$role->name}");
            }

            return $role;
        });
    }
}
