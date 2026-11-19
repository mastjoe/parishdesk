<?php

namespace App\Actions\Marriages;

use App\Enums\BannsLetter;
use App\Models\MarriageApplication;
use App\Support\Pdf\Letterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a letter about a couple's banns to another parish as a PDF.
 */
class RenderBannsLetter
{
    /**
     * Render the letter and stream it to the browser.
     */
    public function handle(MarriageApplication $application, BannsLetter $letter): Response
    {
        $application->load('publications');

        $pdf = Pdf::loadView('pdf.marriages.banns-letter', [
            ...Letterhead::data(),
            'letter' => $letter,
            'application' => $application,
            'addressee' => $letter->addressee($application),
        ])->setPaper('a4');

        return $pdf->stream(str_replace('/', '-', $application->application_no)."-{$letter->value}.pdf");
    }
}
