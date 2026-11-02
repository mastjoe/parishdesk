<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * The degree of Holy Orders a person was ordained to.
 */
enum HolyOrder: string
{
    use HasOptions;

    case Deacon = 'deacon';
    case Priest = 'priest';
    case Bishop = 'bishop';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Deacon => 'Diaconate',
            self::Priest => 'Priesthood',
            self::Bishop => 'Episcopate',
        };
    }
}
