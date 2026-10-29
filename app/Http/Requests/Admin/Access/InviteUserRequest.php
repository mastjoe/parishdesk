<?php

namespace App\Http\Requests\Admin\Access;

use App\Concerns\ReadsStringLists;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    use ReadsStringLists;

    /**
     * Get the validated invitation data.
     *
     * @return array{name: string, email: string, roles: list<string>, permissions: list<string>}
     */
    public function invitationData(): array
    {
        return [
            'name' => $this->string('name')->value(),
            'email' => $this->string('email')->value(),
            'roles' => $this->stringList('roles'),
            'permissions' => $this->stringList('permissions'),
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('invite', User::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'roles' => ['array'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Permission::names())],
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
            'email.unique' => __('A user with this email already exists.'),
        ];
    }
}
