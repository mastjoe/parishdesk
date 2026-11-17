<?php

namespace App\Actions\Marriages;

use App\Enums\BannsLetter;
use App\Enums\ClergyRole;
use App\Models\ClergyAssignment;
use App\Models\MarriageApplication;
use App\Models\ParishSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a letter about a couple's banns to another parish as a PDF.
 */
class RenderBannsLetter
{
    /**
     * The logo types the PDF renderer can draw.
     */
    private const array LOGO_MIME_TYPES = ['image/jpeg', 'image/png'];

    /**
     * Render the letter and stream it to the browser.
     */
    public function handle(MarriageApplication $application, BannsLetter $letter): Response
    {
        $application->load('publications');
        $parish = ParishSetting::current();

        $pdf = Pdf::loadView('pdf.marriages.banns-letter', [
            'letter' => $letter,
            'application' => $application,
            'addressee' => $letter->addressee($application),
            'parish' => $parish,
            'logo' => $this->logoDataUri($parish),
            'parishPriest' => ClergyAssignment::query()->current()->where('role', ClergyRole::ParishPriest)->latest('starts_on')->value('name'),
        ])->setPaper('a4');

        return $pdf->stream(str_replace('/', '-', $application->application_no)."-{$letter->value}.pdf");
    }

    /**
     * Get the parish logo as a data URI, so it renders whichever disk holds it.
     */
    private function logoDataUri(ParishSetting $parish): ?string
    {
        $media = $parish->getFirstMedia('logo');

        if (! $media instanceof Media || ! in_array($media->mime_type, self::LOGO_MIME_TYPES, true)) {
            return null;
        }

        $contents = stream_get_contents($media->stream());

        return $contents === false ? null : "data:{$media->mime_type};base64,".base64_encode($contents);
    }
}
