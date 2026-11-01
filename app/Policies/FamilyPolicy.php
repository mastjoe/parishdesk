<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Family;
use App\Models\User;

class FamilyPolicy
{
    /**
     * Determine whether the user can list families.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::FamiliesView);
    }

    /**
     * Determine whether the user can view a family.
     */
    public function view(User $user, Family $family): bool
    {
        return $user->can(Permission::FamiliesView);
    }

    /**
     * Determine whether the user can search families in a picker, which
     * the member form needs.
     */
    public function lookup(User $user): bool
    {
        return $user->canAny([Permission::FamiliesView, Permission::MembersCreate, Permission::MembersUpdate]);
    }

    /**
     * Determine whether the user can create families.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::FamiliesCreate);
    }

    /**
     * Determine whether the user can edit a family and its members.
     */
    public function update(User $user, Family $family): bool
    {
        return $user->can(Permission::FamiliesUpdate);
    }

    /**
     * Determine whether the user can delete a family.
     */
    public function delete(User $user, Family $family): bool
    {
        return $user->can(Permission::FamiliesDelete);
    }
}
