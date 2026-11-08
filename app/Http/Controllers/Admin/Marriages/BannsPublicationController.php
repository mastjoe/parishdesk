<?php

namespace App\Http\Controllers\Admin\Marriages;

use App\Actions\Marriages\PublishBanns;
use App\Http\Controllers\Controller;
use App\Models\BannsPublication;
use App\Models\MarriageApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class BannsPublicationController extends Controller
{
    /**
     * Record that the banns were announced.
     */
    public function store(Request $request, MarriageApplication $marriage, PublishBanns $publish): RedirectResponse
    {
        Gate::authorize('update', $marriage);

        $validated = $request->validate(
            ['published_on' => ['required', 'date', 'before_or_equal:today']],
            ['published_on.before_or_equal' => __('Record the banns once they have been announced.')],
        );

        $publication = $publish->handle($marriage, Carbon::parse($validated['published_on']), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Banns :number of :total recorded.', ['number' => $publication->number, 'total' => MarriageApplication::BANNS_REQUIRED])]);

        return back();
    }

    /**
     * Take back the latest publication, recorded by mistake.
     */
    public function destroy(MarriageApplication $marriage, BannsPublication $publication, PublishBanns $publish): RedirectResponse
    {
        Gate::authorize('update', $marriage);

        $publish->undo($marriage, $publication);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Banns :number taken back.', ['number' => $publication->number])]);

        return back();
    }
}
