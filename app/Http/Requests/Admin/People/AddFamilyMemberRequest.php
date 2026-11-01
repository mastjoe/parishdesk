<?php

namespace App\Http\Requests\Admin\People;

use App\Enums\FamilyRole;
use App\Models\Family;
use App\Rules\OneParentPerFamily;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddFamilyMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $family = $this->route('family');

        return $family instanceof Family && $this->user()->can('update', $family);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')->withoutTrashed()],
            'family_role' => ['required', Rule::enum(FamilyRole::class), $this->oneParent()],
        ];
    }

    /**
     * Keep the family to one father and one mother.
     */
    private function oneParent(): OneParentPerFamily
    {
        $family = $this->route('family');

        return new OneParentPerFamily($family instanceof Family ? $family->id : null, $this->integer('member_id') ?: null);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['member_id' => 'member', 'family_role' => 'role'];
    }
}
