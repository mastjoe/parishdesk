<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * A member's gender as recorded in the parish register.
 */
enum Gender: string
{
    use HasOptions;

    case Male = 'male';
    case Female = 'female';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
        };
    }
}
