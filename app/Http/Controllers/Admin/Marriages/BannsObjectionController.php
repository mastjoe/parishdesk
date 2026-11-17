<?php

namespace App\Http\Controllers\Admin\Marriages;

use App\Actions\Marriages\RecordBannsObjection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Marriages\RaiseBannsObjectionRequest;
use App\Http\Requests\Admin\Marriages\ResolveBannsObjectionRequest;
use App\Models\BannsObjection;
use App\Models\MarriageApplication;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BannsObjectionController extends Controller
{
    /**
     * Record an impediment someone made known. It holds up the banns and
     * the wedding until it is resolved.
     */
    public function store(RaiseBannsObjectionRequest $request, MarriageApplication $marriage, RecordBannsObjection $record): RedirectResponse
    {
        $record->handle($marriage, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Objection recorded. The marriage is on hold until it is resolved.')]);

        return back();
    }

    /**
     * Record how an objection was resolved.
     */
    public function update(ResolveBannsObjectionRequest $request, MarriageApplication $marriage, BannsObjection $objection, RecordBannsObjection $record): RedirectResponse
    {
        $record->resolve($objection, $request->validated('resolution'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Objection resolved.')]);

        return back();
    }
}
