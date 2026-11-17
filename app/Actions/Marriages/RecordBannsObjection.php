<?php

namespace App\Actions\Marriages;

use App\Models\BannsObjection;
use App\Models\MarriageApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records an impediment someone made known about a marriage, and how it
 * was resolved. An open objection holds up the banns and the wedding.
 */
class RecordBannsObjection
{
    /**
     * Record the objection. The application is locked, so the objection
     * cannot slip in after the wedding has been recorded.
     *
     * @param  array{raised_by: string, raised_on: string, details: string}  $objection
     */
    public function handle(MarriageApplication $application, array $objection, ?User $recordedBy = null): BannsObjection
    {
        return DB::transaction(function () use ($application, $objection, $recordedBy): BannsObjection {
            $application = MarriageApplication::query()->lockForUpdate()->findOrFail($application->id);

            if (! $application->canReceiveObjections()) {
                throw ValidationException::withMessages(['stage' => __('Objections are recorded once the documents are verified and until the wedding.')]);
            }

            return $application->objections()->create([...$objection, 'recorded_by' => $recordedBy?->id]);
        });
    }

    /**
     * Record how the objection was resolved. The objection is locked, so
     * two staff cannot both resolve it.
     */
    public function resolve(BannsObjection $objection, string $resolution, ?User $resolvedBy = null): void
    {
        DB::transaction(function () use ($objection, $resolution, $resolvedBy): void {
            $objection = BannsObjection::query()->lockForUpdate()->findOrFail($objection->id);

            if (! $objection->isOpen()) {
                throw ValidationException::withMessages(['resolution' => __('This objection is already resolved.')]);
            }

            $objection->forceFill([
                'resolution' => $resolution,
                'resolved_at' => now(),
                'resolved_by' => $resolvedBy?->id,
            ])->save();
        });
    }
}
