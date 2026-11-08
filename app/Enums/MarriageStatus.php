<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Where a marriage application stands, from the first interview to the
 * wedding being written in the marriage register.
 */
enum MarriageStatus: string
{
    use HasOptions;

    case Applied = 'applied';
    case DocumentsVerified = 'documents_verified';
    case Banns = 'banns';
    case Cleared = 'cleared';
    case Married = 'married';
    case Withdrawn = 'withdrawn';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Applied',
            self::DocumentsVerified => 'Documents verified',
            self::Banns => 'Banns in progress',
            self::Cleared => 'Cleared to marry',
            self::Married => 'Married',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * Whether the application is still on its way to a wedding.
     */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Married, self::Withdrawn], true);
    }

    /**
     * Get the statuses of applications still on their way to a wedding.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isOpen()));
    }
}
