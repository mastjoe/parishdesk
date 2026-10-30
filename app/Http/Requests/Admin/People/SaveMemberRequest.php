<?php

namespace App\Http\Requests\Admin\People;

use App\Enums\FamilyRole;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MemberStatus;
use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $member = $this->route('member');

        return $member instanceof Member
            ? $this->user()->can('update', $member)
            : $this->user()->can('create', Member::class);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $names = [];

        foreach (['title', 'first_name', 'middle_name', 'last_name', 'baptismal_name'] as $field) {
            if (is_string($this->input($field))) {
                $names[$field] = Str::squish($this->input($field));
            }
        }

        $this->merge([
            ...$names,
            'email' => is_string($this->input('email')) ? Str::lower(trim($this->input('email'))) : null,
            'family_role' => $this->filled('family_id') ? $this->input('family_role') : null,
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
            'title' => ['nullable', 'string', 'max:30'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'baptismal_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'wedding_date' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'alt_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'state_of_origin' => ['nullable', 'string', 'max:100'],
            'lga_of_origin' => ['nullable', 'string', 'max:100'],
            'community_id' => ['nullable', 'integer', Rule::exists('communities', 'id')],
            'family_id' => ['nullable', 'integer', Rule::exists('families', 'id')->withoutTrashed()],
            'family_role' => ['nullable', 'required_with:family_id', Rule::enum(FamilyRole::class)],
            'status' => ['required', Rule::enum(MemberStatus::class)],
            'joined_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['boolean'],
        ];
    }

    /**
     * Get the validated member attributes, without the photo fields.
     *
     * @return array<string, mixed>
     */
    public function memberData(): array
    {
        return $this->safe()->except(['photo', 'remove_photo']);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'community_id' => 'community',
            'family_id' => 'family',
            'lga_of_origin' => 'LGA of origin',
        ];
    }
}
