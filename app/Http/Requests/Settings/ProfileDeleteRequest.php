<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                $isLastSuperAdmin = $user->isSuperAdmin()
                    && User::role(Role::SUPER_ADMIN)->whereKeyNot($user->getKey())->doesntExist();

                if ($isLastSuperAdmin) {
                    $validator->errors()->add('password', __('You are the only Super Admin. Make someone else a Super Admin before deleting your account.'));
                }
            },
        ];
    }
}
