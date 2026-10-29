<?php

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

test('a user with roles.view sees every role', function () {
    Role::create(['name' => 'Choir Coordinator']);
    $user = User::factory()->withPermissions(Permission::RolesView)->create();

    $this->actingAs($user)
        ->get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/access/roles/Index')
            ->has('roles', 2)
            ->where('roles.0.isSuperAdmin', true)
            ->where('can.create', false));
});

test('a role is created with exactly the ticked permissions', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('admin.roles.store'), [
            'name' => '  Parish   Secretary ',
            'description' => 'Runs the parish office.',
            'color' => 'blue',
            'permissions' => [Permission::UsersView->value, Permission::UsersInvite->value],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.roles.index'));

    $role = Role::findByName('Parish Secretary');

    expect($role->description)->toBe('Runs the parish office.')
        ->and($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe([Permission::UsersInvite->value, Permission::UsersView->value]);

    expect(Activity::query()->where('event', 'permissions_updated')->sole()->properties->get('granted'))
        ->toEqualCanonicalizing([Permission::UsersView->value, Permission::UsersInvite->value]);
});

test('role input is validated', function (array $payload, string $field) {
    Role::create(['name' => 'Taken']);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('admin.roles.store'), [
            'name' => 'Valid name',
            'color' => 'blue',
            'permissions' => [],
            ...$payload,
        ])
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'duplicate name' => [['name' => 'Taken'], 'name'],
    'reserved super admin name' => [['name' => Role::SUPER_ADMIN], 'name'],
    'unknown colour' => [['color' => 'neon'], 'color'],
    'unknown permission' => [['permissions' => ['members.fly']], 'permissions.0'],
]);

test('a user cannot put permissions they lack into a role', function () {
    $user = User::factory()->withPermissions(Permission::RolesCreate)->create();

    $this->actingAs($user)
        ->post(route('admin.roles.store'), [
            'name' => 'Escalation',
            'color' => 'red',
            'permissions' => [Permission::UsersRolesAssign->value],
        ])
        ->assertSessionHasErrors(['permissions' => 'You cannot grant permissions you do not have yourself: users.roles.assign.']);

    expect(Role::query()->where('name', 'Escalation')->exists())->toBeFalse();
});

test('a role can be updated and loses unticked permissions', function () {
    $role = Role::create(['name' => 'Office']);
    $role->givePermissionTo(Permission::UsersView, Permission::ActivityView);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->put(route('admin.roles.update', $role), [
            'name' => 'Parish Office',
            'color' => 'green',
            'permissions' => [Permission::UsersView->value],
        ])
        ->assertSessionHasNoErrors();

    $role->refresh();

    expect($role->name)->toBe('Parish Office')
        ->and($role->permissions->pluck('name')->all())->toBe([Permission::UsersView->value]);
});

test('the super admin role cannot be edited, duplicated or deleted', function () {
    $admin = User::factory()->superAdmin()->create();
    $superAdmin = Role::findByName(Role::SUPER_ADMIN);

    $this->actingAs($admin);

    $this->get(route('admin.roles.edit', $superAdmin))->assertForbidden();
    $this->put(route('admin.roles.update', $superAdmin), ['name' => 'Boss', 'color' => 'red', 'permissions' => []])->assertForbidden();
    $this->post(route('admin.roles.duplicate', $superAdmin))->assertForbidden();
    $this->delete(route('admin.roles.destroy', $superAdmin))->assertForbidden();

    expect(Role::query()->where('name', Role::SUPER_ADMIN)->exists())->toBeTrue();
});

test('a user cannot edit or delete a role they hold', function () {
    $role = Role::create(['name' => 'Managers']);
    $role->givePermissionTo(Permission::RolesUpdate, Permission::RolesDelete);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user);

    $this->put(route('admin.roles.update', $role), ['name' => 'Managers', 'color' => 'blue', 'permissions' => []])
        ->assertForbidden();
    $this->delete(route('admin.roles.destroy', $role))->assertForbidden();

    expect($role->fresh()->permissions)->toHaveCount(2);
});

test('a role with users cannot be deleted', function () {
    $role = Role::create(['name' => 'Ushers']);
    User::factory()->create()->assignRole($role);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->delete(route('admin.roles.destroy', $role))
        ->assertRedirect();

    expect($role->fresh())->not->toBeNull();
});

test('an unused role can be deleted', function () {
    $role = Role::create(['name' => 'Ushers']);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->delete(route('admin.roles.destroy', $role))
        ->assertRedirect(route('admin.roles.index'));

    expect($role->fresh())->toBeNull();
});

test('duplicating a role copies its permissions under a free name', function () {
    $role = Role::create(['name' => 'Choir']);
    $role->givePermissionTo(Permission::UsersView);
    Role::create(['name' => 'Copy of Choir']);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->post(route('admin.roles.duplicate', $role));

    expect(Role::findByName('Copy of Choir (2)')->permissions->pluck('name')->all())
        ->toBe([Permission::UsersView->value]);
});

test('a custom role grants exactly its permissions', function () {
    $role = Role::create(['name' => 'Designer']);
    $role->givePermissionTo(Permission::SettingsAppearanceUpdate);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user);

    $this->get(route('admin.settings.appearance.edit'))->assertOk();
    $this->get(route('admin.settings.parish.edit'))->assertForbidden();
    $this->get(route('admin.users.index'))->assertForbidden();
});
