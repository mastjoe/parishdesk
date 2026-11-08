<?php

namespace App\Actions\Marriages;

use App\Enums\MarriageStatus;
use App\Models\BannsPublication;
use App\Models\MarriageApplication;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records the next publication of the banns, and takes back the latest one
 * when it was recorded by mistake.
 */
class PublishBanns
{
    /**
     * Record the next publication. The application is locked while it is
     * numbered, so two staff recording at once cannot both publish the
     * same banns.
     */
    public function handle(MarriageApplication $application, CarbonInterface $publishedOn, ?User $recordedBy = null): BannsPublication
    {
        return DB::transaction(function () use ($application, $publishedOn, $recordedBy): BannsPublication {
            $application = MarriageApplication::query()->lockForUpdate()->findOrFail($application->id);

            if (! $application->canPublishBanns()) {
                throw ValidationException::withMessages(['stage' => $application->hasOpenObjection()
                    ? __('Resolve the objection before the banns are published again.')
                    : __('The banns cannot be published at this stage.')]);
            }

            $previous = $application->publications()->reorder('number', 'desc')->first();

            if ($previous !== null && $publishedOn->lte($previous->published_on)) {
                throw ValidationException::withMessages(['published_on' => __('Publish the banns after the previous publication on :date.', ['date' => $previous->published_on->format('j F Y')])]);
            }

            $publication = $application->publications()->create([
                'number' => ($previous->number ?? 0) + 1,
                'published_on' => $publishedOn,
                'recorded_by' => $recordedBy?->id,
            ]);

            $application->status = MarriageStatus::Banns;
            $application->save();

            return $publication;
        });
    }

    /**
     * Take back the latest publication. With none left, the application
     * returns to Documents verified.
     */
    public function undo(MarriageApplication $application, BannsPublication $publication): void
    {
        DB::transaction(function () use ($application, $publication): void {
            if (! $application->canUndoBanns() || $publication->number !== $application->bannsPublished()) {
                throw ValidationException::withMessages(['stage' => __('Only the latest publication of the banns can be taken back.')]);
            }

            $publication->delete();

            if ($application->publications()->doesntExist()) {
                $application->status = MarriageStatus::DocumentsVerified;
                $application->save();
            }
        });
    }
}
