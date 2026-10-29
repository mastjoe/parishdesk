<?php

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\StaffInvitation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('public registration is disabled', function () {
    $this->get('/register')->assertNotFound();
});

test('a staff user can be invited with roles and permissions', function () {
    Notification::fake();
    Role::create(['name' => 'Secretary']);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('admin.invitations.store'), [
            'name' => 'Grace Okafor',
            'email' => 'grace@example.com',
            'roles' => ['Secretary'],
            'permissions' => [Permission::ActivityView->value],
        ])
        ->assertSessionHasNoErrors();

    $invitation = UserInvitation::query()->sole();

    expect($invitation->roles)->toBe(['Secretary'])
        ->and($invitation->permissions)->toBe([Permission::ActivityView->value])
        ->and($invitation->isPending())->toBeTrue();

    Notification::assertSentOnDemand(
        StaffInvitation::class,
        fn (StaffInvitation $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'grace@example.com',
    );
});

test('inviting an existing user is rejected', function () {
    $existing = User::factory()->create();

    $this->actingAs(User::factory()->withPermissions(Permission::UsersInvite)->create())
        ->post(route('admin.invitations.store'), ['name' => 'Again', 'email' => $existing->email])
        ->assertSessionHasErrors(['email' => 'A user with this email already exists.']);
});

test('an invitation cannot carry more access than the inviter holds', function () {
    Notification::fake();

    $this->actingAs(User::factory()->withPermissions(Permission::UsersInvite)->create())
        ->post(route('admin.invitations.store'), [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'permissions' => [Permission::RolesDelete->value],
        ])
        ->assertSessionHasErrors('permissions');

    expect(UserInvitation::query()->exists())->toBeFalse();
    Notification::assertNothingSent();
});

test('re-inviting an email revokes the earlier pending invitation', function () {
    Notification::fake();
    $earlier = UserInvitation::factory()->create(['email' => 'grace@example.com']);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.invitations.store'), ['name' => 'Grace', 'email' => 'grace@example.com']);

    expect($earlier->fresh()->revoked_at)->not->toBeNull()
        ->and(UserInvitation::query()->pending()->count())->toBe(1);
});

test('accepting an invitation creates the user with the invited access', function () {
    Role::create(['name' => 'Secretary']);
    $invitation = UserInvitation::factory()->create([
        'email' => 'grace@example.com',
        'roles' => ['Secretary', 'Deleted Role'],
        'permissions' => [Permission::ActivityView->value],
    ]);

    $this->get($invitation->acceptUrl())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AcceptInvitation')
            ->where('invitation.email', 'grace@example.com'));

    $this->post($invitation->acceptUrl(), [
        'password' => 'a-strong-password',
        'password_confirmation' => 'a-strong-password',
    ])->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'grace@example.com')->sole();

    $this->assertAuthenticatedAs($user);
    expect($user->getRoleNames()->all())->toBe(['Secretary'])
        ->and($user->hasDirectPermission(Permission::ActivityView))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('an invitation link only works once', function () {
    $invitation = UserInvitation::factory()->create();
    $payload = ['password' => 'a-strong-password', 'password_confirmation' => 'a-strong-password'];

    $this->post($invitation->acceptUrl(), $payload);
    auth()->logout();

    $this->post($invitation->acceptUrl(), $payload)->assertStatus(410);
    expect(User::query()->where('email', $invitation->email)->count())->toBe(1);
});

test('unusable invitation links are refused', function (Closure $makeUrl, int $status) {
    $invitation = UserInvitation::factory()->create();

    $this->get($makeUrl($invitation))->assertStatus($status);
})->with([
    'unsigned' => [fn (UserInvitation $invitation) => route('invitations.show', $invitation), 403],
    'tampered' => [fn (UserInvitation $invitation) => $invitation->acceptUrl().'x', 403],
    'revoked' => [function (UserInvitation $invitation) {
        $invitation->update(['revoked_at' => now()]);

        return $invitation->acceptUrl();
    }, 410],
]);

test('an expired invitation link is refused', function () {
    $invitation = UserInvitation::factory()->create();
    $url = $invitation->acceptUrl();

    $this->travel(UserInvitation::VALID_FOR_DAYS + 1)->days();

    $this->get($url)->assertForbidden();
});

test('resending an expired invitation extends it', function () {
    Notification::fake();
    $invitation = UserInvitation::factory()->expired()->create();

    $this->actingAs(User::factory()->withPermissions(Permission::UsersInvite)->create())
        ->post(route('admin.invitations.resend', $invitation))
        ->assertRedirect();

    expect($invitation->fresh()->isPending())->toBeTrue();
    Notification::assertSentOnDemandTimes(StaffInvitation::class, 1);
});

test('an invitation can be revoked', function () {
    $invitation = UserInvitation::factory()->create();

    $this->actingAs(User::factory()->withPermissions(Permission::UsersInvite)->create())
        ->delete(route('admin.invitations.destroy', $invitation));

    expect($invitation->fresh()->status())->toBe('revoked');
});

test('the invitation email escapes the invitee name', function () {
    $invitation = UserInvitation::factory()->create(['name' => '<script>alert(1)</script>']);

    $html = (string) (new StaffInvitation($invitation))->toMail($invitation)->render();

    expect($html)->toContain('&lt;script&gt;')->not->toContain('<script>alert(1)</script>');
});
