<?php

namespace App\Http\Controllers\Admin\Marriages;

use App\Actions\Marriages\MoveMarriageApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Marriages\UpdateMarriageChecksRequest;
use App\Http\Requests\Admin\Marriages\WithdrawMarriageApplicationRequest;
use App\Models\MarriageApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
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
    public function checks(UpdateMarriageChecksRequest $request, MarriageApplication $marriage, MoveMarriageApplication $move): RedirectResponse
    {
        $move->updateChecks($marriage, $request->validated('checks'));

        return back();
    }

    /**
     * Confirm every document is in order, so the banns can be published.
     */
    public function verify(MarriageApplication $marriage, MoveMarriageApplication $move): RedirectResponse
    {
        Gate::authorize('update', $marriage);

        $move->verifyDocuments($marriage);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Documents verified. The banns can now be published.')]);

        return back();
    }

    /**
     * Clear the couple to marry once the banns are complete and unopposed.
     */
    public function clear(MarriageApplication $marriage, MoveMarriageApplication $move): RedirectResponse
    {
        Gate::authorize('update', $marriage);

        $move->clear($marriage);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':couple are cleared to marry.', ['couple' => $marriage->coupleName()])]);

        return back();
    }

    /**
     * Close the application because the couple will not marry here.
     */
    public function withdraw(WithdrawMarriageApplicationRequest $request, MarriageApplication $marriage, MoveMarriageApplication $move): RedirectResponse
    {
        $move->withdraw($marriage, $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marriage application :number withdrawn.', ['number' => $marriage->application_no])]);

        return back();
    }
}
