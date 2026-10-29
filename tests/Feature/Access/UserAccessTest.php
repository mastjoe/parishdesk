<?php

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

function roleWith(string $name, Permission ...$permissions): Role
{
    $role = Role::create(['name' => $name]);
    $role->givePermissionTo($permissions);

    return $role;
}

test('users can be searched and filtered by role', function () {
    $secretaries = roleWith('Secretary', Permission::UsersView);
    User::factory()->create(['name' => 'Grace Okafor'])->assignRole($secretaries);
    User::factory()->create(['name' => 'Grace Musa']);
    $viewer = User::factory()->withPermissions(Permission::UsersView)->create(['name' => 'Viewer']);

    $this->actingAs($viewer)
        ->get(route('admin.users.index', ['search' => 'grace', 'filter' => ['role' => 'Secretary']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Grace Okafor')
            ->where('table.filters.role', 'Secretary'));
});

test('an unknown sort column falls back to the default sort', function () {
    $viewer = User::factory()->withPermissions(Permission::UsersView)->create();

    $this->actingAs($viewer)
        ->get(route('admin.users.index', ['sort' => 'password; drop table users']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('table.sort', '-created_at'));
});

test('roles and direct permissions can be assigned to a user', function () {
    $role = roleWith('Auditor', Permission::ActivityView);
    $target = User::factory()->create();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.access.update', $target), [
            'roles' => ['Auditor'],
            'permissions' => [Permission::SettingsParishUpdate->value],
        ])
        ->assertSessionHasNoErrors();

    $target->refresh();

    expect($target->hasDirectPermission(Permission::SettingsParishUpdate))->toBeTrue()
        ->and($target->getRoleNames()->all())->toBe(['Auditor'])
        ->and($target->effectivePermissionNames())->toEqualCanonicalizing([
            Permission::ActivityView->value,
            Permission::SettingsParishUpdate->value,
        ]);

    expect(Activity::query()->where('event', 'access_updated')->sole()->properties->toArray())->toBe([
        'roles' => ['granted' => ['Auditor'], 'revoked' => []],
        'permissions' => ['granted' => [Permission::SettingsParishUpdate->value], 'revoked' => []],
    ]);
});

test('changing roles and changing direct permissions need separate permissions', function () {
    roleWith('Auditor', Permission::ActivityView);
    $target = User::factory()->create();
    $roleAssigner = User::factory()->withPermissions(Permission::UsersRolesAssign, Permission::ActivityView)->create();

    $this->actingAs($roleAssigner)
        ->put(route('admin.users.access.update', $target), ['permissions' => [Permission::ActivityView->value]])
        ->assertForbidden();

    $this->put(route('admin.users.access.update', $target), ['roles' => ['Auditor']])
        ->assertSessionHasNoErrors();

    expect($target->fresh()->getRoleNames()->all())->toBe(['Auditor']);
});

test('nobody can change their own access', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.access.update', $admin), ['roles' => []])
        ->assertForbidden();

    expect($admin->fresh()->isSuperAdmin())->toBeTrue();
});

test('a user cannot grant a role holding permissions they lack', function () {
    roleWith('Treasurer', Permission::SettingsFinanceUpdate);
    $target = User::factory()->create();
    $assigner = User::factory()->withPermissions(Permission::UsersRolesAssign)->create();

    $this->actingAs($assigner)
        ->put(route('admin.users.access.update', $target), ['roles' => ['Treasurer']])
        ->assertSessionHasErrors(['roles' => 'You cannot grant the Treasurer role because it includes permissions you do not have.']);

    expect($target->fresh()->roles)->toBeEmpty();
});

test('a user cannot grant direct permissions they lack', function () {
    $target = User::factory()->create();
    $assigner = User::factory()->withPermissions(Permission::UsersPermissionsAssign)->create();

    $this->actingAs($assigner)
        ->put(route('admin.users.access.update', $target), ['permissions' => [Permission::RolesDelete->value]])
        ->assertSessionHasErrors('permissions');

    expect($target->fresh()->permissions)->toBeEmpty();
});

test('only a super admin can grant the super admin role', function () {
    $target = User::factory()->create();
    $everything = User::factory()->withPermissions(...Permission::cases())->create();

    $this->actingAs($everything)
        ->put(route('admin.users.access.update', $target), ['roles' => [Role::SUPER_ADMIN]])
        ->assertSessionHasErrors(['roles' => 'Only a Super Admin can grant the Super Admin role.']);

    expect($target->fresh()->isSuperAdmin())->toBeFalse();
});

test('a super admin can promote another user to super admin', function () {
    $target = User::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->put(route('admin.users.access.update', $target), ['roles' => [Role::SUPER_ADMIN]])
        ->assertSessionHasNoErrors();

    expect($target->fresh()->isSuperAdmin())->toBeTrue();
});

test('only a super admin may act on another super admin', function (string $ability) {
    $policy = new UserPolicy;
    $superAdmin = User::factory()->superAdmin()->create();
    $manager = User::factory()->withPermissions(...Permission::cases())->create();

    expect($policy->{$ability}($manager, $superAdmin))->toBeFalse()
        ->and($policy->{$ability}(User::factory()->superAdmin()->create(), $superAdmin))->toBeTrue();
})->with(['update', 'toggleActive', 'assignRoles', 'assignPermissions']);

test('a user can be deactivated and reactivated', function () {
    $target = User::factory()->create();
    $admin = User::factory()->withPermissions(Permission::UsersUpdate)->create();

    $this->actingAs($admin)->patch(route('admin.users.status.update', $target));
    expect($target->fresh()->is_active)->toBeFalse();

    $this->patch(route('admin.users.status.update', $target));
    expect($target->fresh()->is_active)->toBeTrue();
});

test('users cannot deactivate themselves', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.status.update', $admin))
        ->assertForbidden();
});

test('user details can be updated and a new email must be verified again', function () {
    $target = User::factory()->create();

    $this->actingAs(User::factory()->withPermissions(Permission::UsersUpdate)->create())
        ->patch(route('admin.users.update', $target), ['name' => 'New Name', 'email' => 'new@example.com'])
        ->assertSessionHasNoErrors();

    $target->refresh();

    expect($target->name)->toBe('New Name')
        ->and($target->email)->toBe('new@example.com')
        ->and($target->email_verified_at)->toBeNull();
});

test('an admin can send a user a password reset link', function () {
    Notification::fake();
    $target = User::factory()->create();

    $this->actingAs(User::factory()->withPermissions(Permission::UsersUpdate)->create())
        ->post(route('admin.users.password-reset', $target))
        ->assertRedirect();

    Notification::assertSentTo($target, ResetPassword::class);
});

test('the last super admin cannot delete their account', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('password');

    expect($admin->fresh())->not->toBeNull();
});
