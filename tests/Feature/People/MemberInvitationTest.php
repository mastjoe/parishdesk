<?php

use App\Enums\Permission;
use App\Models\Member;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\MemberInvitation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('a member can be invited to the portal and accepting links their account', function () {
    Notification::fake();
    $member = Member::factory()->create(['first_name' => 'Grace', 'last_name' => 'Okafor', 'email' => null]);
    $clerk = User::factory()->withPermissions(Permission::MembersInvite)->create();

    $this->actingAs($clerk)
        ->post(route('admin.members.invitation.store', $member), ['email' => 'Grace@Example.com'])
        ->assertSessionHasNoErrors();

    $invitation = UserInvitation::query()->sole();

    expect($invitation->member_id)->toBe($member->id)
        ->and($invitation->roles)->toBe([])
        ->and($member->refresh()->email)->toBe('grace@example.com');

    Notification::assertSentOnDemand(
        MemberInvitation::class,
        fn (MemberInvitation $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'grace@example.com',
    );

    auth()->logout();

    $this->post($invitation->acceptUrl(), ['password' => 'secret-password', 'password_confirmation' => 'secret-password'])
        ->assertRedirect(route('dashboard'));

    $account = User::query()->where('email', 'grace@example.com')->sole();

    expect($member->refresh()->user_id)->toBe($account->id)
        ->and($account->canAccessAdmin())->toBeFalse();

    $this->actingAs($account)->get(route('portal.profile.show'))->assertOk();
});

test('inviting again replaces the earlier invitation', function () {
    Notification::fake();
    $member = Member::factory()->create();
    $clerk = User::factory()->withPermissions(Permission::MembersInvite)->create();

    $this->actingAs($clerk)->post(route('admin.members.invitation.store', $member), ['email' => 'first@example.com']);
    $this->actingAs($clerk)->post(route('admin.members.invitation.store', $member), ['email' => 'second@example.com']);

    expect(UserInvitation::query()->pending()->pluck('email')->all())->toBe(['second@example.com']);
});

test('an existing account with the same email is linked without an invitation', function () {
    Notification::fake();
    $staff = User::factory()->create(['email' => 'office@example.com']);
    $member = Member::factory()->create();
    $clerk = User::factory()->withPermissions(Permission::MembersInvite)->create();

    $this->actingAs($clerk)->post(route('admin.members.invitation.store', $member), ['email' => 'office@example.com']);

    expect($member->refresh()->user_id)->toBe($staff->id)
        ->and(UserInvitation::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('an account already linked to another member cannot be linked again', function () {
    $taken = Member::factory()->withAccount()->create();
    $member = Member::factory()->create();
    $clerk = User::factory()->withPermissions(Permission::MembersInvite)->create();

    $this->actingAs($clerk)
        ->post(route('admin.members.invitation.store', $member), ['email' => $taken->user->email])
        ->assertSessionHasErrors('email');

    expect($member->refresh()->user_id)->toBeNull();
});

test('members who already use the portal cannot be invited', function () {
    $member = Member::factory()->withAccount()->create();
    $clerk = User::factory()->withPermissions(Permission::MembersInvite)->create();

    $this->actingAs($clerk)
        ->post(route('admin.members.invitation.store', $member), ['email' => 'new@example.com'])
        ->assertForbidden();
});

test('member invitations are not listed with staff invitations', function () {
    UserInvitation::factory()->create(['email' => 'staff@example.com']);
    UserInvitation::factory()->create(['email' => 'member@example.com', 'member_id' => Member::factory()->create()->id]);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->has('invitations', 1)->where('invitations.0.email', 'staff@example.com'));
});
