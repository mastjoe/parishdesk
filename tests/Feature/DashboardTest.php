<?php

use App\Enums\MemberStatus;
use App\Enums\Permission;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

test('the dashboard counts living members by age group, with group edges inclusive', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));
    Member::factory()->create(['date_of_birth' => '2014-06-15']); // 12 today: still a child
    Member::factory()->create(['date_of_birth' => '2013-06-15']); // 13 today: a teen
    Member::factory()->create(['date_of_birth' => '1966-06-15']); // 60 today: a senior
    Member::factory()->create(['date_of_birth' => null]);
    Member::factory()->create(['date_of_birth' => '1990-01-01', 'status' => MemberStatus::Deceased]);
    $user = User::factory()->withPermissions(Permission::MembersView)->create();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.members.ageGroups.groups', [
                ['label' => 'Children (0-12)', 'count' => 1],
                ['label' => 'Teens (13-17)', 'count' => 1],
                ['label' => 'Youth (18-35)', 'count' => 0],
                ['label' => 'Adults (36-59)', 'count' => 0],
                ['label' => 'Seniors (60+)', 'count' => 1],
            ])
            ->where('stats.members.ageGroups.unknown', 1)
            ->where('stats.members.active', 4));
});

test('the dashboard leaves out member figures for staff who cannot view members', function () {
    $user = User::factory()->withPermissions(Permission::ActivityView)->create();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.members', null)->where('birthdays', null));
});
