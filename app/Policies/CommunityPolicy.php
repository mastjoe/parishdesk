<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\User;

class CommunityPolicy
{
    /**
     * Determine whether the user can list communities.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CommunitiesView) || $user->can(Permission::CommunitiesManage);
    }

    /**
     * Determine whether the user can create communities.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::CommunitiesManage);
    }

    /**
     * Determine whether the user can edit a community.
     */
    public function update(User $user, Community $community): bool
    {
        return $user->can(Permission::CommunitiesManage);
    }

    /**
     * Determine whether the user can delete a community.
     */
    public function delete(User $user, Community $community): bool
    {
        return $user->can(Permission::CommunitiesManage);
    }
}
