<?php

namespace App\Http\Requests\Admin\Access;

use App\Concerns\ReadsStringLists;
use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    use ReadsStringLists;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            ? $this->user()->can('update', $role)
            : $this->user()->can('create', Role::class);
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
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::notIn([Role::SUPER_ADMIN]),
                Rule::unique('roles', 'name')->ignore($role instanceof Role ? $role->id : null),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['required', Rule::in(Role::COLORS)],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Permission::names())],
        ];
    }

    /**
     * Get the validated role data.
     *
     * @return array{name: string, description: string|null, color: string, permissions: list<string>}
     */
    public function roleData(): array
    {
        return [
            'name' => $this->string('name')->value(),
            'description' => $this->filled('description') ? $this->string('description')->value() : null,
            'color' => $this->string('color')->value(),
            'permissions' => $this->stringList('permissions'),
        ];
    }
}
