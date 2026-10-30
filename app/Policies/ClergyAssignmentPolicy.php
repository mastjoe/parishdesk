<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ClergyAssignment;
use App\Models\User;

class ClergyAssignmentPolicy
{
    /**
     * Determine whether the user can see the clergy history.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ClergyView) || $user->can(Permission::ClergyManage);
    }

    /**
     * Determine whether the user can record a clergy assignment.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::ClergyManage);
    }

    /**
     * Determine whether the user can edit a clergy assignment.
     */
    public function update(User $user, ClergyAssignment $assignment): bool
    {
        return $user->can(Permission::ClergyManage);
    }

    /**
     * Determine whether the user can delete a clergy assignment.
     */
    public function delete(User $user, ClergyAssignment $assignment): bool
    {
        return $user->can(Permission::ClergyManage);
    }
}
