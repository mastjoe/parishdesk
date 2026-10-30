<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * How a member is related to the family they belong to.
 */
enum FamilyRole: string
{
    use HasOptions;

    case Head = 'head';
    case Spouse = 'spouse';
    case Child = 'child';
    case Dependant = 'dependant';
    case Other = 'other';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Head => 'Head of family',
            self::Spouse => 'Spouse',
            self::Child => 'Child',
            self::Dependant => 'Dependant',
            self::Other => 'Other',
        };
    }
}
