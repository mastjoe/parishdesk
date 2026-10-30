<?php

namespace App\Http\Requests\Portal;

use App\Enums\MaritalStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileChangeRequestRequest extends FormRequest
{
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
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'wedding_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'alt_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get the requested values for the fields a member may change.
     *
     * @return array<string, string|null>
     */
    public function values(): array
    {
        return array_map(
            fn (mixed $value): ?string => is_scalar($value) ? (string) $value : null,
            $this->safe()->except('note'),
        );
    }
}
