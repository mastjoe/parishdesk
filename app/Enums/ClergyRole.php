<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * The office a priest or deacon held in the parish.
 */
enum ClergyRole: string
{
    use HasOptions;

    case ParishPriest = 'parish_priest';
    case AssistantPriest = 'assistant_priest';
    case ResidentPriest = 'resident_priest';
    case Deacon = 'deacon';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::ParishPriest => 'Parish Priest',
            self::AssistantPriest => 'Assistant Priest',
            self::ResidentPriest => 'Resident Priest',
            self::Deacon => 'Deacon',
        };
    }
}
