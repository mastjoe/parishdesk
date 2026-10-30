<?php

use App\Enums\ChangeRequestStatus;
use App\Enums\MaritalStatus;
use App\Enums\Permission;
use App\Models\Member;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->member = Member::factory()->withAccount()->create([
        'first_name' => 'Grace', 'last_name' => 'Okafor', 'phone' => '08010000000',
        'occupation' => 'Teacher', 'date_of_birth' => '1990-03-12', 'marital_status' => MaritalStatus::Single,
    ]);
    $this->account = $this->member->user;
});

/**
 * Everything the portal form sends, with the given changes.
 *
 * @param  array<string, string|null>  $changes
 * @return array<string, string|null>
 */
function profileForm(Member $member, array $changes = []): array
{
    return [...ProfileChangeRequest::currentValues($member), ...$changes];
}

test('a member sends only the details that changed', function () {
    $this->actingAs($this->account)
        ->post(route('portal.profile.changes.store'), [
            ...profileForm($this->member, ['phone' => '08031234567', 'marital_status' => 'married', 'wedding_date' => '2026-06-20']),
            'note' => 'We got married in June.',
        ])
        ->assertRedirect(route('portal.profile.show'));

    $request = ProfileChangeRequest::query()->sole();

    expect($request->changes)->toBe(['marital_status' => 'married', 'wedding_date' => '2026-06-20', 'phone' => '08031234567'])
        ->and($request->original)->toBe(['marital_status' => 'single', 'wedding_date' => null, 'phone' => '08010000000'])
        ->and($request->note)->toBe('We got married in June.')
        ->and($this->member->refresh()->phone)->toBe('08010000000');
});

test('sending nothing new is refused', function () {
    $this->actingAs($this->account)
        ->post(route('portal.profile.changes.store'), profileForm($this->member))
        ->assertSessionHasErrors('changes');

    expect(ProfileChangeRequest::query()->count())->toBe(0);
});

test('fields members may not change are ignored', function () {
    $this->actingAs($this->account)->post(route('portal.profile.changes.store'), [
        ...profileForm($this->member, ['occupation' => 'Nurse']),
        'status' => 'deceased',
        'member_no' => 'HACKED',
    ]);

    expect(ProfileChangeRequest::query()->sole()->changes)->toBe(['occupation' => 'Nurse']);
});

test('sending again replaces the pending request, and it can be withdrawn', function () {
    $this->actingAs($this->account)->post(route('portal.profile.changes.store'), profileForm($this->member, ['occupation' => 'Nurse']));
    $this->actingAs($this->account)->post(route('portal.profile.changes.store'), profileForm($this->member, ['occupation' => 'Doctor']));

    expect(ProfileChangeRequest::query()->sole()->changes)->toBe(['occupation' => 'Doctor']);

    $this->actingAs($this->account)->delete(route('portal.profile.changes.destroy'));

    expect(ProfileChangeRequest::query()->count())->toBe(0);
});

test('accounts without a member record cannot open member pages', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('portal.profile.show'))
        ->assertRedirect(route('portal.dashboard'));
});

test('approving applies the changes to the register', function () {
    $request = ProfileChangeRequest::factory()->for($this->member)->create([
        'changes' => ['phone' => '08031234567', 'marital_status' => 'married'],
        'original' => ['phone' => '08010000000', 'marital_status' => 'single'],
    ]);
    $reviewer = User::factory()->withPermissions(Permission::MembersChangeRequestsApprove)->create();

    $this->actingAs($reviewer)
        ->patch(route('admin.change-requests.update', $request), ['decision' => 'approve'])
        ->assertSessionHasNoErrors();

    $this->member->refresh();

    expect($this->member->phone)->toBe('08031234567')
        ->and($this->member->marital_status)->toBe(MaritalStatus::Married)
        ->and($request->refresh()->status)->toBe(ChangeRequestStatus::Approved)
        ->and($request->reviewed_by)->toBe($reviewer->id);
});

test('rejecting needs a reason and leaves the register alone', function () {
    $request = ProfileChangeRequest::factory()->for($this->member)->create(['changes' => ['phone' => '08031234567']]);
    $reviewer = User::factory()->withPermissions(Permission::MembersChangeRequestsApprove)->create();

    $this->actingAs($reviewer)
        ->patch(route('admin.change-requests.update', $request), ['decision' => 'reject'])
        ->assertSessionHasErrors('review_note');

    $this->actingAs($reviewer)
        ->patch(route('admin.change-requests.update', $request), ['decision' => 'reject', 'review_note' => 'Please call the office.'])
        ->assertSessionHasNoErrors();

    expect($request->refresh()->status)->toBe(ChangeRequestStatus::Rejected)
        ->and($this->member->refresh()->phone)->toBe('08010000000');

    $this->actingAs($this->account)->get(route('portal.profile.show'))
        ->assertInertia(fn (Assert $page) => $page->where('pending', null)->where('lastReview.reviewNote', 'Please call the office.'));
});

test('a reviewed request cannot be reviewed again', function () {
    $request = ProfileChangeRequest::factory()->for($this->member)->create(['status' => ChangeRequestStatus::Rejected]);
    $reviewer = User::factory()->withPermissions(Permission::MembersChangeRequestsApprove)->create();

    $this->actingAs($reviewer)
        ->patch(route('admin.change-requests.update', $request), ['decision' => 'approve'])
        ->assertForbidden();
});

test('nobody approves changes to their own member record, not even a super admin', function () {
    $admin = User::factory()->superAdmin()->create();
    $ownMember = Member::factory()->withAccount($admin)->create();
    $request = ProfileChangeRequest::factory()->for($ownMember)->create();

    $this->actingAs($admin)
        ->patch(route('admin.change-requests.update', $request), ['decision' => 'approve'])
        ->assertForbidden();
});

test('the review queue lists waiting requests with the register values beside them', function () {
    ProfileChangeRequest::factory()->for($this->member)->create(['changes' => ['phone' => '0809'], 'original' => ['phone' => '0801']]);
    ProfileChangeRequest::factory()->create(['status' => ChangeRequestStatus::Approved]);
    $reviewer = User::factory()->withPermissions(Permission::MembersChangeRequestsApprove)->create();

    $this->actingAs($reviewer)->get(route('admin.change-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('requests.data', 1)
            ->where('requests.data.0.current.phone', '08010000000')
            ->where('requests.data.0.canReview', true)
            ->where('pendingCount', 1));
});
