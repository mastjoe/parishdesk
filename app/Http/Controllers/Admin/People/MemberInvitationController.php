<?php

namespace App\Http\Controllers\Admin\People;

use App\Actions\Members\InviteMember;
use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MemberInvitationController extends Controller
{
    /**
     * Give the member portal access, by invitation or by linking an existing account.
     */
    public function store(Request $request, Member $member, InviteMember $inviteMember): RedirectResponse
    {
        Gate::authorize('invite', $member);

        $validated = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $invitation = $inviteMember->handle($request->user(), $member, $validated['email']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $invitation !== null
                ? __('Portal invitation sent to :email.', ['email' => $invitation->email])
                : __(':name is now linked to their existing account.', ['name' => $member->full_name]),
        ]);

        return back();
    }
}
