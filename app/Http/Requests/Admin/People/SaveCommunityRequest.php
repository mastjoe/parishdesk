<?php

namespace App\Http\Requests\Admin\People;

use App\Enums\CommunityType;
use App\Models\Community;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveCommunityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $community = $this->route('community');

        return $community instanceof Community
            ? $this->user()->can('update', $community)
            : $this->user()->can('create', Community::class);
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
        $community = $this->route('community');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('communities', 'name')->ignore($community instanceof Community ? $community->id : null)],
            'type' => ['required', Rule::enum(CommunityType::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'leader_member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->withoutTrashed()],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['leader_member_id' => 'leader'];
    }
}
