<?php

namespace App\Actions\Access;

use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as PermissionModel;

/**
 * Turns an accepted invitation into a user account.
 */
class AcceptInvitation
{
    /**
     * Create the user with the invited access.
     *
     * Roles or permissions deleted since the invitation was sent are skipped.
     */
    public function handle(UserInvitation $invitation, string $password): User
    {
        return DB::transaction(function () use ($invitation, $password): User {
            $user = User::query()->create([
                'name' => $invitation->name,
                'email' => $invitation->email,
                'password' => $password,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->syncRoles(Role::query()->whereIn('name', $invitation->roles ?? [])->get());
            $user->syncPermissions(PermissionModel::query()->whereIn('name', $invitation->permissions ?? [])->get());

            $invitation->update(['accepted_at' => now(), 'user_id' => $user->id]);

            return $user;
        });
    }
}
