<?php

namespace App\Actions\Access;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\GrantGuard;
use App\Support\Permissions\Names;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces a user's roles and direct permissions.
 */
class UpdateUserAccess
{
    public function __construct(
        private GrantGuard $guard,
    ) {}

    /**
     * Sync the user's access and record the changes in the audit log.
     *
     * Passing null for roles or permissions leaves that part unchanged.
     *
     * @param  list<string>|null  $roles
     * @param  list<string>|null  $permissions
     */
    public function handle(User $actor, User $user, ?array $roles, ?array $permissions): void
    {
        $currentRoles = Names::of($user->roles);
        $currentPermissions = Names::of($user->getDirectPermissions());

        if ($roles !== null) {
            $this->guard->ensureCanGrantRoles($actor, array_values(array_diff($roles, $currentRoles)));
            $this->guard->ensureCanGrantRoles($actor, array_values(array_diff($currentRoles, $roles)));
            $this->ensureSuperAdminRemains($user, $roles);
        }

        if ($permissions !== null) {
            $this->guard->ensureCanGrantPermissions($actor, array_values(array_diff($permissions, $currentPermissions)));
            $this->guard->ensureCanGrantPermissions($actor, array_values(array_diff($currentPermissions, $permissions)));
        }

        DB::transaction(function () use ($actor, $user, $roles, $permissions, $currentRoles, $currentPermissions): void {
            $changes = [];

            if ($roles !== null) {
                $user->syncRoles($roles);
                $changes['roles'] = $this->diff($currentRoles, $roles);
            }

            if ($permissions !== null) {
                $user->syncPermissions($permissions);
                $changes['permissions'] = $this->diff($currentPermissions, $permissions);
            }

            $changes = array_filter($changes, fn (array $diff): bool => $diff['granted'] !== [] || $diff['revoked'] !== []);

            if ($changes !== []) {
                activity('access')
                    ->performedOn($user)
                    ->causedBy($actor)
                    ->event('access_updated')
                    ->withProperties($changes)
                    ->log("Updated access of {$user->name}");
            }
        });
    }

    /**
     * Refuse to remove the super admin role from the last super admin.
     *
     * @param  list<string>  $roles
     *
     * @throws ValidationException
     */
    private function ensureSuperAdminRemains(User $user, array $roles): void
    {
        if (! $user->isSuperAdmin() || in_array(Role::SUPER_ADMIN, $roles, true)) {
            return;
        }

        if (User::role(Role::SUPER_ADMIN)->where('is_active', true)->whereKeyNot($user->getKey())->doesntExist()) {
            throw ValidationException::withMessages(['roles' => __('The last Super Admin cannot lose the Super Admin role.')]);
        }
    }

    /**
     * Describe what was granted and revoked.
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     * @return array{granted: list<string>, revoked: list<string>}
     */
    private function diff(array $before, array $after): array
    {
        return [
            'granted' => array_values(array_diff($after, $before)),
            'revoked' => array_values(array_diff($before, $after)),
        ];
    }
}
