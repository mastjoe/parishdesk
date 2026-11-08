<?php

namespace App\Http\Controllers\Admin\Marriages;

use App\Enums\MarriageCheck;
use App\Enums\MarriageStatus;
use App\Http\Controllers\Controller;
use App\Models\MarriageApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Moves a marriage application through its checks, its clearance, or its
 * withdrawal. The banns and the wedding have their own controllers.
 */
class MarriageStageController extends Controller
{
    /**
     * Tick off the documents and steps that are done.
     */
    public function checks(Request $request, MarriageApplication $marriage): RedirectResponse
    {
        Gate::authorize('update', $marriage);

        $validated = $request->validate([
            'checks' => ['present', 'array'],
            'checks.*' => ['distinct', Rule::enum(MarriageCheck::class)],
        ]);

        $this->ensure($marriage->canChangeChecks(), __('The checklist is fixed once the documents are verified.'));

        $marriage->checks = array_values(array_intersect(MarriageCheck::values(), $validated['checks']));
        $marriage->save();

        return back();
    }

    /**
     * Confirm every document is in order, so the banns can be published.
     */
    public function verify(MarriageApplication $marriage): RedirectResponse
    {
        Gate::authorize('update', $marriage);

        $this->ensure($marriage->canVerifyDocuments(), __('Complete every check before verifying the documents.'));

        $marriage->status = MarriageStatus::DocumentsVerified;
        $marriage->documents_verified_at = now();
        $marriage->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Documents verified. The banns can now be published.')]);

        return back();
    }

    /**
     * Clear the couple to marry once the banns are complete and unopposed.
     */
    public function clear(MarriageApplication $marriage): RedirectResponse
    {
        Gate::authorize('update', $marriage);

        $this->ensure($marriage->canBeCleared(), $marriage->hasOpenObjection()
            ? __('Resolve the objection before clearing the couple.')
            : __('Publish the banns :count times before clearing the couple.', ['count' => MarriageApplication::BANNS_REQUIRED]));

        $marriage->status = MarriageStatus::Cleared;
        $marriage->cleared_at = now();
        $marriage->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':couple are cleared to marry.', ['couple' => $marriage->coupleName()])]);

        return back();
    }

    /**
     * Close the application because the couple will not marry here.
     */
    public function withdraw(Request $request, MarriageApplication $marriage): RedirectResponse
    {
        Gate::authorize('update', $marriage);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $marriage->status = MarriageStatus::Withdrawn;
        $marriage->withdrawn_at = now();
        $marriage->withdrawal_reason = $validated['reason'];
        $marriage->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marriage application :number withdrawn.', ['number' => $marriage->application_no])]);

        return back();
    }

    /**
     * Refuse the step when the application is not ready for it.
     */
    private function ensure(bool $ready, string $message): void
    {
        if (! $ready) {
            throw ValidationException::withMessages(['stage' => $message]);
        }
    }
}
