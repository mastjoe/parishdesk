<?php

namespace App\Rules;

use App\Enums\FamilyRole;
use App\Models\Member;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A family has at most one father and one mother.
 */
class OneParentPerFamily implements ValidationRule
{
    /**
     * @param  int|null  $familyId  The family the role is held in; nothing is checked without one.
     * @param  int|null  $memberId  The member taking the role, who may already hold it.
     */
    public function __construct(
        private readonly ?int $familyId,
        private readonly ?int $memberId = null,
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $role = is_string($value) ? FamilyRole::tryFrom($value) : null;

        if ($this->familyId === null || $role === null || ! $role->isParent()) {
            return;
        }

        $holder = Member::query()
            ->where('family_id', $this->familyId)
            ->where('family_role', $role)
            ->when($this->memberId !== null, fn ($query) => $query->whereKeyNot($this->memberId))
            ->first();

        if ($holder !== null) {
            $fail(__(':name is already the :role in this family.', ['name' => $holder->full_name, 'role' => strtolower($role->label())]));
        }
    }
}
