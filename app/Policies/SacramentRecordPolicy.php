<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SacramentRecord;
use App\Models\User;

class SacramentRecordPolicy
{
    /**
     * Determine whether the user can browse the sacramental registers.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SacramentsView);
    }

    /**
     * Determine whether the user can view a register entry.
     */
    public function view(User $user, SacramentRecord $record): bool
    {
        return $user->can(Permission::SacramentsView);
    }

    /**
     * Determine whether the user can write new register entries.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::SacramentsCreate);
    }

    /**
     * Determine whether the user can correct a register entry.
     */
    public function update(User $user, SacramentRecord $record): bool
    {
        return $user->can(Permission::SacramentsUpdate);
    }

    /**
     * Determine whether the user can remove a register entry.
     */
    public function delete(User $user, SacramentRecord $record): bool
    {
        return $user->can(Permission::SacramentsDelete);
    }
}
