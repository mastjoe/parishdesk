<?php

namespace App\Support\Permissions;

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Stops users from handing out more access than they hold themselves.
 */
class GrantGuard
{
    /**
     * Ensure the actor may grant every given role.
     *
     * @param  list<string>  $roleNames
     *
     * @throws ValidationException
     */
    public function ensureCanGrantRoles(User $actor, array $roleNames, string $field = 'roles'): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if (in_array(Role::SUPER_ADMIN, $roleNames, true)) {
            throw ValidationException::withMessages([$field => __('Only a Super Admin can grant the Super Admin role.')]);
        }

        $held = $actor->effectivePermissionNames();

        $roles = Role::query()->with('permissions')->whereIn('name', $roleNames)->get();

        foreach ($roles as $role) {
            $missing = array_diff($role->permissions->pluck('name')->all(), $held);

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    $field => __('You cannot grant the :role role because it includes permissions you do not have.', ['role' => $role->name]),
                ]);
            }
        }
    }

    /**
     * Ensure the actor holds every permission they are trying to grant.
     *
     * @param  list<string>  $permissionNames
     *
     * @throws ValidationException
     */
    public function ensureCanGrantPermissions(User $actor, array $permissionNames, string $field = 'permissions'): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        $missing = array_values(array_diff($permissionNames, $actor->effectivePermissionNames()));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                $field => __('You cannot grant permissions you do not have yourself: :permissions.', ['permissions' => implode(', ', $missing)]),
            ]);
        }
    }
}
