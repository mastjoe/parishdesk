<?php

namespace App\Http\Requests\Admin\Marriages;

use App\Enums\MarriageCheck;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMarriageChecksRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('marriage'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'checks' => ['present', 'array'],
            'checks.*' => ['distinct', Rule::enum(MarriageCheck::class)],
        ];
    }
}
