<?php

namespace App\Http\Requests\Admin\People;

use App\Actions\Members\ImportMembers;
use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportMembersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('import', Member::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'string', 'max:255'],
            ...collect(ImportMembers::REQUIRED)->mapWithKeys(fn (string $field) => ["mapping.{$field}" => ['required', 'string']])->all(),
        ];
    }

    /**
     * Check that every mapped column exists in the uploaded file.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $headers = $this->session()->get('member_import.headers', []);

                foreach ($this->mapping() as $field => $header) {
                    if (! in_array($header, $headers, true)) {
                        $validator->errors()->add("mapping.{$field}", __('Choose a column from the uploaded file.'));
                    }
                }
            },
        ];
    }

    /**
     * Get the chosen mapping of member fields to CSV headers.
     *
     * @return array<string, string>
     */
    public function mapping(): array
    {
        return collect($this->array('mapping'))
            ->only(array_keys(ImportMembers::FIELDS))
            ->filter(fn (mixed $header): bool => is_string($header) && $header !== '')
            ->all();
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(ImportMembers::FIELDS)->mapWithKeys(fn (string $label, string $field) => ["mapping.{$field}" => $label])->all();
    }
}
