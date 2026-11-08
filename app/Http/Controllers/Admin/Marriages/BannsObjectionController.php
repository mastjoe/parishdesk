<?php

namespace App\Http\Controllers\Admin\Marriages;

use App\Http\Controllers\Controller;
use App\Models\BannsObjection;
use App\Models\MarriageApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BannsObjectionController extends Controller
{
    /**
     * Record an impediment someone made known. It holds up the banns and
     * the wedding until it is resolved.
     */
    public function store(Request $request, MarriageApplication $marriage): RedirectResponse
    {
        Gate::authorize('update', $marriage);

        $validated = $request->validate([
            'raised_by' => ['required', 'string', 'max:255'],
            'raised_on' => ['required', 'date', 'before_or_equal:today'],
            'details' => ['required', 'string', 'max:5000'],
        ]);

        if (! $marriage->canReceiveObjections()) {
            throw ValidationException::withMessages(['stage' => __('Objections are recorded once the documents are verified and until the wedding.')]);
        }

        $marriage->objections()->create([...$validated, 'recorded_by' => $request->user()->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Objection recorded. The marriage is on hold until it is resolved.')]);

        return back();
    }

    /**
     * Record how an objection was resolved.
     */
    public function update(Request $request, MarriageApplication $marriage, BannsObjection $objection): RedirectResponse
    {
        Gate::authorize('update', $marriage);

        $validated = $request->validate(['resolution' => ['required', 'string', 'max:5000']]);

        if (! $objection->isOpen()) {
            throw ValidationException::withMessages(['resolution' => __('This objection is already resolved.')]);
        }

        $objection->forceFill([
            'resolution' => $validated['resolution'],
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Objection resolved.')]);

        return back();
    }
}
