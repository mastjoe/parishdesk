<?php

use App\Enums\Permission;
use App\Models\ParishSetting;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission as PermissionModel;

test('sync mirrors the catalogue and removes permissions that no longer exist', function () {
    PermissionModel::findOrCreate('members.retired');
    PermissionModel::query()->where('name', Permission::ActivityView->value)->delete();

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(PermissionModel::query()->pluck('name')->all())->toEqualCanonicalizing(Permission::names());
});

test('sync leaves admin-defined roles untouched', function () {
    $role = Role::create(['name' => 'Secretary', 'description' => 'Office work', 'color' => 'blue']);
    $role->givePermissionTo(Permission::UsersView);

    $this->artisan('permissions:sync')->assertSuccessful();
    $this->artisan('permissions:sync')->assertSuccessful();

    $role->refresh();

    expect(Role::query()->pluck('name')->all())->toEqualCanonicalizing([Role::SUPER_ADMIN, 'Secretary'])
        ->and($role->description)->toBe('Office work')
        ->and($role->permissions->pluck('name')->all())->toBe([Permission::UsersView->value]);
});

test('the installer creates the super admin and no other roles', function () {
    $this->artisan('parishdesk:install', [
        '--parish' => 'St. Joseph Catholic Church',
        '--name' => 'Father John',
        '--email' => 'FR.JOHN@example.com',
        '--password' => 'a-strong-password',
    ])->assertSuccessful();

    $user = User::query()->sole();

    expect($user->email)->toBe('fr.john@example.com')
        ->and($user->isSuperAdmin())->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Role::query()->pluck('name')->all())->toBe([Role::SUPER_ADMIN])
        ->and(ParishSetting::current()->name)->toBe('St. Joseph Catholic Church');
});

test('the installer does not create a second super admin', function () {
    User::factory()->superAdmin()->create();

    $this->artisan('parishdesk:install', [
        '--parish' => 'Parish', '--name' => 'Another', '--email' => 'another@example.com', '--password' => 'a-strong-password',
    ])->assertSuccessful();

    expect(User::query()->count())->toBe(1);
});

test('the installer rejects invalid details', function () {
    $this->artisan('parishdesk:install', [
        '--parish' => 'Parish', '--name' => 'Father John', '--email' => 'not-an-email', '--password' => 'a-strong-password',
    ])->assertFailed();

    expect(User::query()->exists())->toBeFalse();
});

test('a super admin holds every permission without it being assigned', function () {
    $admin = User::factory()->superAdmin()->create();

    foreach (Permission::cases() as $permission) {
        expect($admin->can($permission))->toBeTrue();
    }

    expect($admin->getAllPermissions())->toBeEmpty();
});

test('a deactivated super admin loses the bypass', function () {
    $admin = User::factory()->superAdmin()->inactive()->create();

    expect($admin->can(Permission::UsersView))->toBeFalse()
        ->and($admin->canAccessAdmin())->toBeFalse();
});
