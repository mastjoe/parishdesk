<?php

use App\Enums\ClergyRole;
use App\Enums\Permission;
use App\Models\ClergyAssignment;
use App\Models\Community;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('communities can be created with a leader', function () {
    $leader = Member::factory()->create();
    $manager = User::factory()->withPermissions(Permission::CommunitiesManage)->create();

    $this->actingAs($manager)
        ->post(route('admin.communities.store'), ['name' => 'St. Jude SCC', 'type' => 'scc', 'leader_member_id' => $leader->id])
        ->assertSessionHasNoErrors();

    $community = Community::query()->sole();

    expect($community->leader?->is($leader))->toBeTrue();
});

test('community names are unique', function () {
    Community::factory()->create(['name' => 'St. Jude SCC']);
    $manager = User::factory()->withPermissions(Permission::CommunitiesManage)->create();

    $this->actingAs($manager)
        ->post(route('admin.communities.store'), ['name' => 'St.  Jude SCC', 'type' => 'scc'])
        ->assertSessionHasErrors('name');
});

test('viewing communities does not allow changing them', function () {
    $community = Community::factory()->create();
    $viewer = User::factory()->withPermissions(Permission::CommunitiesView)->create();

    $this->actingAs($viewer)->get(route('admin.communities.index'))
        ->assertInertia(fn (Assert $page) => $page->has('communities', 1)->where('can.manage', false));
    $this->actingAs($viewer)->put(route('admin.communities.update', $community), ['name' => 'Renamed', 'type' => 'zone'])->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.communities.destroy', $community))->assertForbidden();
});

test('deleting a community leaves its members without one', function () {
    $community = Community::factory()->create();
    $member = Member::factory()->create(['community_id' => $community->id]);
    $manager = User::factory()->withPermissions(Permission::CommunitiesManage)->create();

    $this->actingAs($manager)->delete(route('admin.communities.destroy', $community));

    expect(Community::query()->count())->toBe(0)
        ->and($member->refresh()->community_id)->toBeNull();
});

test('clergy assignments are listed with those serving now first', function () {
    ClergyAssignment::factory()->ended()->create(['name' => 'Rev. Fr. Former']);
    ClergyAssignment::factory()->create(['name' => 'Rev. Fr. Current', 'role' => ClergyRole::ParishPriest]);
    $viewer = User::factory()->withPermissions(Permission::ClergyView)->create();

    $this->actingAs($viewer)->get(route('admin.clergy.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.name', 'Rev. Fr. Current')
            ->where('assignments.0.isCurrent', true)
            ->where('assignments.1.isCurrent', false)
            ->where('can.manage', false));
});

test('an assignment cannot end before it starts', function () {
    $manager = User::factory()->withPermissions(Permission::ClergyManage)->create();

    $this->actingAs($manager)
        ->post(route('admin.clergy.store'), ['name' => 'Rev. Fr. Eze', 'role' => 'parish_priest', 'starts_on' => '2024-01-01', 'ends_on' => '2023-12-31'])
        ->assertSessionHasErrors('ends_on');

    $this->actingAs($manager)
        ->post(route('admin.clergy.store'), ['name' => 'Rev. Fr. Eze', 'role' => 'parish_priest', 'starts_on' => '2024-01-01'])
        ->assertSessionHasNoErrors();

    expect(ClergyAssignment::query()->sole()->isCurrent())->toBeTrue();
});
