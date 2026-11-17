<?php

namespace App\Http\Controllers\Admin\Marriages;

use App\Actions\Marriages\PublishBanns;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Marriages\PublishBannsRequest;
use App\Models\BannsPublication;
use App\Models\MarriageApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class BannsPublicationController extends Controller
{
    /**
     * Record that the banns were announced.
     */
    public function store(PublishBannsRequest $request, MarriageApplication $marriage, PublishBanns $publish): RedirectResponse
    {
        $publication = $publish->handle($marriage, Carbon::parse($request->validated('published_on')), $request->user());

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
