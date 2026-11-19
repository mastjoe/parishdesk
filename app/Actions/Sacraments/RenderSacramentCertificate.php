<?php

namespace App\Actions\Sacraments;

use App\Enums\Gender;
use App\Enums\SacramentType;
use App\Models\SacramentRecord;
use App\Support\Pdf\Letterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the certificate the parish issues from a register entry as a PDF.
 */
class RenderSacramentCertificate
{
    /**
     * Render the certificate and stream it to the browser. Only the
     * marriage certificate is issued so far.
     */
    public function handle(SacramentRecord $record): Response
    {
        if ($record->type !== SacramentType::Matrimony) {
            throw new InvalidArgumentException("No certificate is issued for {$record->type->label()}.");
        }

        $title = (string) $record->type->certificateTitle();

        $pdf = Pdf::loadView('pdf.sacraments.marriage-certificate', [
            ...Letterhead::data(),
            ...$this->couple($record),
            'title' => $title,
            'record' => $record->load(['sponsors', 'marriageApplication']),
        ])->setPaper('a4');

        return $pdf->stream(Str::slug($title)."-{$record->id}.pdf");
    }

    /**
     * Get the husband and the wife from a Matrimony entry. The entry is
     * written for one spouse and names the other, so its gender says which
     * is which. When the wedding went through the banns here, the
     * application adds each spouse's parents and parish.
     *
     * @return array{husband: array{name: string, father: string|null, mother: string|null, parish: string|null}, wife: array{name: string, father: string|null, mother: string|null, parish: string|null}}
     */
    private function couple(SacramentRecord $record): array
    {
        $application = $record->marriageApplication;
        $personIsWife = $record->gender === Gender::Female;
        $person = $record->person_name;
        $spouse = (string) $record->spouse_name;

        return [
            'husband' => [
                'name' => $personIsWife ? $spouse : $person,
                'father' => $application?->groom_father_name,
                'mother' => $application?->groom_mother_name,
                'parish' => $application?->groom_parish,
            ],
            'wife' => [
                'name' => $personIsWife ? $person : $spouse,
                'father' => $application?->bride_father_name,
                'mother' => $application?->bride_mother_name,
                'parish' => $application?->bride_parish,
            ],
        ];
    }
}
