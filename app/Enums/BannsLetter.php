<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Models\MarriageApplication;

/**
 * The letters the parish sends to other parishes about a couple's banns.
 *
 * When the groom or the bride belongs to another parish, that parish is
 * asked to publish the banns too. When the wedding is held in another
 * parish, that parish is told the banns are complete and unopposed.
 */
enum BannsLetter: string
{
    use HasOptions;

    case GroomParishRequest = 'groom-parish-request';
    case BrideParishRequest = 'bride-parish-request';
    case Confirmation = 'confirmation';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::GroomParishRequest => 'Banns request to the groom\'s parish',
            self::BrideParishRequest => 'Banns request to the bride\'s parish',
            self::Confirmation => 'Banns confirmation to the wedding parish',
        };
    }

    /**
     * Get the title printed at the top of the letter.
     */
    public function title(): string
    {
        return match ($this) {
            self::GroomParishRequest, self::BrideParishRequest => 'Request for the Publication of Banns',
            self::Confirmation => 'Certificate of Banns',
        };
    }

    /**
     * Get the parish the letter is addressed to, if the application names one.
     */
    public function addressee(MarriageApplication $application): ?string
    {
        return match ($this) {
            self::GroomParishRequest => $application->groom_parish,
            self::BrideParishRequest => $application->bride_parish,
            self::Confirmation => $application->wedding_parish,
        };
    }

    /**
     * Whether the letter can be written for the application now.
     *
     * Banns requests go out once the documents are verified and until the
     * wedding. The confirmation goes out once the couple is cleared, while
     * no objection is waiting to be resolved.
     */
    public function isAvailableFor(MarriageApplication $application): bool
    {
        if (blank($this->addressee($application))) {
            return false;
        }

        return match ($this) {
            self::GroomParishRequest, self::BrideParishRequest => in_array(
                $application->status,
                [MarriageStatus::DocumentsVerified, MarriageStatus::Banns, MarriageStatus::Cleared],
                true,
            ),
            self::Confirmation => $application->canBeMarried(),
        };
    }

    /**
     * Get the letters that can be written for the application now.
     *
     * @return list<self>
     */
    public static function availableFor(MarriageApplication $application): array
    {
        return array_values(array_filter(self::cases(), fn (self $letter): bool => $letter->isAvailableFor($application)));
    }
}
