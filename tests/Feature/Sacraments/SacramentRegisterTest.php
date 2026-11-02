<?php

use App\Enums\FamilyRole;
use App\Enums\Permission;
use App\Enums\SacramentType;
use App\Models\Family;
use App\Models\Member;
use App\Models\SacramentRecord;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Get a valid request body for a register entry.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sacramentEntry(array $overrides = []): array
{
    return [
        'type' => 'baptism',
        'person_name' => 'Chiamaka Obi',
        'gender' => 'female',
        'date_of_birth' => '2026-08-02',
        'received_on' => '2026-10-04',
        'elsewhere' => false,
        'minister' => 'Rev. Fr. Michael Eze',
        'father_name' => 'Emeka Obi',
        'mother_name' => 'Cecilia Obi',
        'register_book' => '4',
        'register_page' => '12',
        'entry_no' => '231',
        'sponsors' => [],
        ...$overrides,
    ];
}

function registrar(): User
{
    return User::factory()->withPermissions(Permission::SacramentsView, Permission::SacramentsCreate, Permission::SacramentsUpdate)->create();
}

test('a baptism is recorded with its godparents', function () {
    $this->travelTo('2026-11-01');
    $member = Member::factory()->create();
    $godmother = Member::factory()->create();
    $user = registrar();

    $response = $this->actingAs($user)->post(route('admin.sacraments.store'), sacramentEntry([
        'member_id' => $member->id,
        'sponsors' => [
            ['member_id' => null, 'name' => '  Paul   Nnaji ', 'role' => 'godfather'],
            ['member_id' => $godmother->id, 'name' => $godmother->full_name, 'role' => 'godmother'],
        ],
    ]));

    $record = SacramentRecord::query()->sole();

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.sacraments.show', $record));

    expect($record->type)->toBe(SacramentType::Baptism)
        ->and($record->member->is($member))->toBeTrue()
        ->and($record->recorded_by)->toBe($user->id)
        ->and($record->registerReference())->toBe('Book 4, page 12, entry 231')
        ->and($record->sponsors->pluck('name')->all())->toBe(['Paul Nnaji', $godmother->full_name])
        ->and($record->sponsors[1]->member_id)->toBe($godmother->id);
});

test('the register lists entries by sacrament and year', function () {
    SacramentRecord::factory()->create(['received_on' => '2025-06-01']);
    SacramentRecord::factory()->create(['received_on' => '2024-06-01']);
    SacramentRecord::factory()->ofType(SacramentType::Confirmation)->create(['received_on' => '2025-08-01']);
    SacramentRecord::factory()->create(['received_on' => '2025-07-01'])->delete();

    $this->actingAs(registrar())
        ->get(route('admin.sacraments.index', ['filter' => ['type' => 'baptism', 'year' => '2025']]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/sacraments/Index')
            ->has('records.data', 1)
            ->where('records.data.0.receivedOn', '2025-06-01')
            ->where('counts.baptism', 2)
            ->where('counts.confirmation', 1)
            ->where('years', fn ($years) => collect($years)->last() === 2024));
});

test('a member is baptised, receives First Holy Communion and is confirmed only once', function (string $type) {
    $member = Member::factory()->create();
    SacramentRecord::factory()->ofType(SacramentType::from($type))->forMember($member)->create();

    $this->actingAs(registrar())
        ->post(route('admin.sacraments.store'), sacramentEntry(['type' => $type, 'member_id' => $member->id, 'entry_no' => null]))
        ->assertSessionHasErrors('member_id');
})->with(['baptism', 'first_holy_communion', 'confirmation']);

test('a removed entry no longer counts as the member having received the sacrament', function () {
    $member = Member::factory()->create();
    SacramentRecord::factory()->forMember($member)->create()->delete();

    $this->actingAs(registrar())
        ->post(route('admin.sacraments.store'), sacramentEntry(['member_id' => $member->id]))
        ->assertSessionHasNoErrors();
});

test('anointing of the sick can be recorded for a member more than once', function () {
    $member = Member::factory()->create();
    SacramentRecord::factory()->ofType(SacramentType::AnointingOfTheSick)->forMember($member)->create();

    $this->actingAs(registrar())
        ->post(route('admin.sacraments.store'), sacramentEntry(['type' => 'anointing_of_the_sick', 'member_id' => $member->id, 'entry_no' => null]))
        ->assertSessionHasNoErrors();

    expect($member->sacraments()->count())->toBe(2);
});

test('an entry number is used once in each register book', function () {
    SacramentRecord::factory()->create(['register_book' => '4', 'entry_no' => '231']);
    $user = registrar();

    $this->actingAs($user)
        ->post(route('admin.sacraments.store'), sacramentEntry(['register_book' => '4', 'entry_no' => '231']))
        ->assertSessionHasErrors(['entry_no' => 'This entry number is already used in that register book.']);

    $this->actingAs($user)
        ->post(route('admin.sacraments.store'), sacramentEntry(['register_book' => '5', 'entry_no' => '231']))
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('admin.sacraments.store'), sacramentEntry(['type' => 'confirmation', 'register_book' => '4', 'entry_no' => '231']))
        ->assertSessionHasNoErrors();
});

test('entries are validated against what their register records', function (array $overrides, string $error) {
    $this->travelTo('2026-11-01');

    $this->actingAs(registrar())
        ->post(route('admin.sacraments.store'), sacramentEntry($overrides))
        ->assertSessionHasErrors($error);
})->with([
    'a witness at a baptism' => [['sponsors' => [['name' => 'Ada Eze', 'role' => 'witness']]], 'sponsors.0.role'],
    'too many sponsors at a confirmation' => [['type' => 'confirmation', 'sponsors' => array_fill(0, 3, ['name' => 'Ada Eze', 'role' => 'sponsor'])], 'sponsors'],
    'a sponsor at First Holy Communion' => [['type' => 'first_holy_communion', 'sponsors' => [['name' => 'Ada Eze', 'role' => 'sponsor']]], 'sponsors'],
    'a sponsor without a name' => [['sponsors' => [['name' => '  ', 'role' => 'godmother']]], 'sponsors.0.name'],
    'a date in the future' => [['received_on' => '2026-11-02'], 'received_on'],
    'a birth after the sacrament' => [['date_of_birth' => '2026-10-05'], 'date_of_birth'],
    'another parish without its name' => [['elsewhere' => true, 'place' => ''], 'place'],
    'a marriage without the spouse' => [['type' => 'matrimony', 'spouse_name' => ''], 'spouse_name'],
    'an ordination without the order' => [['type' => 'holy_orders'], 'details.order'],
]);

test('the person receiving a sacrament cannot be their own sponsor or spouse', function () {
    $member = Member::factory()->create();
    $user = registrar();

    $this->actingAs($user)
        ->post(route('admin.sacraments.store'), sacramentEntry([
            'member_id' => $member->id,
            'sponsors' => [['member_id' => $member->id, 'name' => $member->full_name, 'role' => 'godfather']],
        ]))
        ->assertSessionHasErrors('sponsors');

    $this->actingAs($user)
        ->post(route('admin.sacraments.store'), sacramentEntry([
            'type' => 'matrimony',
            'member_id' => $member->id,
            'spouse_member_id' => $member->id,
            'spouse_name' => $member->full_name,
        ]))
        ->assertSessionHasErrors('spouse_member_id');
});

test('a marriage shows on the profiles of both spouses', function () {
    $husband = Member::factory()->create();
    $wife = Member::factory()->create();
    $user = User::factory()->withPermissions(Permission::MembersView, Permission::SacramentsView, Permission::SacramentsCreate)->create();

    $this->actingAs($user)->post(route('admin.sacraments.store'), sacramentEntry([
        'type' => 'matrimony',
        'member_id' => $husband->id,
        'person_name' => $husband->full_name,
        'spouse_member_id' => $wife->id,
        'spouse_name' => $wife->full_name,
        'sponsors' => [['name' => 'Ada Eze', 'role' => 'witness'], ['name' => 'Obi Eze', 'role' => 'witness']],
    ]))->assertSessionHasNoErrors();

    $record = SacramentRecord::query()->sole();

    // The parents' names are not part of the marriage register.
    expect($record->father_name)->toBeNull()->and($record->mother_name)->toBeNull();

    foreach ([$husband, $wife] as $spouse) {
        $this->actingAs($user)->get(route('admin.members.show', $spouse))
            ->assertInertia(fn (Assert $page) => $page
                ->has('sacraments', 1)
                ->where('sacraments.0.type', 'matrimony')
                ->where('can.viewSacraments', true));
    }
});

test('the place is only kept for sacraments celebrated in another parish', function () {
    $user = registrar();

    $this->actingAs($user)->post(route('admin.sacraments.store'), sacramentEntry(['elsewhere' => false, 'place' => 'Holy Ghost Cathedral']));
    $this->actingAs($user)->post(route('admin.sacraments.store'), sacramentEntry(['elsewhere' => true, 'place' => 'Holy Ghost Cathedral', 'entry_no' => null]));

    expect(SacramentRecord::query()->orderBy('id')->pluck('place')->all())->toBe([null, 'Holy Ghost Cathedral']);
});

test('correcting an entry keeps its sacrament and replaces its sponsors', function () {
    $record = SacramentRecord::factory()->create(['entry_no' => '12']);
    $record->sponsors()->create(['name' => 'Old Godfather', 'role' => 'godfather']);

    $this->actingAs(registrar())
        ->put(route('admin.sacraments.update', $record), sacramentEntry([
            'type' => 'confirmation',
            'entry_no' => '12',
            'sponsors' => [['name' => 'New Godmother', 'role' => 'godmother']],
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.sacraments.show', $record));

    $record->refresh();

    expect($record->type)->toBe(SacramentType::Baptism)
        ->and($record->person_name)->toBe('Chiamaka Obi')
        ->and($record->sponsors()->pluck('name')->all())->toBe(['New Godmother']);
});

test('the form copies a child\'s details and parents from their family', function () {
    $family = Family::factory()->create();
    $father = Member::factory()->inFamily($family, FamilyRole::Father)->create(['first_name' => 'Emeka', 'middle_name' => null]);
    Member::factory()->inFamily($family, FamilyRole::Mother)->create(['first_name' => 'Cecilia', 'middle_name' => null]);
    $child = Member::factory()->inFamily($family)->create(['date_of_birth' => '2026-08-02']);

    $this->actingAs(registrar())
        ->get(route('admin.sacraments.create', ['type' => 'confirmation', 'member' => $child->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/sacraments/Create')
            ->where('type', 'confirmation')
            ->where('person.id', $child->id)
            ->where('person.dateOfBirth', '2026-08-02')
            ->where('person.fatherName', 'Emeka '.$father->last_name)
            ->where('person.motherName', fn (string $name) => str_starts_with($name, 'Cecilia')));

    $this->actingAs(registrar())
        ->get(route('admin.sacraments.create', ['member' => $father->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('type', 'baptism')
            ->where('person.fatherName', null)
            ->where('person.motherName', null));
});

test('removing an entry keeps it in the database', function () {
    $record = SacramentRecord::factory()->create();
    $user = User::factory()->withPermissions(Permission::SacramentsView, Permission::SacramentsDelete)->create();

    $this->actingAs($user)
        ->delete(route('admin.sacraments.destroy', $record))
        ->assertRedirect(route('admin.sacraments.index'));

    expect($record->fresh()->trashed())->toBeTrue();
});

test('viewing the registers does not allow changing them', function () {
    $record = SacramentRecord::factory()->create();
    $viewer = User::factory()->withPermissions(Permission::SacramentsView)->create();

    $this->actingAs($viewer)->get(route('admin.sacraments.show', $record))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/sacraments/Show')
            ->where('record.id', $record->id)
            ->where('can.update', false)
            ->where('can.delete', false));
    $this->actingAs($viewer)->get(route('admin.sacraments.create'))->assertForbidden();
    $this->actingAs($viewer)->post(route('admin.sacraments.store'), sacramentEntry())->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.sacraments.update', $record), sacramentEntry())->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.sacraments.destroy', $record))->assertForbidden();
});

test('member profiles leave out sacraments for staff who cannot view the registers', function () {
    $member = Member::factory()->create();
    SacramentRecord::factory()->forMember($member)->create();

    $this->actingAs(User::factory()->withPermissions(Permission::MembersView)->create())
        ->get(route('admin.members.show', $member))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sacraments', [])
            ->where('can.viewSacraments', false));
});
