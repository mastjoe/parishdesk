<?php

use App\Enums\Permission;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('users without admin permissions are sent to the member portal', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('portal.dashboard'));

    $this->get(route('portal.dashboard'))->assertOk();
});

test('users holding any admin permission are sent to the admin dashboard', function () {
    $user = User::factory()->withPermissions(Permission::ActivityView)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));

    $this->get(route('admin.dashboard'))->assertOk();
});

test('users without admin permissions cannot open the admin dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('deactivated users are signed out', function () {
    $user = User::factory()->superAdmin()->inactive()->create();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
