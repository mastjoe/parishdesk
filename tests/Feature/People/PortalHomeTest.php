<?php

use App\Enums\FamilyRole;
use App\Enums\MaritalStatus;
use App\Models\Family;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 6, 15)->setTime(9, 0));
});

test('a birthday or anniversary within thirty days is announced on the portal home', function () {
    $member = Member::factory()->withAccount()->create([
        'date_of_birth' => '1990-07-15',
        'marital_status' => MaritalStatus::Married,
        'wedding_date' => '2016-06-15',
    ]);

    $this->actingAs($member->user)
        ->get(route('portal.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('member.celebrations', [
            ['type' => 'birthday', 'date' => '2026-07-15', 'inDays' => 30, 'years' => 36],
            ['type' => 'anniversary', 'date' => '2026-06-15', 'inDays' => 0, 'years' => 10],
        ]));
});

test('celebrations further off, or an anniversary after the marriage ended, are not announced', function () {
    $member = Member::factory()->withAccount()->create([
        'date_of_birth' => '1990-07-16',
        'marital_status' => MaritalStatus::Widowed,
        'wedding_date' => '2016-06-20',
    ]);

    $this->actingAs($member->user)
        ->get(route('portal.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('member.celebrations', []));
});

test('the portal home previews the family, head first, up to five people', function () {
    $family = Family::factory()->create();
    $member = Member::factory()->inFamily($family, FamilyRole::Child)->withAccount()->create(['date_of_birth' => '2010-01-01']);
    Member::factory()->count(5)->inFamily($family, FamilyRole::Child)->create(['date_of_birth' => '2012-01-01']);
    $head = Member::factory()->inFamily($family, FamilyRole::Head)->create(['date_of_birth' => '1975-01-01']);

    $this->actingAs($member->user)
        ->get(route('portal.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('member.family.membersCount', 7)
            ->has('member.family.people', 5)
            ->where('member.family.people.0.id', $head->id)
            ->where('member.family.people.1.id', $member->id));
});
