<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Where a member stands in the parish register.
 */
enum MemberStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Inactive = 'inactive';
    case TransferredIn = 'transferred_in';
    case TransferredOut = 'transferred_out';
    case Deceased = 'deceased';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::TransferredIn => 'Transferred in',
            self::TransferredOut => 'Transferred out',
            self::Deceased => 'Deceased',
        };
    }
}
