<?php

namespace App\Http\Controllers\Admin\Marriages;

use App\Enums\BannsLetter;
use App\Enums\ClergyRole;
use App\Http\Controllers\Controller;
use App\Models\ClergyAssignment;
use App\Models\MarriageApplication;
use App\Models\ParishSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class BannsLetterController extends Controller
{
    /**
     * The logo types the PDF renderer can draw.
     */
    private const array LOGO_MIME_TYPES = ['image/jpeg', 'image/png'];

    /**
     * Stream a letter about the couple's banns to another parish as a PDF.
     */
    public function show(MarriageApplication $marriage, BannsLetter $letter): Response
    {
        Gate::authorize('view', $marriage);

        abort_unless($letter->isAvailableFor($marriage), 404);

        $marriage->load('publications');
        $parish = ParishSetting::current();

        $pdf = Pdf::loadView('pdf.marriages.banns-letter', [
            'letter' => $letter,
            'application' => $marriage,
            'addressee' => $letter->addressee($marriage),
            'parish' => $parish,
            'logo' => $this->logoDataUri($parish),
            'parishPriest' => ClergyAssignment::query()->current()->where('role', ClergyRole::ParishPriest)->latest('starts_on')->value('name'),
        ])->setPaper('a4');

        return $pdf->stream(str_replace('/', '-', $marriage->application_no)."-{$letter->value}.pdf");
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
