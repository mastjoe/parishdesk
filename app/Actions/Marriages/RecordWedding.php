<?php

namespace App\Actions\Marriages;

use App\Actions\Sacraments\SaveSacramentRecord;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MarriageStatus;
use App\Enums\SacramentType;
use App\Models\MarriageApplication;
use App\Models\Member;
use App\Models\SacramentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes a cleared couple's wedding in the marriage register and closes
 * their application.
 */
class RecordWedding
{
    public function __construct(private SaveSacramentRecord $save) {}

    /**
     * Record the wedding. The couple's member records are marked married
     * with the wedding date.
     *
     * @param  array{received_on: string, minister?: string|null, register_book?: string|null, register_page?: string|null, entry_no?: string|null, notes?: string|null}  $entry
     * @param  list<array{member_id: int|null, name: string, role: string}>  $witnesses
     */
    public function handle(MarriageApplication $application, array $entry, array $witnesses, ?User $recordedBy = null): SacramentRecord
    {
        return DB::transaction(function () use ($application, $entry, $witnesses, $recordedBy): SacramentRecord {
            $application = MarriageApplication::query()->with(['groom', 'bride'])->lockForUpdate()->findOrFail($application->id);

            if (! $application->canBeMarried()) {
                throw ValidationException::withMessages(['stage' => __('The couple must be cleared to marry before the wedding is recorded.')]);
            }

            $record = $this->save->handle([
                ...$entry,
                'type' => SacramentType::Matrimony,
                'member_id' => $application->groom_member_id,
                'person_name' => $application->groom_name,
                'gender' => $application->groom->gender ?? Gender::Male,
                'date_of_birth' => $application->groom?->date_of_birth,
                'elsewhere' => false,
                'place' => null,
                'spouse_member_id' => $application->bride_member_id,
                'spouse_name' => $application->bride_name,
            ], $witnesses, recordedBy: $recordedBy);

            $application->status = MarriageStatus::Married;
            $application->matrimony_record_id = $record->id;
            $application->save();

            foreach ([$application->groom, $application->bride] as $spouse) {
                if ($spouse instanceof Member && ! $spouse->trashed()) {
                    $spouse->update(['marital_status' => MaritalStatus::Married, 'wedding_date' => $record->received_on]);
                }
            }

            return $record;
        });
    }
}
