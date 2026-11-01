<?php

namespace App\Http\Requests\Admin\People;

use App\Enums\FamilyRole;
use App\Models\Family;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveFamilyRequest extends FormRequest
{
    /**
     * The most members that can be placed in a family as it is created.
     */
    public const int MAX_MEMBERS = 30;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $family = $this->route('family');

        return $family instanceof Family
            ? $this->user()->can('update', $family)
            : $this->user()->can('create', Family::class);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => Str::squish((string) $this->input('name'))]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'community_id' => ['nullable', 'integer', Rule::exists('communities', 'id')],
            'address' => ['nullable', 'string', 'max:255'],
            'home_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        // Members can be placed in a new family as it is created; afterwards they are managed from the family page.
        if (! $this->route('family') instanceof Family) {
            $rules += [
                'members' => ['array', 'max:'.self::MAX_MEMBERS],
                'members.*.member_id' => ['required', 'integer', 'distinct', Rule::exists('members', 'id')->withoutTrashed()],
                'members.*.family_role' => ['required', Rule::enum(FamilyRole::class)],
            ];
        }

        return $rules;
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $roles = collect((array) $this->input('members'))->countBy('family_role');

                foreach ([FamilyRole::Father, FamilyRole::Mother] as $parent) {
                    if ($roles->get($parent->value, 0) > 1) {
                        $validator->errors()->add('members', __('A family can only have one :role.', ['role' => Str::lower($parent->label())]));
                    }
                }
            },
        ];
    }

    /**
     * Get the validated family details, without the members.
     *
     * @return array<string, mixed>
     */
    public function familyData(): array
    {
        return $this->safe()->except('members');
    }

    /**
     * Get the members to place in a new family, with their roles.
     *
     * @return list<array{member_id: int, family_role: FamilyRole}>
     */
    public function members(): array
    {
        return array_values(array_map(fn (array $row): array => [
            'member_id' => (int) $row['member_id'],
            'family_role' => FamilyRole::from($row['family_role']),
        ], $this->validated('members', [])));
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
            'members.*.member_id' => 'member',
            'members.*.family_role' => 'family role',
        ];
    }
}
