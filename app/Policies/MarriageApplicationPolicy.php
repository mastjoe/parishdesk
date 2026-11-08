<?php

namespace App\Policies;

use App\Enums\MarriageStatus;
use App\Enums\Permission;
use App\Models\MarriageApplication;
use App\Models\SacramentRecord;
use App\Models\User;

class MarriageApplicationPolicy
{
    /**
     * Determine whether the user can browse marriage applications.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MarriagesView);
    }

    /**
     * Determine whether the user can view an application.
     */
    public function view(User $user, MarriageApplication $application): bool
    {
        return $user->can(Permission::MarriagesView);
    }

    /**
     * Determine whether the user can open new applications.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::MarriagesCreate);
    }

    /**
     * Determine whether the user can change an application and move it
     * through the banns. Married and withdrawn applications are closed.
     */
    public function update(User $user, MarriageApplication $application): bool
    {
        return $user->can(Permission::MarriagesUpdate) && $application->isOpen();
    }

    /**
     * Determine whether the user can write the wedding in the marriage
     * register, which also needs the right to record sacraments.
     */
    public function recordWedding(User $user, MarriageApplication $application): bool
    {
        return $this->update($user, $application) && $user->can('create', SacramentRecord::class);
    }

    /**
     * Determine whether the user can remove an application. Once the couple
     * is married, the application stays with its register entry.
     */
    public function delete(User $user, MarriageApplication $application): bool
    {
        return $user->can(Permission::MarriagesDelete) && $application->status !== MarriageStatus::Married;
    }
}
