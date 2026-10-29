<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can list staff users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UsersView);
    }

    /**
     * Determine whether the user can view another user's access.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can(Permission::UsersView);
    }

    /**
     * Determine whether the user can invite new staff users.
     */
    public function invite(User $user): bool
    {
        return $user->can(Permission::UsersInvite);
    }

    /**
     * Determine whether the user can edit another user's details.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::UsersUpdate) && $this->outranks($user, $model);
    }

    /**
     * Determine whether the user can activate or deactivate an account.
     */
    public function toggleActive(User $user, User $model): bool
    {
        return ! $user->is($model) && $user->can(Permission::UsersUpdate) && $this->outranks($user, $model);
    }

    /**
     * Determine whether the user can change another user's roles.
     */
    public function assignRoles(User $user, User $model): bool
    {
        return ! $user->is($model) && $user->can(Permission::UsersRolesAssign) && $this->outranks($user, $model);
    }

    /**
     * Determine whether the user can change another user's direct permissions.
     */
    public function assignPermissions(User $user, User $model): bool
    {
        return ! $user->is($model) && $user->can(Permission::UsersPermissionsAssign) && $this->outranks($user, $model);
    }

    /**
     * Only a super admin may act on another super admin.
     */
    private function outranks(User $user, User $model): bool
    {
        return ! $model->isSuperAdmin() || $user->isSuperAdmin();
    }
}
