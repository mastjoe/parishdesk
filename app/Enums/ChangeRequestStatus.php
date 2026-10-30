<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * The review state of a profile change request.
 */
enum ChangeRequestStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
