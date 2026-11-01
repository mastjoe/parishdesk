<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What kind of grouping a parish community is.
 */
enum CommunityType: string
{
    use HasOptions;

    case SmallChristianCommunity = 'scc';
    case Station = 'station';
    case Outstation = 'outstation';
    case Zone = 'zone';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::SmallChristianCommunity => 'Small Christian Community',
            self::Station => 'Station',
            self::Outstation => 'Outstation',
            self::Zone => 'Zone',
        };
    }
}
