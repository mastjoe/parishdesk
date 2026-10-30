<?php

use App\Enums\FamilyRole;
use App\Enums\Permission;
use App\Models\Community;
use App\Models\Family;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('a registered member gets the next member number for the year', function () {
    $this->travelTo(now()->setDate(2026, 3, 1));
    $clerk = User::factory()->withPermissions(Permission::MembersCreate)->create();

    $this->actingAs($clerk)
        ->post(route('admin.members.store'), ['first_name' => 'Grace', 'last_name' => 'Okafor', 'gender' => 'female', 'status' => 'active'])
        ->assertSessionHasNoErrors();

    $this->actingAs($clerk)
        ->post(route('admin.members.store'), ['first_name' => 'Peter', 'last_name' => 'Eze', 'gender' => 'male', 'status' => 'active']);

    expect(Member::query()->orderBy('id')->pluck('member_no')->all())->toBe(['MEM-2026-0001', 'MEM-2026-0002']);

    $this->travelTo(now()->setDate(2027, 1, 5));

    $this->actingAs($clerk)
        ->post(route('admin.members.store'), ['first_name' => 'Ada', 'last_name' => 'Obi', 'gender' => 'female', 'status' => 'active'])
        ->assertRedirect(route('admin.members.show', Member::query()->latest('id')->first()));

    expect(Member::query()->latest('id')->value('member_no'))->toBe('MEM-2027-0001');
});

test('registering a member needs the create permission', function () {
    $viewer = User::factory()->withPermissions(Permission::MembersView)->create();

    $this->actingAs($viewer)
        ->post(route('admin.members.store'), ['first_name' => 'Grace', 'last_name' => 'Okafor', 'gender' => 'female', 'status' => 'active'])
        ->assertForbidden();

    expect(Member::query()->count())->toBe(0);
});

test('member details are validated', function (array $input, string $error) {
    $clerk = User::factory()->withPermissions(Permission::MembersCreate)->create();

    $this->actingAs($clerk)
        ->post(route('admin.members.store'), [
            'first_name' => 'Grace', 'last_name' => 'Okafor', 'gender' => 'female', 'status' => 'active', ...$input,
        ])
        ->assertSessionHasErrors($error);
})->with([
    'missing first name' => [['first_name' => ''], 'first_name'],
    'unknown gender' => [['gender' => 'other'], 'gender'],
    'unknown status' => [['status' => 'gone'], 'status'],
    'birth date in the future' => [['date_of_birth' => now()->addDay()->toDateString()], 'date_of_birth'],
    'family without a role' => [fn () => ['family_id' => Family::factory()->create()->id], 'family_role'],
    'deleted family' => [fn () => ['family_id' => tap(Family::factory()->create())->delete()->id, 'family_role' => 'child'], 'family_id'],
]);

test('names are tidied and the family role is dropped without a family', function () {
    $clerk = User::factory()->withPermissions(Permission::MembersCreate)->create();

    $this->actingAs($clerk)->post(route('admin.members.store'), [
        'first_name' => '  Grace   Ada ', 'last_name' => 'Okafor', 'gender' => 'female', 'status' => 'active',
        'email' => ' Grace@Example.COM ', 'family_role' => 'head',
    ]);

    $member = Member::query()->sole();

    expect($member->first_name)->toBe('Grace Ada')
        ->and($member->email)->toBe('grace@example.com')
        ->and($member->family_role)->toBeNull();
});

test('a photo can be uploaded, replaced and removed', function () {
    Storage::fake('media');
    $member = Member::factory()->create();
    $editor = User::factory()->withPermissions(Permission::MembersUpdate)->create();
    $fields = ['first_name' => $member->first_name, 'last_name' => $member->last_name, 'gender' => $member->gender->value, 'status' => 'active'];

    $this->actingAs($editor)->put(route('admin.members.update', $member), [...$fields, 'photo' => UploadedFile::fake()->image('one.jpg', 600, 400)]);
    $first = $member->refresh()->getFirstMedia('photo');

    expect($first)->not->toBeNull()
        ->and($first->hasGeneratedConversion('thumb'))->toBeTrue()
        ->and($first->disk)->toBe('media')
        ->and($member->photoUrl())->toBe($first->getUrl('thumb'));
    Storage::disk('media')->assertExists($first->getPathRelativeToRoot());

    $this->actingAs($editor)->put(route('admin.members.update', $member), [...$fields, 'photo' => UploadedFile::fake()->image('two.png')]);
    $second = $member->refresh()->getFirstMedia('photo');

    expect($member->getMedia('photo'))->toHaveCount(1)
        ->and($second->is($first))->toBeFalse();
    Storage::disk('media')->assertMissing($first->getPathRelativeToRoot());

    $this->actingAs($editor)->put(route('admin.members.update', $member), [...$fields, 'remove_photo' => true]);

    expect($member->refresh()->getMedia('photo'))->toHaveCount(0)
        ->and($member->photoUrl())->toBeNull();
    Storage::disk('media')->assertMissing($second->getPathRelativeToRoot());
});

test('a photo must be an image', function () {
    $member = Member::factory()->create();
    $editor = User::factory()->withPermissions(Permission::MembersUpdate)->create();

    $this->actingAs($editor)
        ->put(route('admin.members.update', $member), [
            'first_name' => 'Grace', 'last_name' => 'Okafor', 'gender' => 'female', 'status' => 'active',
            'photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('photo');
});

test('removing a member keeps the record but closes their portal', function () {
    $member = Member::factory()->withAccount()->create();
    $account = $member->user;
    $admin = User::factory()->withPermissions(Permission::MembersDelete)->create();

    $this->actingAs($admin)->delete(route('admin.members.destroy', $member))->assertRedirect(route('admin.members.index'));

    expect(Member::withTrashed()->find($member->id)->trashed())->toBeTrue()
        ->and($account->fresh()->member)->toBeNull();

    $this->actingAs($account->fresh())->get(route('portal.profile.show'))->assertRedirect(route('portal.dashboard'));
});

test('the register can be searched across name columns and filtered', function () {
    $community = Community::factory()->create();
    Member::factory()->create(['first_name' => 'Grace', 'last_name' => 'Okafor', 'community_id' => $community->id, 'date_of_birth' => now()->subYears(40)]);
    Member::factory()->create(['first_name' => 'Grace', 'last_name' => 'Musa', 'date_of_birth' => now()->subYears(30)]);
    Member::factory()->create(['first_name' => 'Ada', 'last_name' => 'Okafor', 'date_of_birth' => now()->subYears(8)]);
    $viewer = User::factory()->withPermissions(Permission::MembersView)->create();

    $this->actingAs($viewer)
        ->get(route('admin.members.index', ['search' => 'grace okafor']))
        ->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.name', 'Grace Okafor'));

    $this->actingAs($viewer)
        ->get(route('admin.members.index', ['filter' => ['community' => 'none'], 'search' => 'okafor']))
        ->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.name', 'Ada Okafor'));

    $this->actingAs($viewer)
        ->get(route('admin.members.index', ['filter' => ['age' => 'children']]))
        ->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.name', 'Ada Okafor'));
});

test('the age filter includes a member on the birthday that moves them up a group', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));
    Member::factory()->create(['first_name' => 'Turning', 'date_of_birth' => '2008-06-15']);
    Member::factory()->create(['first_name' => 'Almost', 'date_of_birth' => '2008-06-16']);
    $viewer = User::factory()->withPermissions(Permission::MembersView)->create();

    $this->actingAs($viewer)
        ->get(route('admin.members.index', ['filter' => ['age' => 'youth']]))
        ->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.name', fn (string $name) => str_starts_with($name, 'Turning')));
});

test('a member profile lists the rest of their family, head first', function () {
    $family = Family::factory()->create();
    $child = Member::factory()->inFamily($family, FamilyRole::Child)->create(['first_name' => 'Chidi']);
    Member::factory()->inFamily($family, FamilyRole::Head)->create(['first_name' => 'Emeka']);
    Member::factory()->inFamily($family, FamilyRole::Spouse)->create(['first_name' => 'Ngozi']);
    $viewer = User::factory()->withPermissions(Permission::MembersView)->create();

    $this->actingAs($viewer)
        ->get(route('admin.members.show', $child))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/people/members/Show')
            ->has('family.members', 2)
            ->where('family.members.0.familyRole', 'head')
            ->where('family.members.1.familyRole', 'spouse')
            ->where('can.update', false));
});

test('the register can be exported as CSV with the current filters', function () {
    Member::factory()->create(['first_name' => 'Grace', 'last_name' => 'Okafor', 'status' => 'active', 'phone' => '+2348031234567']);
    Member::factory()->create(['first_name' => '=HYPERLINK("x")', 'last_name' => 'Okafor', 'status' => 'active']);
    Member::factory()->create(['first_name' => 'Peter', 'last_name' => 'Eze', 'status' => 'deceased']);
    $exporter = User::factory()->withPermissions(Permission::MembersExport)->create();

    $response = $this->actingAs($exporter)->get(route('admin.members.export', ['filter' => ['status' => 'active']]));
    $csv = $response->assertOk()->streamedContent();

    expect($csv)->toContain('Grace,,Okafor')
        ->toContain('+2348031234567')
        ->toContain("'=HYPERLINK")
        ->not->toContain('Peter');
});

test('exporting needs the export permission', function () {
    $viewer = User::factory()->withPermissions(Permission::MembersView)->create();

    $this->actingAs($viewer)->get(route('admin.members.export'))->assertForbidden();
});

test('member pickers can be used by anyone who needs to choose a member', function (Permission $permission, int $status) {
    Member::factory()->create(['first_name' => 'Grace', 'last_name' => 'Okafor']);
    $user = User::factory()->withPermissions($permission)->create();

    $this->actingAs($user)->getJson(route('admin.lookup.members', ['q' => 'grace']))->assertStatus($status);
})->with([
    'communities manager' => [Permission::CommunitiesManage, 200],
    'clergy manager' => [Permission::ClergyManage, 200],
    'role manager' => [Permission::RolesUpdate, 403],
]);

test('the member lookup matches every word and returns at most ten people', function () {
    Member::factory()->count(12)->create(['first_name' => 'Chinedu', 'last_name' => 'Okafor', 'email' => null]);
    Member::factory()->create(['first_name' => 'Grace', 'last_name' => 'Okafor']);
    $viewer = User::factory()->withPermissions(Permission::MembersView)->create();

    $this->actingAs($viewer)->getJson(route('admin.lookup.members', ['q' => 'okafor']))->assertJsonCount(10, 'data');
    $this->actingAs($viewer)->getJson(route('admin.lookup.members', ['q' => 'okafor grace']))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Grace Okafor');
});
