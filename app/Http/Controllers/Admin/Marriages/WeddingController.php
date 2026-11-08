<?php

namespace App\Http\Controllers\Admin\Marriages;

use App\Actions\Marriages\RecordWedding;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Marriages\RecordWeddingRequest;
use App\Models\MarriageApplication;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class WeddingController extends Controller
{
    /**
     * Write the couple's wedding in the marriage register.
     */
    public function store(RecordWeddingRequest $request, MarriageApplication $marriage, RecordWedding $record): RedirectResponse
    {
        $record->handle($marriage, $request->entry(), $request->witnesses(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The wedding of :couple is written in the marriage register.', ['couple' => $marriage->coupleName()])]);

        return back();
    }
}
