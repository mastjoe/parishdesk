<?php

namespace App\Support\Sacraments;

use App\Enums\Gender;
use App\Enums\HolyOrder;
use App\Enums\SacramentType;
use App\Enums\SponsorRole;

/**
 * The choices the sacrament pages offer, and what each register records.
 */
class SacramentOptions
{
    /**
     * Get the select choices and the shape of each register.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return [
            'types' => array_map(fn (SacramentType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'isOnce' => $type->isOnce(),
                'recordsParents' => $type->recordsParents(),
                'maxSponsors' => $type->maxSponsors(),
                'sponsorRoles' => array_map(fn (SponsorRole $role): array => ['value' => $role->value, 'label' => $role->label()], $type->sponsorRoles()),
            ], SacramentType::cases()),
            'holyOrders' => HolyOrder::options(),
            'genders' => Gender::options(),
        ];
    }
}
