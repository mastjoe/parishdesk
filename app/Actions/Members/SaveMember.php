<?php

namespace App\Actions\Members;

use App\Models\Member;
use App\Support\Numbering\NumberGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Registers a new member or updates an existing one, including their photo.
 */
class SaveMember
{
    public function __construct(
        private NumberGenerator $numbers,
    ) {}

    /**
     * Save the member.
     *
     * New members get the next member number unless one is given, for example
     * when it comes from a paper register.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Member $member = null, ?UploadedFile $photo = null, bool $removePhoto = false): Member
    {
        $member ??= new Member;

        if (! $member->exists && empty($data['member_no'])) {
            $data['member_no'] = $this->numbers->next(Member::NUMBER_SEQUENCE, Member::NUMBER_FORMAT, resetsYearly: true);
        }

        if ($photo !== null || $removePhoto) {
            if ($member->photo_path !== null) {
                Storage::disk('public')->delete($member->photo_path);
            }

            $data['photo_path'] = $photo?->store('members', 'public');
        }

        $member->fill($data)->save();

        return $member;
    }
}
