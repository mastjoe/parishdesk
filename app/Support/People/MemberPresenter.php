<?php

namespace App\Support\People;

use App\Models\Member;

/**
 * Shapes member records into the props the Vue pages expect.
 */
class MemberPresenter
{
    /**
     * Get the short form used in lists, pickers and family trees.
     *
     * @return array<string, mixed>
     */
    public static function summary(Member $member): array
    {
        return [
            'id' => $member->id,
            'memberNo' => $member->member_no,
            'name' => $member->full_name,
            'title' => $member->title,
            'photoUrl' => $member->photoUrl(),
            'gender' => $member->gender->value,
            'age' => $member->date_of_birth?->age,
            'status' => $member->status->value,
            'familyRole' => $member->family_role?->value,
        ];
    }

    /**
     * Get the row shown in the member register.
     *
     * @return array<string, mixed>
     */
    public static function row(Member $member): array
    {
        return [
            ...self::summary($member),
            'phone' => $member->phone,
            'email' => $member->email,
            'community' => $member->community?->name,
            'family' => $member->family !== null ? ['id' => $member->family->id, 'name' => $member->family->name] : null,
            'hasAccount' => $member->user_id !== null,
        ];
    }

    /**
     * Get every stored field, as the values a form works with.
     *
     * @return array<string, string|int|bool|null>
     */
    public static function form(Member $member): array
    {
        return [
            'title' => $member->title,
            'first_name' => $member->first_name,
            'middle_name' => $member->middle_name,
            'last_name' => $member->last_name,
            'baptismal_name' => $member->baptismal_name,
            'gender' => $member->gender->value,
            'date_of_birth' => $member->date_of_birth?->toDateString(),
            'marital_status' => $member->marital_status?->value,
            'wedding_date' => $member->wedding_date?->toDateString(),
            'phone' => $member->phone,
            'alt_phone' => $member->alt_phone,
            'whatsapp_phone' => $member->whatsapp_phone,
            'email' => $member->email,
            'communication_consent' => $member->communication_consent,
            'address' => $member->address,
            'city' => $member->city,
            'state' => $member->state,
            'occupation' => $member->occupation,
            'emergency_contact_name' => $member->emergency_contact_name,
            'emergency_contact_phone' => $member->emergency_contact_phone,
            'emergency_contact_relationship' => $member->emergency_contact_relationship,
            'state_of_origin' => $member->state_of_origin,
            'lga_of_origin' => $member->lga_of_origin,
            'community_id' => $member->community_id,
            'family_id' => $member->family_id,
            'family_role' => $member->family_role?->value,
            'status' => $member->status->value,
            'joined_at' => $member->joined_at?->toDateString(),
            'notes' => $member->notes,
        ];
    }
}
