<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ProfileChangeRequest;
use App\Models\User;

class ProfileChangeRequestPolicy
{
    /**
     * Determine whether the user can see the review queue.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MembersChangeRequestsApprove);
    }

    /**
     * Determine whether the user can approve or reject a request.
     *
     * Nobody reviews changes to their own member record.
     */
    public function review(User $user, ProfileChangeRequest $request): bool
    {
        return $request->isPending()
            && $request->member->user_id !== $user->id
            && $user->can(Permission::MembersChangeRequestsApprove);
    }
}
