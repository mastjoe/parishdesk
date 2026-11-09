<?php

namespace App\Support\Sacraments;

use App\Enums\HolyOrder;
use App\Models\Member;
use App\Models\SacramentRecord;
use App\Models\SacramentSponsor;
use App\Support\People\MemberPresenter;

/**
 * Shapes register entries into the props the Vue pages expect.
 */
class SacramentPresenter
{
    /**
     * Get the row shown in the register list and on member profiles.
     *
     * @return array<string, mixed>
     */
    public static function row(SacramentRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => $record->type->value,
            'typeLabel' => $record->type->label(),
            'personName' => $record->person_name,
            'spouseName' => $record->spouse_name,
            'receivedOn' => $record->received_on->toDateString(),
            'elsewhere' => $record->elsewhere,
            'place' => $record->place,
            'minister' => $record->minister,
            'reference' => $record->registerReference(),
            'member' => $record->member !== null ? MemberPresenter::summary($record->member) : null,
        ];
    }

    /**
     * Get the entry with everything the register holds about it.
     *
     * @return array<string, mixed>
     */
    public static function detail(SacramentRecord $record): array
    {
        $order = HolyOrder::tryFrom((string) $record->detail('order'));

        return [
            ...self::row($record),
            'gender' => $record->gender?->value,
            'genderLabel' => $record->gender?->label(),
            'dateOfBirth' => $record->date_of_birth?->toDateString(),
            'fatherName' => $record->father_name,
            'motherName' => $record->mother_name,
            'spouse' => $record->spouse !== null ? MemberPresenter::summary($record->spouse) : null,
            'confirmationName' => $record->detail('confirmation_name'),
            'orderLabel' => $order?->label(),
            'registerBook' => $record->register_book,
            'registerPage' => $record->register_page,
            'entryNo' => $record->entry_no,
            'notes' => $record->notes,
            'recordedBy' => $record->recorder?->name,
            'createdAt' => $record->created_at?->toIso8601String(),
            'sponsors' => $record->sponsors->map(fn (SacramentSponsor $sponsor): array => [
                'id' => $sponsor->id,
                'name' => $sponsor->name,
                'role' => $sponsor->role->value,
                'roleLabel' => $sponsor->role->label(),
                'member' => $sponsor->member !== null ? MemberPresenter::summary($sponsor->member) : null,
            ])->values(),
        ];
    }

    /**
     * Get every stored field, as the values the form works with.
     *
     * @return array<string, mixed>
     */
    public static function form(SacramentRecord $record): array
    {
        return [
            'type' => $record->type->value,
            'member_id' => $record->member_id,
            'person_name' => $record->person_name,
            'gender' => $record->gender?->value,
            'date_of_birth' => $record->date_of_birth?->toDateString(),
            'received_on' => $record->received_on->toDateString(),
            'elsewhere' => $record->elsewhere,
            'place' => $record->place,
            'minister' => $record->minister,
            'father_name' => $record->father_name,
            'mother_name' => $record->mother_name,
            'spouse_member_id' => $record->spouse_member_id,
            'spouse_name' => $record->spouse_name,
            'details' => [
                'confirmation_name' => $record->detail('confirmation_name'),
                'order' => $record->detail('order'),
            ],
            'register_book' => $record->register_book,
            'register_page' => $record->register_page,
            'entry_no' => $record->entry_no,
            'notes' => $record->notes,
            'sponsors' => $record->sponsors->map(fn (SacramentSponsor $sponsor): array => [
                'member_id' => $sponsor->member_id,
                'name' => $sponsor->name,
                'role' => $sponsor->role->value,
            ])->values()->all(),
        ];
    }

    /**
     * Get what the register can copy from a member's record: their name,
     * gender, date of birth and, from their family, their parents' names.
     *
     * @return array<string, mixed>
     */
    public static function person(Member $member): array
    {
        $parents = $member->familyParents();

        return [
            ...MemberPresenter::summary($member),
            'dateOfBirth' => $member->date_of_birth?->toDateString(),
            'fatherName' => $parents['father']?->full_name,
            'motherName' => $parents['mother']?->full_name,
        ];
    }
}
