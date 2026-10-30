<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    /**
     * Determine whether the user can browse the member register.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MembersView);
    }

    /**
     * Determine whether the user can view a member's profile.
     */
    public function view(User $user, Member $member): bool
    {
        return $user->can(Permission::MembersView);
    }

    /**
     * Determine whether the user can search members in a picker, which
     * forms for families, communities and clergy need.
     */
    public function lookup(User $user): bool
    {
        return $user->canAny([
            Permission::MembersView, Permission::MembersCreate, Permission::MembersUpdate,
            Permission::FamiliesUpdate, Permission::CommunitiesManage, Permission::ClergyManage,
        ]);
    }

    /**
     * Determine whether the user can register new members.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::MembersCreate);
    }

    /**
     * Determine whether the user can edit a member.
     */
    public function update(User $user, Member $member): bool
    {
        return $user->can(Permission::MembersUpdate);
    }

    /**
     * Determine whether the user can remove a member from the register.
     */
    public function delete(User $user, Member $member): bool
    {
        return $user->can(Permission::MembersDelete);
    }

    /**
     * Determine whether the user can import members from a file.
     */
    public function import(User $user): bool
    {
        return $user->can(Permission::MembersImport);
    }

    /**
     * Determine whether the user can download the member register.
     */
    public function export(User $user): bool
    {
        return $user->can(Permission::MembersExport);
    }

    /**
     * Determine whether the user can invite a member to the portal.
     *
     * Members who already have an account, or have left the register, cannot be invited.
     */
    public function invite(User $user, Member $member): bool
    {
        return $member->user_id === null && ! $member->trashed() && $user->can(Permission::MembersInvite);
    }
}
