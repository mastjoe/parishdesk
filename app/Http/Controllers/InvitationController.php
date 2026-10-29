<?php

namespace App\Http\Controllers;

use App\Actions\Access\AcceptInvitation;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\ParishSetting;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /**
     * Show the form where the invitee sets their password.
     */
    public function show(UserInvitation $invitation): Response
    {
        abort_unless($invitation->isPending(), 410, __('This invitation is no longer valid.'));

        return Inertia::render('auth/AcceptInvitation', [
            'invitation' => [
                'name' => $invitation->name,
                'email' => $invitation->email,
            ],
            'parishName' => ParishSetting::current()->name,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Create the account and sign the new user in.
     */
    public function store(AcceptInvitationRequest $request, UserInvitation $invitation, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        abort_unless($invitation->isPending(), 410, __('This invitation is no longer valid.'));

        $user = $acceptInvitation->handle($invitation, $request->string('password')->value());

        Auth::login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to :parish!', ['parish' => ParishSetting::current()->name])]);

        return to_route('dashboard');
    }
}
