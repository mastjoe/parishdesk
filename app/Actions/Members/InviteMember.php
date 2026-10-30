<?php

namespace App\Actions\Members;

use App\Actions\Access\InviteUser;
use App\Models\Member;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Validation\ValidationException;

/**
 * Gives a member access to the portal.
 *
 * When an account already exists for the email address, for example a staff
 * user who is also a parishioner, the member is linked to it straight away.
 * Otherwise the member is emailed a link to set their password.
 */
class InviteMember
{
    public function __construct(
        private InviteUser $inviteUser,
    ) {}

    /**
     * Link or invite the member, returning the invitation when one was sent.
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Member $member, string $email): ?UserInvitation
    {
        $email = strtolower($email);
        $account = User::query()->with('member')->where('email', $email)->first();

        if ($account !== null) {
            if ($account->member !== null) {
                throw ValidationException::withMessages([
                    'email' => __('This account is already linked to :name.', ['name' => $account->member->full_name]),
                ]);
            }

            $member->forceFill(['user_id' => $account->id])->save();

            activity('people')->performedOn($member)->causedBy($actor)->event('account_linked')
                ->withProperties(['email' => $email])
                ->log("Linked {$member->full_name} to the existing account {$email}");

            return null;
        }

        UserInvitation::query()->pending()
            ->where(fn ($query) => $query->where('member_id', $member->id)->orWhere('email', $email))
            ->update(['revoked_at' => now()]);

        $invitation = UserInvitation::query()->create([
            'name' => $member->full_name,
            'email' => $email,
            'roles' => [],
            'permissions' => [],
            'member_id' => $member->id,
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(UserInvitation::VALID_FOR_DAYS),
        ]);

        if ($member->email === null) {
            $member->update(['email' => $email]);
        }

        $this->inviteUser->send($invitation);

        activity('people')->performedOn($member)->causedBy($actor)->event('invited')
            ->withProperties(['email' => $email])
            ->log("Invited {$member->full_name} to the member portal");

        return $invitation;
    }
}
