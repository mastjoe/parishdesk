<?php

namespace App\Http\Controllers\Admin\Marriages;

use App\Actions\Marriages\RenderBannsLetter;
use App\Enums\BannsLetter;
use App\Http\Controllers\Controller;
use App\Models\MarriageApplication;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class BannsLetterController extends Controller
{
    /**
     * Stream a letter about the couple's banns to another parish as a PDF.
     */
    public function show(MarriageApplication $marriage, BannsLetter $letter, RenderBannsLetter $render): Response
    {
        Gate::authorize('view', $marriage);

        abort_unless($letter->isAvailableFor($marriage), 404);

        return $render->handle($marriage, $letter);
    }
}
