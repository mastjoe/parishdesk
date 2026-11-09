<?php

namespace App\Http\Requests\Admin\Marriages;

use App\Enums\Gender;
use App\Models\MarriageApplication;
use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveMarriageApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $application = $this->application();

        return $application !== null
            ? $this->user()->can('update', $application)
            : $this->user()->can('create', MarriageApplication::class);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $squish = fn (mixed $value): ?string => is_string($value) && Str::squish($value) !== '' ? Str::squish($value) : null;

        $this->merge([
            'groom_name' => $squish($this->input('groom_name')),
            'bride_name' => $squish($this->input('bride_name')),
            'groom_parish' => $squish($this->input('groom_parish')),
            'bride_parish' => $squish($this->input('bride_parish')),
            'groom_father_name' => $squish($this->input('groom_father_name')),
            'groom_mother_name' => $squish($this->input('groom_mother_name')),
            'bride_father_name' => $squish($this->input('bride_father_name')),
            'bride_mother_name' => $squish($this->input('bride_mother_name')),
            'wedding_parish' => $squish($this->input('wedding_parish')),
            'officiant' => $squish($this->input('officiant')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'groom_member_id' => ['nullable', 'integer', 'different:bride_member_id', Rule::exists('members', 'id')->withoutTrashed()],
            'groom_name' => ['required', 'string', 'max:255'],
            'groom_parish' => ['nullable', 'string', 'max:255'],
            'bride_member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->withoutTrashed()],
            'bride_name' => ['required', 'string', 'max:255'],
            'bride_parish' => ['nullable', 'string', 'max:255'],
            ...$this->parentRules('groom'),
            ...$this->parentRules('bride'),
            'proposed_date' => ['nullable', 'date'],
            'wedding_parish' => ['nullable', 'string', 'max:255'],
            'officiant' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get the rules for one partner's father and mother, each picked from
     * the member register or named.
     *
     * @return array<string, array<mixed>>
     */
    private function parentRules(string $side): array
    {
        $rules = [];

        foreach (['father', 'mother'] as $parent) {
            $rules["{$side}_{$parent}_member_id"] = ['nullable', 'integer', "different:{$side}_member_id", Rule::exists('members', 'id')->withoutTrashed()];
            $rules["{$side}_{$parent}_name"] = ['nullable', "required_with:{$side}_{$parent}_member_id", 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * A member can only be the groom or a father if he is a man and the bride
     * or a mother if she is a woman, and can only be in one open application
     * at a time.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['groom_member_id', 'bride_member_id'])) {
                    return;
                }

                foreach (['groom' => Gender::Male, 'bride' => Gender::Female] as $side => $gender) {
                    $member = $this->filled("{$side}_member_id") ? Member::query()->find($this->integer("{$side}_member_id")) : null;

                    if ($member === null) {
                        continue;
                    }

                    if ($member->gender !== $gender) {
                        $validator->errors()->add("{$side}_member_id", __(':name is not recorded as a :gender in the member register.', [
                            'name' => $member->full_name,
                            'gender' => $gender === Gender::Male ? 'man' : 'woman',
                        ]));

                        continue;
                    }

                    $other = MarriageApplication::query()->open()->involving($member->id)
                        ->when($this->application(), fn ($query, MarriageApplication $application) => $query->whereKeyNot($application->id))
                        ->first();

                    if ($other !== null) {
                        $validator->errors()->add("{$side}_member_id", __(':name already has an open marriage application (:number).', [
                            'name' => $member->full_name,
                            'number' => $other->application_no,
                        ]));
                    }
                }
            },
            function (Validator $validator): void {
                foreach (['groom', 'bride'] as $side) {
                    foreach (['father' => Gender::Male, 'mother' => Gender::Female] as $parent => $gender) {
                        $key = "{$side}_{$parent}_member_id";

                        if ($validator->errors()->has($key) || ! $this->filled($key)) {
                            continue;
                        }

                        $member = Member::query()->find($this->integer($key));

                        if ($member !== null && $member->gender !== $gender) {
                            $validator->errors()->add($key, __(':name is not recorded as a :gender in the member register.', [
                                'name' => $member->full_name,
                                'gender' => $gender === Gender::Male ? 'man' : 'woman',
                            ]));
                        }
                    }
                }
            },
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'groom_member_id.different' => __('The groom and the bride must be different people.'),
            '*_father_member_id.different' => __('A partner cannot be their own father.'),
            '*_mother_member_id.different' => __('A partner cannot be their own mother.'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'groom_member_id' => 'groom',
            'groom_name' => 'groom\'s name',
            'groom_parish' => 'groom\'s parish',
            'bride_member_id' => 'bride',
            'bride_name' => 'bride\'s name',
            'bride_parish' => 'bride\'s parish',
            'groom_father_name' => 'groom\'s father\'s name',
            'groom_mother_name' => 'groom\'s mother\'s name',
            'bride_father_name' => 'bride\'s father\'s name',
            'bride_mother_name' => 'bride\'s mother\'s name',
        ];
    }

    /**
     * Get the application being changed, if any.
     */
    private function application(): ?MarriageApplication
    {
        $application = $this->route('marriage');

        return $application instanceof MarriageApplication ? $application : null;
    }
}
