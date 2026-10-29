<?php

namespace App\Http\Requests\Admin\Access;

use App\Concerns\ReadsStringLists;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserAccessRequest extends FormRequest
{
    use ReadsStringLists;

    /**
     * Get the requested roles, or null when roles are not being changed.
     *
     * @return list<string>|null
     */
    public function roles(): ?array
    {
        return $this->has('roles') ? $this->stringList('roles') : null;
    }

    /**
     * Get the requested direct permissions, or null when they are not being changed.
     *
     * @return list<string>|null
     */
    public function permissions(): ?array
    {
        return $this->has('permissions') ? $this->stringList('permissions') : null;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * Each part is authorised separately: changing roles needs one
     * permission and changing direct permissions needs another.
     */
    public function authorize(): bool
    {
        /** @var User $target */
        $target = $this->route('user');

        if ($this->has('roles') && $this->user()->cannot('assignRoles', $target)) {
            return false;
        }

        if ($this->has('permissions') && $this->user()->cannot('assignPermissions', $target)) {
            return false;
        }

        return $this->hasAny(['roles', 'permissions']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'roles' => ['sometimes', 'present', 'array'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')],
            'permissions' => ['sometimes', 'present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Permission::names())],
        ];
    }
}
