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

test('members can be placed in a family with their roles as it is created', function () {
    $community = Community::factory()->create();
    $old = Family::factory()->create();
    $father = Member::factory()->create(['community_id' => null]);
    $mother = Member::factory()->inFamily($old, FamilyRole::Child)->create();
    $clerk = User::factory()->withPermissions(Permission::FamiliesCreate, Permission::FamiliesView)->create();

    $this->actingAs($clerk)->post(route('admin.families.store'), [
        'name' => 'Okafor Family',
        'community_id' => $community->id,
        'members' => [
            ['member_id' => $father->id, 'family_role' => 'father'],
            ['member_id' => $mother->id, 'family_role' => 'mother'],
        ],
    ])->assertSessionHasNoErrors();

    $family = Family::query()->where('name', 'Okafor Family')->sole();

    expect($family->head?->is($father))->toBeTrue()
        ->and($father->refresh()->community_id)->toBe($community->id)
        ->and($mother->refresh()->family_id)->toBe($family->id)
        ->and($mother->family_role)->toBe(FamilyRole::Mother)
        ->and($old->members()->count())->toBe(0);
});

test('members added while creating a family are validated', function (Closure $members, string $error) {
    $clerk = User::factory()->withPermissions(Permission::FamiliesCreate)->create();

    $this->actingAs($clerk)
        ->post(route('admin.families.store'), ['name' => 'Okafor Family', 'members' => $members()])
        ->assertSessionHasErrors($error);

    expect(Family::query()->count())->toBe(0);
})->with([
    'two fathers' => [fn () => [
        ['member_id' => Member::factory()->create()->id, 'family_role' => 'father'],
        ['member_id' => Member::factory()->create()->id, 'family_role' => 'father'],
    ], 'members'],
    'two mothers' => [fn () => [
        ['member_id' => Member::factory()->create()->id, 'family_role' => 'mother'],
        ['member_id' => Member::factory()->create()->id, 'family_role' => 'mother'],
    ], 'members'],
    'the same person twice' => [fn () => [
        ['member_id' => $id = Member::factory()->create()->id, 'family_role' => 'father'],
        ['member_id' => $id, 'family_role' => 'child'],
    ], 'members.0.member_id'],
    'a removed member' => [fn () => [
        ['member_id' => tap(Member::factory()->create())->delete()->id, 'family_role' => 'child'],
    ], 'members.0.member_id'],
    'an unknown role' => [fn () => [
        ['member_id' => Member::factory()->create()->id, 'family_role' => 'cousin'],
    ], 'members.0.family_role'],
]);

test('editing a family ignores any members sent with it', function () {
    $family = Family::factory()->create();
    $member = Member::factory()->create();
    $editor = User::factory()->withPermissions(Permission::FamiliesUpdate)->create();

    $this->actingAs($editor)->put(route('admin.families.update', $family), [
        'name' => $family->name, 'members' => [['member_id' => $member->id, 'family_role' => 'father']],
    ])->assertSessionHasNoErrors();

    expect($member->refresh()->family_id)->toBeNull();
});

test('staff who can create families can search the register for members', function () {
    Member::factory()->create(['first_name' => 'Zebedee']);
    $clerk = User::factory()->withPermissions(Permission::FamiliesCreate)->create();

    $this->actingAs($clerk)
        ->getJson(route('admin.lookup.members', ['q' => 'Zebedee']))
        ->assertOk()
        ->assertJsonPath('data.0.name', fn (string $name) => str_contains($name, 'Zebedee'));
});

test('the new family page lists the communities to choose from', function () {
    Community::factory()->create(['name' => 'St. Jude SCC']);
    $clerk = User::factory()->withPermissions(Permission::FamiliesCreate)->create();

    $this->actingAs($clerk)
        ->get(route('admin.families.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/people/families/Create')
            ->where('communities.0.name', 'St. Jude SCC'));
});

test('a family is edited on its own page and saving returns to the family', function () {
    $family = Family::factory()->create(['name' => 'Okafor Family']);
    Member::factory()->count(2)->inFamily($family)->create();
    $editor = User::factory()->withPermissions(Permission::FamiliesUpdate, Permission::FamiliesView)->create();

    $this->actingAs($editor)
        ->get(route('admin.families.edit', $family))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/people/families/Edit')
            ->where('family.name', 'Okafor Family')
            ->where('family.membersCount', 2));

    $this->actingAs($editor)
        ->put(route('admin.families.update', $family), ['name' => 'Okafor-Eze Family', 'home_phone' => '08010000000'])
        ->assertRedirect(route('admin.families.show', $family));

    expect($family->refresh()->only(['name', 'home_phone']))->toBe(['name' => 'Okafor-Eze Family', 'home_phone' => '08010000000']);
});

test('the family form pages need the matching permission', function () {
    $family = Family::factory()->create();
    $viewer = User::factory()->withPermissions(Permission::FamiliesView)->create();

    $this->actingAs($viewer)->get(route('admin.families.create'))->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.families.edit', $family))->assertForbidden();
});

test('adding a member moves them out of their old family and fills in the community', function () {
    $community = Community::factory()->create();
    $old = Family::factory()->create();
    $new = Family::factory()->create(['community_id' => $community->id]);
    $member = Member::factory()->inFamily($old, FamilyRole::Father)->create(['community_id' => null]);
    $editor = User::factory()->withPermissions(Permission::FamiliesUpdate)->create();

    $this->actingAs($editor)
        ->post(route('admin.families.members.store', $new), ['member_id' => $member->id, 'family_role' => 'mother'])
        ->assertSessionHasNoErrors();

    $member->refresh();

    expect($member->family_id)->toBe($new->id)
        ->and($member->family_role)->toBe(FamilyRole::Mother)
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
        ->patch(route('admin.families.members.update', [$family, $inside]), ['family_role' => 'father'])
        ->assertSessionHasNoErrors();
    $this->actingAs($editor)
        ->patch(route('admin.families.members.update', [$family, $outside]), ['family_role' => 'father'])
        ->assertNotFound();
    $this->actingAs($editor)
        ->delete(route('admin.families.members.destroy', [$family, $outside]))
        ->assertNotFound();

    expect($inside->refresh()->family_role)->toBe(FamilyRole::Father)
        ->and($family->head?->is($inside))->toBeTrue()
        ->and($outside->refresh()->family_role)->toBe(FamilyRole::Child);
});

test('the head of family is the father, or the mother when there is no father', function () {
    $family = Family::factory()->create();
    $mother = Member::factory()->inFamily($family, FamilyRole::Mother)->create();
    Member::factory()->inFamily($family, FamilyRole::Grandparent)->create();

    expect($family->head?->is($mother))->toBeTrue();

    $father = Member::factory()->inFamily($family, FamilyRole::Father)->create();

    expect($family->fresh()->head?->is($father))->toBeTrue()
        ->and(Family::query()->with('head')->find($family->id)?->head?->is($father))->toBeTrue();
});

test('a family keeps one father and one mother wherever roles are set', function () {
    $family = Family::factory()->create();
    $father = Member::factory()->inFamily($family, FamilyRole::Father)->create(['first_name' => 'Emeka']);
    $child = Member::factory()->inFamily($family, FamilyRole::Child)->create();
    $newcomer = Member::factory()->create();
    $editor = User::factory()->withPermissions(Permission::FamiliesUpdate, Permission::MembersUpdate)->create();

    $this->actingAs($editor)
        ->post(route('admin.families.members.store', $family), ['member_id' => $newcomer->id, 'family_role' => 'father'])
        ->assertSessionHasErrors('family_role');

    expect(session('errors')->first('family_role'))->toContain('Emeka')->toContain('already the father');

    $this->actingAs($editor)
        ->patch(route('admin.families.members.update', [$family, $child]), ['family_role' => 'father'])
        ->assertSessionHasErrors('family_role');

    $this->actingAs($editor)
        ->put(route('admin.members.update', $newcomer), [
            ...$newcomer->only(['first_name', 'last_name']), 'gender' => 'male', 'status' => 'active',
            'family_id' => $family->id, 'family_role' => 'father',
        ])
        ->assertSessionHasErrors('family_role');

    // The father keeping his own role is fine.
    $this->actingAs($editor)
        ->patch(route('admin.families.members.update', [$family, $father]), ['family_role' => 'father'])
        ->assertSessionHasNoErrors();

    expect($child->refresh()->family_role)->toBe(FamilyRole::Child)
        ->and($newcomer->refresh()->family_id)->toBeNull();
});

test('the family tree order lists every role in the order they are declared', function () {
    $order = collect(FamilyRole::cases())
        ->map(fn (FamilyRole $role, int $position): string => "when '{$role->value}' then {$position}")
        ->implode(' ');

    expect(str_replace('  ', ' ', FamilyRole::ORDER_EXPRESSION))
        ->toBe("case family_role {$order} else ".count(FamilyRole::cases()).' end');
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
    $member = Member::factory()->inFamily($family, FamilyRole::Father)->create();
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
    Member::factory()->inFamily($family, FamilyRole::Father)->create(['first_name' => 'Emeka', 'last_name' => 'Obi']);
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
