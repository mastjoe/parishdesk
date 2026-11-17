<?php

namespace App\Actions\Marriages;

use App\Enums\MarriageCheck;
use App\Enums\MarriageStatus;
use App\Models\MarriageApplication;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a marriage application through its checks, its clearance, or its
 * withdrawal. The banns and the wedding have their own actions.
 *
 * Each step locks the application and checks it again, so it cannot
 * overwrite a step another clerk has just taken.
 */
class MoveMarriageApplication
{
    /**
     * Tick off the documents and steps that are done.
     *
     * @param  list<string>  $checks  The MarriageCheck values that are done
     */
    public function updateChecks(MarriageApplication $application, array $checks): void
    {
        $this->transition($application, function (MarriageApplication $application) use ($checks): void {
            $this->ensure($application->canChangeChecks(), __('The checklist is fixed once the documents are verified.'));

            $application->checks = array_values(array_intersect(MarriageCheck::values(), $checks));
        });
    }

    /**
     * Confirm every document is in order, so the banns can be published.
     */
    public function verifyDocuments(MarriageApplication $application): void
    {
        $this->transition($application, function (MarriageApplication $application): void {
            $this->ensure($application->canVerifyDocuments(), __('Complete every check before verifying the documents.'));

            $application->status = MarriageStatus::DocumentsVerified;
            $application->documents_verified_at = now();
        });
    }

    /**
     * Clear the couple to marry once the banns are complete and unopposed.
     */
    public function clear(MarriageApplication $application): void
    {
        $this->transition($application, function (MarriageApplication $application): void {
            $this->ensure($application->canBeCleared(), $application->hasOpenObjection()
                ? __('Resolve the objection before clearing the couple.')
                : __('Publish the banns :count times before clearing the couple.', ['count' => MarriageApplication::BANNS_REQUIRED]));

            $application->status = MarriageStatus::Cleared;
            $application->cleared_at = now();
        });
    }

    /**
     * Close the application because the couple will not marry here.
     */
    public function withdraw(MarriageApplication $application, string $reason): void
    {
        $this->transition($application, function (MarriageApplication $application) use ($reason): void {
            $this->ensure($application->isOpen(), __('The application is already closed.'));

            $application->status = MarriageStatus::Withdrawn;
            $application->withdrawn_at = now();
            $application->withdrawal_reason = $reason;
        });
    }

    /**
     * Lock the application, apply the step to the locked copy and save it.
     *
     * @param  Closure(MarriageApplication): void  $step
     */
    private function transition(MarriageApplication $application, Closure $step): void
    {
        DB::transaction(function () use ($application, $step): void {
            $locked = MarriageApplication::query()->lockForUpdate()->findOrFail($application->id);

            $step($locked);

            $locked->save();
        });
    }

    /**
     * Refuse the step when the application is not ready for it.
     */
    private function ensure(bool $ready, string $message): void
    {
        if (! $ready) {
            throw ValidationException::withMessages(['stage' => $message]);
        }
    }
}
