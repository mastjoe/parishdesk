<?php

namespace App\Support\Pdf;

use App\Enums\ClergyRole;
use App\Models\ClergyAssignment;
use App\Models\ParishSetting;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Gathers what the parish letterhead layout prints on every PDF: the
 * parish, its logo and the parish priest who signs.
 */
class Letterhead
{
    /**
     * The logo types the PDF renderer can draw.
     */
    private const array LOGO_MIME_TYPES = ['image/jpeg', 'image/png'];

    /**
     * Get the data the `pdf.layouts.letter` layout expects.
     *
     * @return array{parish: ParishSetting, logo: string|null, parishPriest: string|null}
     */
    public static function data(): array
    {
        $parish = ParishSetting::current();

        return [
            'parish' => $parish,
            'logo' => self::logoDataUri($parish),
            'parishPriest' => ClergyAssignment::query()->current()->where('role', ClergyRole::ParishPriest)->latest('starts_on')->value('name'),
        ];
    }

    /**
     * Get the parish logo as a data URI, so it renders whichever disk holds it.
     */
    private static function logoDataUri(ParishSetting $parish): ?string
    {
        $media = $parish->getFirstMedia('logo');

        if (! $media instanceof Media || ! in_array($media->mime_type, self::LOGO_MIME_TYPES, true)) {
            return null;
        }

        $contents = stream_get_contents($media->stream());

        return $contents === false ? null : "data:{$media->mime_type};base64,".base64_encode($contents);
    }
}
