<?php

namespace App\Actions\Members;

use App\Models\Member;
use App\Support\Numbering\NumberGenerator;
use Illuminate\Http\UploadedFile;

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
     * when it comes from a paper register. A new photo replaces the old one,
     * since the photo collection holds a single file.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Member $member = null, ?UploadedFile $photo = null, bool $removePhoto = false): Member
    {
        $member ??= new Member;

        if (! $member->exists && empty($data['member_no'])) {
            $data['member_no'] = $this->numbers->next(Member::NUMBER_SEQUENCE, Member::NUMBER_FORMAT, resetsYearly: true);
        }

        $member->fill($data)->save();

        if ($photo !== null) {
            $member->addMedia($photo)->toMediaCollection('photo');
        } elseif ($removePhoto) {
            $member->clearMediaCollection('photo');
        }

        return $member;
    }
}
