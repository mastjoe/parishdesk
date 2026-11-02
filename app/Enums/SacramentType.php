<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * The seven sacraments, less Penance, which is never recorded in a register.
 */
enum SacramentType: string
{
    use HasOptions;

    case Baptism = 'baptism';
    case FirstHolyCommunion = 'first_holy_communion';
    case Confirmation = 'confirmation';
    case Matrimony = 'matrimony';
    case HolyOrders = 'holy_orders';
    case AnointingOfTheSick = 'anointing_of_the_sick';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Baptism => 'Baptism',
            self::FirstHolyCommunion => 'First Holy Communion',
            self::Confirmation => 'Confirmation',
            self::Matrimony => 'Matrimony',
            self::HolyOrders => 'Holy Orders',
            self::AnointingOfTheSick => 'Anointing of the Sick',
        };
    }

    /**
     * Whether a person receives the sacrament only once, so a member can
     * have at most one record of it.
     */
    public function isOnce(): bool
    {
        return in_array($this, [self::Baptism, self::FirstHolyCommunion, self::Confirmation], true);
    }

    /**
     * Whether the register records the names of the person's parents.
     */
    public function recordsParents(): bool
    {
        return in_array($this, [self::Baptism, self::FirstHolyCommunion, self::Confirmation], true);
    }

    /**
     * Get the roles the sponsors of this sacrament can hold. Sacraments
     * without sponsors return an empty list.
     *
     * @return list<SponsorRole>
     */
    public function sponsorRoles(): array
    {
        return match ($this) {
            self::Baptism => [SponsorRole::Godfather, SponsorRole::Godmother],
            self::Confirmation => [SponsorRole::Sponsor],
            self::Matrimony => [SponsorRole::Witness],
            default => [],
        };
    }

    /**
     * Get the most sponsors or witnesses the register takes for one entry.
     */
    public function maxSponsors(): int
    {
        return match ($this) {
            self::Baptism, self::Matrimony => 4,
            self::Confirmation => 2,
            default => 0,
        };
    }
}
