<?php

namespace App\Actions\Members;

use App\Enums\ChangeRequestStatus;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Approves or rejects a member's profile change request.
 */
class ReviewProfileChange
{
    /**
     * Apply the requested changes to the member and close the request.
     */
    public function approve(User $reviewer, ProfileChangeRequest $request, ?string $note = null): void
    {
        DB::transaction(function () use ($reviewer, $request, $note): void {
            $request->member->update(array_intersect_key($request->changes, array_flip(ProfileChangeRequest::FIELDS)));

            $this->close($reviewer, $request, ChangeRequestStatus::Approved, $note);
        });
    }

    /**
     * Close the request without changing the member.
     */
    public function reject(User $reviewer, ProfileChangeRequest $request, ?string $note = null): void
    {
        $this->close($reviewer, $request, ChangeRequestStatus::Rejected, $note);
    }

    /**
     * Record the outcome on the request and in the audit log.
     */
    private function close(User $reviewer, ProfileChangeRequest $request, ChangeRequestStatus $status, ?string $note): void
    {
        $request->update([
            'status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        activity('people')->performedOn($request->member)->causedBy($reviewer)
            ->event("change_request_{$status->value}")
            ->withProperties(['changes' => $request->changes])
            ->log(ucfirst($status->value)." profile changes for {$request->member->full_name}");
    }
}
