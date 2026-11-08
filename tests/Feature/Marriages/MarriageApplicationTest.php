<?php

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MarriageCheck;
use App\Enums\MarriageStatus;
use App\Enums\Permission;
use App\Enums\SacramentType;
use App\Models\BannsObjection;
use App\Models\MarriageApplication;
use App\Models\Member;
use App\Models\SacramentRecord;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Get a valid request body for a marriage application.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function marriageApplication(array $overrides = []): array
{
    return [
        'groom_name' => 'Chidi Okeke',
        'groom_parish' => null,
        'bride_name' => 'Ngozi Eze',
        'bride_parish' => 'Holy Ghost Cathedral, Enugu',
        'proposed_date' => '2027-02-13',
        'officiant' => 'Rev. Fr. Michael Eze',
        ...$overrides,
    ];
}

function marriageOfficer(Permission ...$extra): User
{
    return User::factory()->withPermissions(Permission::MarriagesView, Permission::MarriagesCreate, Permission::MarriagesUpdate, ...$extra)->create();
}

test('an application is opened with its own number', function () {
    $this->travelTo('2026-11-01');
    $groom = Member::factory()->create(['gender' => Gender::Male]);
    $user = marriageOfficer();

    $response = $this->actingAs($user)->post(route('admin.marriages.store'), marriageApplication([
        'groom_member_id' => $groom->id,
        'groom_name' => '  Chidi   Okeke ',
    ]));

    $application = MarriageApplication::query()->sole();

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.marriages.show', $application));

    expect($application->application_no)->toBe('MAR/2026/001')
        ->and($application->status)->toBe(MarriageStatus::Applied)
        ->and($application->groom->is($groom))->toBeTrue()
        ->and($application->groom_name)->toBe('Chidi Okeke')
        ->and($application->bride_parish)->toBe('Holy Ghost Cathedral, Enugu')
        ->and($application->created_by)->toBe($user->id);
});

test('an application names a man as groom and a woman as bride', function (string $side, Gender $gender, string $message) {
    $member = Member::factory()->create(['gender' => $gender, 'first_name' => 'Ada', 'middle_name' => null, 'last_name' => 'Obi']);

    $this->actingAs(marriageOfficer())
        ->post(route('admin.marriages.store'), marriageApplication(["{$side}_member_id" => $member->id]))
        ->assertSessionHasErrors(["{$side}_member_id" => $message]);
})->with([
    'a woman as groom' => ['groom', Gender::Female, 'Ada Obi is not recorded as a man in the member register.'],
    'a man as bride' => ['bride', Gender::Male, 'Ada Obi is not recorded as a woman in the member register.'],
]);

test('the groom and the bride must be different people', function () {
    $member = Member::factory()->create();

    $this->actingAs(marriageOfficer())
        ->post(route('admin.marriages.store'), marriageApplication(['groom_member_id' => $member->id, 'bride_member_id' => $member->id]))
        ->assertSessionHasErrors(['groom_member_id' => 'The groom and the bride must be different people.']);
});

test('a member can only be in one open application', function () {
    $bride = Member::factory()->create(['gender' => Gender::Female]);
    $open = MarriageApplication::factory()->forCouple(bride: $bride)->create(['application_no' => 'MAR/2026/004']);
    $user = marriageOfficer();

    $this->actingAs($user)
        ->post(route('admin.marriages.store'), marriageApplication(['bride_member_id' => $bride->id]))
        ->assertSessionHasErrors(['bride_member_id' => "{$bride->full_name} already has an open marriage application (MAR/2026/004)."]);

    // Saving the open application itself is not a clash.
    $this->actingAs($user)
        ->put(route('admin.marriages.update', $open), marriageApplication(['bride_member_id' => $bride->id]))
        ->assertSessionHasNoErrors();

    $open->forceFill(['status' => MarriageStatus::Withdrawn])->save();

    $this->actingAs($user)
        ->post(route('admin.marriages.store'), marriageApplication(['bride_member_id' => $bride->id]))
        ->assertSessionHasNoErrors();
});

test('an application needs the names of the groom and the bride', function () {
    $this->actingAs(marriageOfficer())
        ->post(route('admin.marriages.store'), ['groom_name' => ' ', 'bride_name' => ''])
        ->assertSessionHasErrors([
            'groom_name' => 'The groom\'s name field is required.',
            'bride_name' => 'The bride\'s name field is required.',
        ]);
});

test('the documents are verified only once every check is done', function () {
    $application = MarriageApplication::factory()->create();
    $user = marriageOfficer();

    $this->actingAs($user)
        ->put(route('admin.marriages.checks.update', $application), ['checks' => [MarriageCheck::MarriageCourse->value]])
        ->assertSessionHasNoErrors();

    expect($application->fresh()->checks)->toBe([MarriageCheck::MarriageCourse->value]);

    $this->actingAs($user)
        ->post(route('admin.marriages.verify', $application))
        ->assertSessionHasErrors(['stage' => 'Complete every check before verifying the documents.']);

    $this->actingAs($user)->put(route('admin.marriages.checks.update', $application), ['checks' => MarriageCheck::values()]);
    $this->actingAs($user)->post(route('admin.marriages.verify', $application))->assertSessionHasNoErrors();

    expect($application->fresh())
        ->status->toBe(MarriageStatus::DocumentsVerified)
        ->documents_verified_at->not->toBeNull();

    $this->actingAs($user)
        ->put(route('admin.marriages.checks.update', $application), ['checks' => []])
        ->assertSessionHasErrors(['stage' => 'The checklist is fixed once the documents are verified.']);
});

test('the banns are published three times in order', function () {
    $this->travelTo('2026-11-01');
    $application = MarriageApplication::factory()->documentsVerified()->create();
    $user = marriageOfficer();

    foreach (['2026-10-18', '2026-10-25', '2026-11-01'] as $sunday) {
        $this->actingAs($user)
            ->post(route('admin.marriages.banns.store', $application), ['published_on' => $sunday])
            ->assertSessionHasNoErrors();
    }

    $application->refresh();

    expect($application->status)->toBe(MarriageStatus::Banns)
        ->and($application->publications->pluck('number')->all())->toBe([1, 2, 3])
        ->and($application->publications[0]->recorded_by)->toBe($user->id);

    $this->actingAs($user)
        ->post(route('admin.marriages.banns.store', $application), ['published_on' => '2026-11-01'])
        ->assertSessionHasErrors(['stage' => 'The banns cannot be published at this stage.']);
});

test('each publication of the banns comes after the one before', function () {
    $this->travelTo('2026-11-01');
    $application = MarriageApplication::factory()->withBanns(1)->create();

    $this->actingAs(marriageOfficer())
        ->post(route('admin.marriages.banns.store', $application), ['published_on' => $application->publications->sole()->published_on->toDateString()])
        ->assertSessionHasErrors(['published_on' => 'Publish the banns after the previous publication on 25 October 2026.']);
});

test('the banns are not published before the documents are verified, or in the future', function () {
    $this->travelTo('2026-11-01');
    $user = marriageOfficer();

    $this->actingAs($user)
        ->post(route('admin.marriages.banns.store', MarriageApplication::factory()->checked()->create()), ['published_on' => '2026-11-01'])
        ->assertSessionHasErrors(['stage' => 'The banns cannot be published at this stage.']);

    $this->actingAs($user)
        ->post(route('admin.marriages.banns.store', MarriageApplication::factory()->documentsVerified()->create()), ['published_on' => '2026-11-08'])
        ->assertSessionHasErrors(['published_on' => 'Record the banns once they have been announced.']);
});

test('only the latest publication of the banns can be taken back', function () {
    $application = MarriageApplication::factory()->withBanns(2)->create();
    [$first, $second] = $application->publications;
    $user = marriageOfficer();

    $this->actingAs($user)
        ->delete(route('admin.marriages.banns.destroy', [$application, $first]))
        ->assertSessionHasErrors(['stage' => 'Only the latest publication of the banns can be taken back.']);

    $this->actingAs($user)->delete(route('admin.marriages.banns.destroy', [$application, $second]))->assertSessionHasNoErrors();
    expect($application->fresh()->status)->toBe(MarriageStatus::Banns);

    // With no banns left, the application goes back to waiting for the first.
    $this->actingAs($user)->delete(route('admin.marriages.banns.destroy', [$application, $first]))->assertSessionHasNoErrors();
    expect($application->fresh()->status)->toBe(MarriageStatus::DocumentsVerified)
        ->and($application->publications()->count())->toBe(0);
});

test('an objection holds up the banns and the clearance until it is resolved', function () {
    $this->travelTo('2026-11-01');
    $application = MarriageApplication::factory()->withBanns(2)->create();
    $user = marriageOfficer();

    $this->actingAs($user)
        ->post(route('admin.marriages.objections.store', $application), [
            'raised_by' => 'Mrs. Grace Nnaji',
            'raised_on' => '2026-10-30',
            'details' => 'Says the groom is already married.',
        ])
        ->assertSessionHasNoErrors();

    $objection = $application->objections()->sole();
    expect($objection->recorded_by)->toBe($user->id);

    $this->actingAs($user)
        ->post(route('admin.marriages.banns.store', $application), ['published_on' => '2026-11-01'])
        ->assertSessionHasErrors(['stage' => 'Resolve the objection before the banns are published again.']);

    $this->actingAs($user)
        ->patch(route('admin.marriages.objections.update', [$application, $objection]), ['resolution' => 'The earlier union was never a marriage.'])
        ->assertSessionHasNoErrors();

    expect($objection->fresh())
        ->resolved_at->not->toBeNull()
        ->resolved_by->toBe($user->id);

    $this->actingAs($user)->post(route('admin.marriages.banns.store', $application), ['published_on' => '2026-11-01'])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('admin.marriages.clear', $application))->assertSessionHasNoErrors();

    expect($application->fresh()->status)->toBe(MarriageStatus::Cleared);
});

test('an open objection stops the couple being cleared', function () {
    $application = MarriageApplication::factory()->withBanns()->create();
    BannsObjection::factory()->for($application, 'application')->create();

    $this->actingAs(marriageOfficer())
        ->post(route('admin.marriages.clear', $application))
        ->assertSessionHasErrors(['stage' => 'Resolve the objection before clearing the couple.']);
});

test('the couple is cleared only after the third banns', function () {
    $application = MarriageApplication::factory()->withBanns(2)->create();

    $this->actingAs(marriageOfficer())
        ->post(route('admin.marriages.clear', $application))
        ->assertSessionHasErrors(['stage' => 'Publish the banns 3 times before clearing the couple.']);

    expect($application->fresh()->status)->toBe(MarriageStatus::Banns);
});

test('objections are only taken once the documents are verified', function () {
    $this->actingAs(marriageOfficer())
        ->post(route('admin.marriages.objections.store', MarriageApplication::factory()->create()), [
            'raised_by' => 'Mrs. Grace Nnaji',
            'raised_on' => now()->toDateString(),
            'details' => 'Says the groom is already married.',
        ])
        ->assertSessionHasErrors(['stage' => 'Objections are recorded once the documents are verified and until the wedding.']);
});

test('an objection belongs to its own application', function () {
    $objection = BannsObjection::factory()->create();
    $other = MarriageApplication::factory()->withBanns(1)->create();

    $this->actingAs(marriageOfficer())
        ->patch(route('admin.marriages.objections.update', [$other, $objection]), ['resolution' => 'Resolved.'])
        ->assertNotFound();

    expect($objection->fresh()->isOpen())->toBeTrue();
});

test('recording the wedding writes it in the marriage register', function () {
    $this->travelTo('2026-11-01');
    $groom = Member::factory()->create(['gender' => Gender::Male, 'marital_status' => MaritalStatus::Single]);
    $bride = Member::factory()->create(['gender' => Gender::Female, 'marital_status' => MaritalStatus::Single]);
    $application = MarriageApplication::factory()->forCouple($groom, $bride)->cleared()->create();
    $user = marriageOfficer(Permission::SacramentsCreate);

    $this->actingAs($user)
        ->post(route('admin.marriages.wedding.store', $application), [
            'received_on' => '2026-10-31',
            'minister' => 'Rev. Fr. Michael Eze',
            'register_book' => '2',
            'register_page' => '41',
            'entry_no' => '815',
            'witnesses' => [['member_id' => null, 'name' => '  Paul  Nnaji ']],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $record = SacramentRecord::query()->sole();
    $application->refresh();

    expect($record->type)->toBe(SacramentType::Matrimony)
        ->and($record->member_id)->toBe($groom->id)
        ->and($record->spouse_member_id)->toBe($bride->id)
        ->and($record->spouse_name)->toBe($bride->full_name)
        ->and($record->received_on->toDateString())->toBe('2026-10-31')
        ->and($record->recorded_by)->toBe($user->id)
        ->and($record->sponsors->pluck('name')->all())->toBe(['Paul Nnaji'])
        ->and($application->status)->toBe(MarriageStatus::Married)
        ->and($application->matrimony_record_id)->toBe($record->id)
        ->and($groom->fresh()->marital_status)->toBe(MaritalStatus::Married)
        ->and($bride->fresh()->wedding_date->toDateString())->toBe('2026-10-31');
});

test('the wedding is recorded only for a cleared couple', function () {
    $application = MarriageApplication::factory()->withBanns()->create();

    $this->actingAs(marriageOfficer(Permission::SacramentsCreate))
        ->post(route('admin.marriages.wedding.store', $application), ['received_on' => now()->toDateString()])
        ->assertSessionHasErrors(['stage' => 'The couple must be cleared to marry before the wedding is recorded.']);

    expect(SacramentRecord::query()->count())->toBe(0);
});

test('the wedding cannot be dated before the last banns', function () {
    $this->travelTo('2026-11-01');
    $application = MarriageApplication::factory()->cleared()->create();

    $this->actingAs(marriageOfficer(Permission::SacramentsCreate))
        ->post(route('admin.marriages.wedding.store', $application), ['received_on' => '2026-10-24'])
        ->assertSessionHasErrors(['received_on' => 'The wedding cannot be before the last publication of the banns.']);
});

test('the couple cannot be witnesses at their own wedding', function () {
    $groom = Member::factory()->create(['gender' => Gender::Male]);
    $application = MarriageApplication::factory()->forCouple($groom)->cleared()->create();

    $this->actingAs(marriageOfficer(Permission::SacramentsCreate))
        ->post(route('admin.marriages.wedding.store', $application), [
            'received_on' => now()->toDateString(),
            'witnesses' => [['member_id' => $groom->id, 'name' => $groom->full_name]],
        ])
        ->assertSessionHasErrors(['witnesses' => 'The groom and the bride cannot be witnesses at their own wedding.']);
});

test('recording the wedding needs the right to record sacraments', function () {
    $application = MarriageApplication::factory()->cleared()->create();
    $user = marriageOfficer();

    $this->actingAs($user)
        ->get(route('admin.marriages.show', $application))
        ->assertInertia(fn (Assert $page) => $page->where('can.update', true)->where('can.recordWedding', false));

    $this->actingAs($user)
        ->post(route('admin.marriages.wedding.store', $application), ['received_on' => now()->toDateString()])
        ->assertForbidden();
});

test('a married application can no longer be changed or removed', function () {
    $application = MarriageApplication::factory()->cleared()->create(['status' => MarriageStatus::Married]);
    $user = marriageOfficer(Permission::MarriagesDelete);

    $this->actingAs($user)->put(route('admin.marriages.update', $application), marriageApplication())->assertForbidden();
    $this->actingAs($user)->post(route('admin.marriages.withdraw', $application), ['reason' => 'Changed their minds.'])->assertForbidden();
    $this->actingAs($user)->delete(route('admin.marriages.destroy', $application))->assertForbidden();
});

test('withdrawing an application closes it with the reason', function () {
    $application = MarriageApplication::factory()->documentsVerified()->create();
    $user = marriageOfficer();

    $this->actingAs($user)
        ->post(route('admin.marriages.withdraw', $application), ['reason' => ''])
        ->assertSessionHasErrors(['reason' => 'The reason field is required.']);

    $this->actingAs($user)
        ->post(route('admin.marriages.withdraw', $application), ['reason' => 'They will marry in the bride\'s parish.'])
        ->assertSessionHasNoErrors();

    expect($application->fresh())
        ->status->toBe(MarriageStatus::Withdrawn)
        ->withdrawal_reason->toBe('They will marry in the bride\'s parish.')
        ->withdrawn_at->not->toBeNull();
});

test('removing an application keeps it in the database', function () {
    $application = MarriageApplication::factory()->create();

    $this->actingAs(User::factory()->withPermissions(Permission::MarriagesView, Permission::MarriagesDelete)->create())
        ->delete(route('admin.marriages.destroy', $application))
        ->assertRedirect(route('admin.marriages.index'));

    expect($application->fresh()->trashed())->toBeTrue();
});

test('the list shows open applications and counts each stage', function () {
    MarriageApplication::factory()->create();
    MarriageApplication::factory()->withBanns(1)->create();
    MarriageApplication::factory()->create(['status' => MarriageStatus::Withdrawn]);

    $this->actingAs(marriageOfficer())
        ->get(route('admin.marriages.index', ['filter' => ['status' => 'open']]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/marriages/Index')
            ->has('applications.data', 2)
            ->where('counts.applied', 1)
            ->where('counts.banns', 1)
            ->where('counts.withdrawn', 1));
});

test('viewing marriage applications does not allow changing them', function () {
    $application = MarriageApplication::factory()->documentsVerified()->create();
    $viewer = User::factory()->withPermissions(Permission::MarriagesView)->create();

    $this->actingAs($viewer)->get(route('admin.marriages.show', $application))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/marriages/Show')
            ->where('application.id', $application->id)
            ->where('application.steps.publishBanns', true)
            ->where('can.update', false)
            ->where('can.delete', false));
    $this->actingAs($viewer)
        ->post(route('admin.marriages.banns.store', $application), ['published_on' => now()->toDateString()])
        ->assertForbidden();
});
