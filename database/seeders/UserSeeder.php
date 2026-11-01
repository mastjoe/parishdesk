<?php

namespace Database\Seeders;

use App\Actions\Access\SyncPermissionCatalogue;
use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use DateTimeInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Sample roles, users and invitations for trying out access control locally.
 *
 * Every account uses the password "password". The seeder can be re-run
 * safely: it updates existing records by name and email instead of
 * duplicating them. Never run it in production, where parishes define their
 * own roles after `php artisan parishdesk:install`.
 */
class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The password given to every seeded account.
     */
    public const string PASSWORD = 'password';

    /**
     * Run the database seeds.
     */
    public function run(SyncPermissionCatalogue $sync): void
    {
        $sync->handle();

        $officeManager = $this->role('Office Manager', 'blue', 'Manages staff accounts, the parish profile and the audit log.', [
            Permission::UsersView, Permission::UsersInvite, Permission::UsersUpdate, Permission::UsersRolesAssign,
            Permission::RolesView, Permission::SettingsParishUpdate, Permission::ActivityView,
            Permission::MembersView, Permission::MembersCreate, Permission::MembersUpdate, Permission::MembersDelete,
            Permission::MembersImport, Permission::MembersExport, Permission::MembersInvite, Permission::MembersChangeRequestsApprove,
            Permission::FamiliesView, Permission::FamiliesCreate, Permission::FamiliesUpdate, Permission::FamiliesDelete,
            Permission::CommunitiesView, Permission::CommunitiesManage, Permission::ClergyView, Permission::ClergyManage,
        ]);

        $recordsClerk = $this->role('Records Clerk', 'purple', 'Keeps the member and family register up to date.', [
            Permission::MembersView, Permission::MembersCreate, Permission::MembersUpdate,
            Permission::FamiliesView, Permission::FamiliesCreate, Permission::FamiliesUpdate, Permission::CommunitiesView,
        ]);

        $financeOfficer = $this->role('Finance Officer', 'green', 'Looks after finance settings and bank details.', [
            Permission::SettingsFinanceUpdate, Permission::ActivityView,
        ]);

        $auditor = $this->role('Auditor', 'teal', 'Read-only access to users, roles and the audit log.', [
            Permission::UsersView, Permission::RolesView, Permission::ActivityView,
            Permission::MembersView, Permission::FamiliesView, Permission::CommunitiesView, Permission::ClergyView,
        ]);

        $this->user('Super Admin', 'superadmin@example.com', roles: [Role::SUPER_ADMIN]);
        $this->user('Grace Okafor', 'office@example.com', roles: [$officeManager]);
        $this->user('Peter Eze', 'finance@example.com', roles: [$financeOfficer]);
        $this->user('Mary Adeyemi', 'auditor@example.com', roles: [$auditor]);
        $this->user('Rita Ugwu', 'clerk@example.com', roles: [$recordsClerk]);

        // A role plus an extra direct permission, to see "via role" locks.
        $this->user('Joseph Bello', 'secretary@example.com', roles: [$auditor], permissions: [Permission::UsersInvite]);

        // No role at all, only a direct permission.
        $this->user('Anne Musa', 'designer@example.com', permissions: [Permission::SettingsAppearanceUpdate]);

        // Has admin access on paper but cannot sign in.
        $this->user('Paul Danjuma', 'inactive@example.com', roles: [$officeManager], active: false);

        // No admin permissions: lands in the member portal.
        $this->user('Cecilia Obi', 'member@example.com');

        $this->invitation('Theresa Nwosu', 'invited@example.com', [$financeOfficer->name], expiresAt: now()->addDays(UserInvitation::VALID_FOR_DAYS));
        $this->invitation('Martin Ibe', 'expired@example.com', [$auditor->name], expiresAt: now()->subDay());
    }

    /**
     * Create or update a role with exactly the given permissions.
     *
     * @param  list<Permission>  $permissions
     */
    private function role(string $name, string $color, string $description, array $permissions): Role
    {
        $role = Role::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => config('auth.defaults.guard')],
            ['color' => $color, 'description' => $description],
        );

        $role->syncPermissions($permissions);

        return $role;
    }

    /**
     * Create or update a verified user with exactly the given access.
     *
     * @param  list<Role|string>  $roles
     * @param  list<Permission>  $permissions
     */
    private function user(string $name, string $email, array $roles = [], array $permissions = [], bool $active = true): User
    {
        $user = User::query()->updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make(self::PASSWORD),
            'is_active' => $active,
        ]);

        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
        $user->syncRoles($roles);
        $user->syncPermissions($permissions);

        return $user;
    }

    /**
     * Create or update a staff invitation that has not been accepted.
     *
     * @param  list<string>  $roles
     */
    private function invitation(string $name, string $email, array $roles, DateTimeInterface $expiresAt): void
    {
        UserInvitation::query()->updateOrCreate(['email' => $email], [
            'name' => $name,
            'roles' => $roles,
            'permissions' => [],
            'invited_by' => User::query()->where('email', 'superadmin@example.com')->value('id'),
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'revoked_at' => null,
        ]);
    }
}
