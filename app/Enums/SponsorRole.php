<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * The part a sponsor or witness plays in a sacrament.
 */
enum SponsorRole: string
{
    use HasOptions;

    case Godfather = 'godfather';
    case Godmother = 'godmother';
    case Sponsor = 'sponsor';
    case Witness = 'witness';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Godfather => 'Godfather',
            self::Godmother => 'Godmother',
            self::Sponsor => 'Sponsor',
            self::Witness => 'Witness',
        };
    }
}
