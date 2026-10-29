<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    /**
     * Determine whether the user can list roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RolesView);
    }

    /**
     * Determine whether the user can view a role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can(Permission::RolesView);
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::RolesCreate);
    }

    /**
     * Determine whether the user can duplicate a role.
     */
    public function duplicate(User $user, Role $role): bool
    {
        return ! $role->isSuperAdmin() && $user->can(Permission::RolesCreate);
    }

    /**
     * Determine whether the user can edit a role.
     *
     * The super admin role is immutable, and nobody but a super admin may
     * edit a role they hold themselves, so they cannot lock themselves out.
     */
    public function update(User $user, Role $role): bool
    {
        return ! $role->isSuperAdmin()
            && $user->can(Permission::RolesUpdate)
            && ($user->isSuperAdmin() || ! $user->roles->contains($role));
    }

    /**
     * Determine whether the user can delete a role.
     */
    public function delete(User $user, Role $role): bool
    {
        return ! $role->isSuperAdmin()
            && $user->can(Permission::RolesDelete)
            && ($user->isSuperAdmin() || ! $user->roles->contains($role));
    }
}
