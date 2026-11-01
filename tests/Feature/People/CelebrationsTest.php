<?php

use App\Enums\MemberStatus;
use App\Enums\Permission;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->viewer = User::factory()->withPermissions(Permission::MembersView)->create();
});

test('birthdays in the coming week are listed soonest first, across the new year', function () {
    $this->travelTo(now()->setDate(2026, 12, 29));
    Member::factory()->create(['first_name' => 'January', 'date_of_birth' => '2000-01-02']);
    Member::factory()->create(['first_name' => 'Today', 'date_of_birth' => '1980-12-29']);
    Member::factory()->create(['first_name' => 'Later', 'date_of_birth' => '1990-01-20']);

    $this->actingAs($this->viewer)
        ->get(route('admin.members.celebrations', ['days' => 7]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('birthdays', 2)
            ->where('birthdays.0.date', '2026-12-29')
            ->where('birthdays.0.years', 46)
            ->where('birthdays.0.inDays', 0)
            ->where('birthdays.1.date', '2027-01-02')
            ->where('birthdays.1.years', 27)
            ->where('birthdays.1.inDays', 4));
});

test('a 29 February birthday is celebrated on 28 February in other years', function () {
    $this->travelTo(now()->setDate(2027, 2, 27));
    Member::factory()->create(['date_of_birth' => '2004-02-29']);

    $this->actingAs($this->viewer)
        ->get(route('admin.members.celebrations', ['days' => 7]))
        ->assertInertia(fn (Assert $page) => $page->has('birthdays', 1)->where('birthdays.0.date', '2027-02-28'));
});

test('deceased and transferred members are left out, and anniversaries are counted', function () {
    $this->travelTo(now()->setDate(2026, 5, 1));
    Member::factory()->create(['date_of_birth' => '1950-05-02', 'status' => MemberStatus::Deceased]);
    Member::factory()->create(['date_of_birth' => '1950-05-02', 'status' => MemberStatus::TransferredOut]);
    Member::factory()->create(['date_of_birth' => null, 'wedding_date' => '2001-05-10']);

    $this->actingAs($this->viewer)
        ->get(route('admin.members.celebrations'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days', 30)
            ->has('birthdays', 0)
            ->has('anniversaries', 1)
            ->where('anniversaries.0.years', 25));
});

test('an unsupported range falls back to thirty days', function () {
    $this->actingAs($this->viewer)
        ->get(route('admin.members.celebrations', ['days' => 3650]))
        ->assertInertia(fn (Assert $page) => $page->where('days', 30));
});
