<?php

use App\Enums\FamilyRole;
use App\Enums\Permission;
use App\Models\Community;
use App\Models\Family;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a family can be created and opens on its page', function () {
    $community = Community::factory()->create();
    $clerk = User::factory()->withPermissions(Permission::FamiliesCreate, Permission::FamiliesView)->create();

    $response = $this->actingAs($clerk)->post(route('admin.families.store'), [
        'name' => ' Okafor   Family ', 'community_id' => $community->id,
    ]);

    $family = Family::query()->sole();

    $response->assertRedirect(route('admin.families.show', $family));
    expect($family->name)->toBe('Okafor Family')->and($family->community_id)->toBe($community->id);
});

test('adding a member moves them out of their old family and fills in the community', function () {
    $community = Community::factory()->create();
    $old = Family::factory()->create();
    $new = Family::factory()->create(['community_id' => $community->id]);
    $member = Member::factory()->inFamily($old, FamilyRole::Head)->create(['community_id' => null]);
    $editor = User::factory()->withPermissions(Permission::FamiliesUpdate)->create();

    $this->actingAs($editor)
        ->post(route('admin.families.members.store', $new), ['member_id' => $member->id, 'family_role' => 'spouse'])
        ->assertSessionHasNoErrors();

    $member->refresh();

    expect($member->family_id)->toBe($new->id)
        ->and($member->family_role)->toBe(FamilyRole::Spouse)
        ->and($member->community_id)->toBe($community->id)
        ->and($old->members()->count())->toBe(0);
});

test('a member keeps their own community when joining a family', function () {
    $own = Community::factory()->create();
    $family = Family::factory()->create(['community_id' => Community::factory()->create()->id]);
    $member = Member::factory()->create(['community_id' => $own->id]);
    $editor = User::factory()->withPermissions(Permission::FamiliesUpdate)->create();

    $this->actingAs($editor)->post(route('admin.families.members.store', $family), ['member_id' => $member->id, 'family_role' => 'child']);

    expect($member->refresh()->community_id)->toBe($own->id);
});

test('family roles can only be changed for members of that family', function () {
    $family = Family::factory()->create();
    $inside = Member::factory()->inFamily($family)->create();
    $outside = Member::factory()->inFamily(Family::factory()->create())->create();
    $editor = User::factory()->withPermissions(Permission::FamiliesUpdate)->create();

    $this->actingAs($editor)
        ->patch(route('admin.families.members.update', [$family, $inside]), ['family_role' => 'head'])
        ->assertSessionHasNoErrors();
    $this->actingAs($editor)
        ->patch(route('admin.families.members.update', [$family, $outside]), ['family_role' => 'head'])
        ->assertNotFound();
    $this->actingAs($editor)
        ->delete(route('admin.families.members.destroy', [$family, $outside]))
        ->assertNotFound();

    expect($inside->refresh()->family_role)->toBe(FamilyRole::Head)
        ->and($family->head?->is($inside))->toBeTrue()
        ->and($outside->refresh()->family_role)->toBe(FamilyRole::Child);
});

test('removing a member from a family keeps them in the register', function () {
    $family = Family::factory()->create();
    $member = Member::factory()->inFamily($family)->create();
    $editor = User::factory()->withPermissions(Permission::FamiliesUpdate)->create();

    $this->actingAs($editor)->delete(route('admin.families.members.destroy', [$family, $member]));

    $member->refresh();

    expect($member->trashed())->toBeFalse()
        ->and($member->family_id)->toBeNull()
        ->and($member->family_role)->toBeNull();
});

test('deleting a family releases its members', function () {
    $family = Family::factory()->create();
    $member = Member::factory()->inFamily($family, FamilyRole::Head)->create();
    $admin = User::factory()->withPermissions(Permission::FamiliesDelete)->create();

    $this->actingAs($admin)->delete(route('admin.families.destroy', $family))->assertRedirect(route('admin.families.index'));

    expect($family->fresh()->trashed())->toBeTrue()
        ->and($member->refresh()->family_id)->toBeNull()
        ->and($member->family_role)->toBeNull();
});

test('changing family membership needs the update permission', function () {
    $family = Family::factory()->create();
    $member = Member::factory()->create();
    $viewer = User::factory()->withPermissions(Permission::FamiliesView, Permission::MembersUpdate)->create();

    $this->actingAs($viewer)
        ->post(route('admin.families.members.store', $family), ['member_id' => $member->id, 'family_role' => 'child'])
        ->assertForbidden();
});

test('the family list shows the head and size, and can be filtered by community', function () {
    $community = Community::factory()->create();
    $family = Family::factory()->create(['name' => 'Obi Family', 'community_id' => $community->id]);
    Member::factory()->inFamily($family, FamilyRole::Head)->create(['first_name' => 'Emeka', 'last_name' => 'Obi']);
    Member::factory()->inFamily($family)->create();
    Family::factory()->create(['name' => 'Musa Family']);
    $viewer = User::factory()->withPermissions(Permission::FamiliesView)->create();

    $this->actingAs($viewer)
        ->get(route('admin.families.index', ['filter' => ['community' => $community->id]]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('families.data', 1)
            ->where('families.data.0.head', 'Emeka Obi')
            ->where('families.data.0.membersCount', 2));
});
