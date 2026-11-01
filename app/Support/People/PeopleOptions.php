<?php

namespace App\Support\People;

use App\Enums\ClergyRole;
use App\Enums\CommunityType;
use App\Enums\FamilyRole;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MemberStatus;
use App\Models\Community;

/**
 * The choices the people pages offer in their select inputs.
 */
class PeopleOptions
{
    /**
     * Get the enum choices used by member forms and filters.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    public static function enums(): array
    {
        return [
            'genders' => Gender::options(),
            'maritalStatuses' => MaritalStatus::options(),
            'statuses' => MemberStatus::options(),
            'familyRoles' => FamilyRole::options(),
            'communityTypes' => CommunityType::options(),
            'clergyRoles' => ClergyRole::options(),
        ];
    }

    /**
     * Get every occupation for the occupation picker, grouped as on the ISCO list.
     *
     * @return list<array{value: string, label: string, group: string}>
     */
    public static function occupations(): array
    {
        return array_map(
            fn (array $occupation): array => ['value' => $occupation['code'], 'label' => $occupation['label'], 'group' => $occupation['group']],
            Occupations::all(),
        );
    }

    /**
     * Get the label of every occupation code, for showing requested changes.
     *
     * @return array<string, string>
     */
    public static function occupationLabels(): array
    {
        return array_column(Occupations::all(), 'label', 'code');
    }

    /**
     * Get every community as an id and name pair.
     *
     * @return list<array{id: int, name: string}>
     */
    public static function communities(): array
    {
        return array_values(Community::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Community $community): array => ['id' => $community->id, 'name' => $community->name])
            ->all());
    }
}
