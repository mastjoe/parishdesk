<?php

namespace App\Actions\Access;

use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\StaffInvitation;
use App\Support\Permissions\GrantGuard;
use Illuminate\Support\Facades\Notification;

/**
 * Invites a new staff user with the roles and permissions they will receive.
 */
class InviteUser
{
    public function __construct(
        private GrantGuard $guard,
    ) {}

    /**
     * Create the invitation and email the signed link.
     *
     * @param  array{name: string, email: string, roles?: list<string>, permissions?: list<string>}  $data
     */
    public function handle(User $actor, array $data): UserInvitation
    {
        $roles = $data['roles'] ?? [];
        $permissions = $data['permissions'] ?? [];

        $this->guard->ensureCanGrantRoles($actor, $roles);
        $this->guard->ensureCanGrantPermissions($actor, $permissions);

        UserInvitation::query()->pending()->where('email', $data['email'])->update(['revoked_at' => now()]);

        $invitation = UserInvitation::query()->create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'roles' => $roles,
            'permissions' => $permissions,
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(UserInvitation::VALID_FOR_DAYS),
        ]);

        $this->send($invitation);

        activity('access')
            ->performedOn($invitation)
            ->causedBy($actor)
            ->event('invited')
            ->withProperties(['email' => $invitation->email, 'roles' => $roles, 'permissions' => $permissions])
            ->log("Invited {$invitation->email}");

        return $invitation;
    }

    /**
     * Email the invitation link, extending its validity.
     */
    public function send(UserInvitation $invitation): void
    {
        if ($invitation->expires_at->isPast()) {
            $invitation->update(['expires_at' => now()->addDays(UserInvitation::VALID_FOR_DAYS)]);
        }

        Notification::route('mail', $invitation->email)->notify(new StaffInvitation($invitation));
    }
}
