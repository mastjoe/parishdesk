<?php

namespace App\Support\Marriages;

use App\Enums\MarriageCheck;
use App\Models\BannsObjection;
use App\Models\BannsPublication;
use App\Models\MarriageApplication;
use App\Support\People\MemberPresenter;

/**
 * Shapes marriage applications into the props the Vue pages expect.
 */
class MarriagePresenter
{
    /**
     * Get the row shown in the list of applications.
     *
     * @return array<string, mixed>
     */
    public static function row(MarriageApplication $application): array
    {
        return [
            'id' => $application->id,
            'applicationNo' => $application->application_no,
            'status' => $application->status->value,
            'statusLabel' => $application->status->label(),
            'groomName' => $application->groom_name,
            'brideName' => $application->bride_name,
            'groom' => $application->groom !== null ? MemberPresenter::summary($application->groom) : null,
            'bride' => $application->bride !== null ? MemberPresenter::summary($application->bride) : null,
            'proposedDate' => $application->proposed_date?->toDateString(),
            'bannsPublished' => $application->bannsPublished(),
            'hasOpenObjection' => $application->hasOpenObjection(),
            'createdAt' => $application->created_at?->toIso8601String(),
        ];
    }

    /**
     * Get the application with its checks, banns, objections and the
     * steps it is ready for.
     *
     * @return array<string, mixed>
     */
    public static function detail(MarriageApplication $application): array
    {
        $done = $application->checks ?? [];

        return [
            ...self::row($application),
            'groomParish' => $application->groom_parish,
            'brideParish' => $application->bride_parish,
            'weddingParish' => $application->wedding_parish,
            'officiant' => $application->officiant,
            'notes' => $application->notes,
            'checks' => array_map(fn (MarriageCheck $check): array => [
                'value' => $check->value,
                'label' => $check->label(),
                'done' => in_array($check->value, $done, true),
            ], MarriageCheck::cases()),
            'documentsVerifiedAt' => $application->documents_verified_at?->toIso8601String(),
            'clearedAt' => $application->cleared_at?->toIso8601String(),
            'withdrawnAt' => $application->withdrawn_at?->toIso8601String(),
            'withdrawalReason' => $application->withdrawal_reason,
            'bannsRequired' => MarriageApplication::BANNS_REQUIRED,
            'publications' => $application->publications->map(fn (BannsPublication $publication): array => [
                'id' => $publication->id,
                'number' => $publication->number,
                'publishedOn' => $publication->published_on->toDateString(),
                'recordedBy' => $publication->recorder?->name,
            ])->values(),
            'objections' => $application->objections->map(fn (BannsObjection $objection): array => [
                'id' => $objection->id,
                'raisedBy' => $objection->raised_by,
                'raisedOn' => $objection->raised_on->toDateString(),
                'details' => $objection->details,
                'resolution' => $objection->resolution,
                'resolvedAt' => $objection->resolved_at?->toIso8601String(),
                'resolvedBy' => $objection->resolver?->name,
                'isOpen' => $objection->isOpen(),
            ])->values(),
            'matrimonyRecordId' => $application->matrimony_record_id,
            'createdBy' => $application->creator?->name,
            'steps' => [
                'changeChecks' => $application->canChangeChecks(),
                'verifyDocuments' => $application->canVerifyDocuments(),
                'publishBanns' => $application->canPublishBanns(),
                'undoBanns' => $application->canUndoBanns(),
                'receiveObjections' => $application->canReceiveObjections(),
                'clear' => $application->canBeCleared(),
                'marry' => $application->canBeMarried(),
            ],
        ];
    }

    /**
     * Get every stored field, as the values the form works with.
     *
     * @return array<string, mixed>
     */
    public static function form(MarriageApplication $application): array
    {
        return [
            'groom_member_id' => $application->groom_member_id,
            'groom_name' => $application->groom_name,
            'groom_parish' => $application->groom_parish,
            'bride_member_id' => $application->bride_member_id,
            'bride_name' => $application->bride_name,
            'bride_parish' => $application->bride_parish,
            'proposed_date' => $application->proposed_date?->toDateString(),
            'wedding_parish' => $application->wedding_parish,
            'officiant' => $application->officiant,
            'notes' => $application->notes,
        ];
    }
}
