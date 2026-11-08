<?php

namespace App\Actions\Marriages;

use App\Models\MarriageApplication;
use App\Models\User;
use App\Support\Numbering\NumberGenerator;

/**
 * Opens a marriage application or changes the couple's details.
 */
class SaveMarriageApplication
{
    public function __construct(private NumberGenerator $numbers) {}

    /**
     * Save the application, giving a new one its application number.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?MarriageApplication $application = null, ?User $createdBy = null): MarriageApplication
    {
        $application ??= new MarriageApplication;

        if (! $application->exists) {
            $application->application_no = $this->numbers->next(MarriageApplication::NUMBER_SEQUENCE, MarriageApplication::NUMBER_FORMAT, resetsYearly: true);
            $application->created_by = $createdBy?->id;
        }

        $application->fill($data)->save();

        return $application;
    }
}
