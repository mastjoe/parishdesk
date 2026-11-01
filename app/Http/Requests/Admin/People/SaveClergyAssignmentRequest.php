<?php

namespace App\Http\Requests\Admin\People;

use App\Enums\ClergyRole;
use App\Models\ClergyAssignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveClergyAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $assignment = $this->route('clergy');

        return $assignment instanceof ClergyAssignment
            ? $this->user()->can('update', $assignment)
            : $this->user()->can('create', ClergyAssignment::class);
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
        return [
            'member_id' => ['nullable', 'integer', Rule::exists('members', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::enum(ClergyRole::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['member_id' => 'member', 'starts_on' => 'start date', 'ends_on' => 'end date'];
    }
}
