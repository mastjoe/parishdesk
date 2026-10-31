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
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.communities.index'));

    $community = Community::query()->sole();

    expect($community->leader?->is($leader))->toBeTrue();
});

test('the new community page offers the community types', function () {
    $manager = User::factory()->withPermissions(Permission::CommunitiesManage)->create();

    $this->actingAs($manager)
        ->get(route('admin.communities.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/people/communities/Create')
            ->where('options.communityTypes.0.value', 'scc'));
});

test('a community is edited on its own page and saving returns to the list', function () {
    $leader = Member::factory()->create();
    $community = Community::factory()->create(['name' => 'St. Jude SCC', 'leader_member_id' => $leader->id]);
    Member::factory()->count(2)->create(['community_id' => $community->id]);
    $manager = User::factory()->withPermissions(Permission::CommunitiesManage)->create();

    $this->actingAs($manager)
        ->get(route('admin.communities.edit', $community))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/people/communities/Edit')
            ->where('community.name', 'St. Jude SCC')
            ->where('community.leader.id', $leader->id)
            ->where('community.membersCount', 2));

    $this->actingAs($manager)
        ->put(route('admin.communities.update', $community), ['name' => 'St. Jude Station', 'type' => 'station', 'leader_member_id' => null])
        ->assertRedirect(route('admin.communities.index'));

    expect($community->refresh()->name)->toBe('St. Jude Station')
        ->and($community->leader_member_id)->toBeNull();
});

test('the community form pages need the manage permission', function () {
    $community = Community::factory()->create();
    $viewer = User::factory()->withPermissions(Permission::CommunitiesView)->create();

    $this->actingAs($viewer)->get(route('admin.communities.create'))->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.communities.edit', $community))->assertForbidden();
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
