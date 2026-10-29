<?php

namespace App\Http\Controllers\Admin\Access;

use App\Actions\Access\InviteUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Access\InviteUserRequest;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class InvitationController extends Controller
{
    /**
     * Invite a new staff user.
     */
    public function store(InviteUserRequest $request, InviteUser $inviteUser): RedirectResponse
    {
        $invitation = $inviteUser->handle($request->user(), $request->invitationData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $invitation->email])]);

        return back();
    }

    /**
     * Email the invitation link again.
     */
    public function resend(UserInvitation $invitation, InviteUser $inviteUser): RedirectResponse
    {
        Gate::authorize('invite', User::class);

        abort_if($invitation->accepted_at !== null || $invitation->revoked_at !== null, 409, __('This invitation can no longer be sent.'));

        $inviteUser->send($invitation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation resent to :email.', ['email' => $invitation->email])]);

        return back();
    }

    /**
     * Revoke a pending invitation.
     */
    public function destroy(Request $request, UserInvitation $invitation): RedirectResponse
    {
        Gate::authorize('invite', User::class);

        if ($invitation->accepted_at === null) {
            $invitation->update(['revoked_at' => now()]);

            activity('access')->performedOn($invitation)->causedBy($request->user())->event('revoked')
                ->log("Revoked the invitation for {$invitation->email}");
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation revoked.')]);

        return back();
    }
}
