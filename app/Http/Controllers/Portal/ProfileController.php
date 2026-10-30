<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Members\SubmitProfileChange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ProfileChangeRequestRequest;
use App\Models\Member;
use App\Models\ProfileChangeRequest;
use App\Support\People\MemberPresenter;
use App\Support\People\PeopleOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the member their parish record and any pending changes.
     */
    public function show(Request $request): Response
    {
        $member = $this->member($request);
        $member->load(['community:id,name', 'family:id,name']);

        $pending = $member->changeRequests()->pending()->first();
        $latest = $member->changeRequests()->whereNot('status', 'pending')->latest('reviewed_at')->first();

        return Inertia::render('portal/Profile', [
            'member' => [
                ...MemberPresenter::row($member),
                'values' => ProfileChangeRequest::currentValues($member),
                'genderLabel' => $member->gender->label(),
                'maritalStatus' => $member->marital_status?->label(),
                'familyRoleLabel' => $member->family_role?->label(),
                'joinedAt' => $member->joined_at?->toDateString(),
            ],
            'pending' => $pending !== null ? [
                'id' => $pending->id,
                'changes' => $pending->changes,
                'note' => $pending->note,
                'createdAt' => $pending->created_at?->toIso8601String(),
            ] : null,
            'lastReview' => $latest !== null ? [
                'status' => $latest->status->value,
                'reviewNote' => $latest->review_note,
                'reviewedAt' => $latest->reviewed_at?->toIso8601String(),
            ] : null,
            'maritalStatuses' => PeopleOptions::enums()['maritalStatuses'],
        ]);
    }

    /**
     * Send changes to the parish office for review.
     */
    public function store(ProfileChangeRequestRequest $request, SubmitProfileChange $submit): RedirectResponse
    {
        $note = $request->filled('note') ? $request->string('note')->value() : null;

        $submit->handle($request->user(), $this->member($request), $request->values(), $note);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you. The parish office will review your changes.')]);

        return to_route('portal.profile.show');
    }

    /**
     * Withdraw a pending change request.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->member($request)->changeRequests()->pending()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your change request was withdrawn.')]);

        return to_route('portal.profile.show');
    }

    /**
     * Get the member linked to the signed-in account.
     */
    private function member(Request $request): Member
    {
        return $request->user()->member ?? abort(403);
    }
}
