<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * The documents and steps the parish checks before the banns are published.
 */
enum MarriageCheck: string
{
    use HasOptions;

    case GroomBaptismCertificate = 'groom_baptism_certificate';
    case BrideBaptismCertificate = 'bride_baptism_certificate';
    case GroomFreedomToMarry = 'groom_freedom_to_marry';
    case BrideFreedomToMarry = 'bride_freedom_to_marry';
    case MarriageCourse = 'marriage_course';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::GroomBaptismCertificate => 'Groom\'s baptism certificate received',
            self::BrideBaptismCertificate => 'Bride\'s baptism certificate received',
            self::GroomFreedomToMarry => 'Groom\'s freedom to marry declared',
            self::BrideFreedomToMarry => 'Bride\'s freedom to marry declared',
            self::MarriageCourse => 'Marriage course completed',
        };
    }
}
