<?php

namespace App\Http\Requests\Admin\Marriages;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RaiseBannsObjectionRequest extends FormRequest
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
            'raised_by' => ['required', 'string', 'max:255'],
            'raised_on' => ['required', 'date', 'before_or_equal:today'],
            'details' => ['required', 'string', 'max:5000'],
        ];
    }
}
