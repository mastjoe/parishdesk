<?php

namespace App\Actions\Members;

use App\Models\Member;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Records the profile changes a member asks for from the portal.
 */
class SubmitProfileChange
{
    /**
     * Store the fields that differ from the register as a pending request.
     *
     * A member has at most one pending request; submitting again replaces it.
     *
     * @param  array<string, string|null>  $values
     *
     * @throws ValidationException
     */
    public function handle(User $user, Member $member, array $values, ?string $note = null): ProfileChangeRequest
    {
        $current = ProfileChangeRequest::currentValues($member);

        $changes = array_filter(
            array_intersect_key($values, array_flip(ProfileChangeRequest::FIELDS)),
            fn (?string $value, string $field): bool => $value !== $current[$field],
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changes === []) {
            throw ValidationException::withMessages(['changes' => __('Change at least one detail before sending your request.')]);
        }

        $request = $member->changeRequests()->pending()->first() ?? new ProfileChangeRequest(['member_id' => $member->id]);

        $request->fill([
            'submitted_by' => $user->id,
            'changes' => $changes,
            'original' => array_intersect_key($current, $changes),
            'note' => $note,
        ])->save();

        return $request;
    }
}
