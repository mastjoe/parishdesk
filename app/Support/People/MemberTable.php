<?php

namespace App\Support\People;

use App\Models\Member;
use App\Support\Table\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The search, filters and sorting of the member register, shared by the
 * list page and the CSV export so both always show the same members.
 */
class MemberTable
{
    /**
     * Age groups offered as a filter, as [youngest, oldest] in years.
     */
    public const array AGE_GROUPS = [
        'children' => [0, 12],
        'teens' => [13, 17],
        'youth' => [18, 35],
        'adults' => [36, 64],
        'seniors' => [65, null],
    ];

    /**
     * Build the table query for the request.
     *
     * @return TableQuery<Member>
     */
    public static function for(Request $request): TableQuery
    {
        return TableQuery::for(Member::query()->with(['community:id,name', 'family:id,name']), $request)
            ->searchable(['first_name', 'middle_name', 'last_name', 'baptismal_name', 'member_no', 'email', 'phone'])
            ->sortable(['last_name', 'member_no', 'date_of_birth', 'joined_at', 'created_at'], 'last_name')
            ->filter('status', function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->filter('gender', function (Builder $query, string $gender): void {
                $query->where('gender', $gender);
            })
            ->filter('community', function (Builder $query, string $community): void {
                $community === 'none' ? $query->whereNull('community_id') : $query->where('community_id', (int) $community);
            })
            ->filter('family', function (Builder $query, string $family): void {
                $family === 'none' ? $query->whereNull('family_id') : $query->whereNotNull('family_id');
            })
            ->filter('portal', function (Builder $query, string $portal): void {
                $portal === 'linked' ? $query->whereNotNull('user_id') : $query->whereNull('user_id');
            })
            ->filter('age', function (Builder $query, string $group): void {
                if (! isset(self::AGE_GROUPS[$group])) {
                    return;
                }

                [$youngest, $oldest] = self::AGE_GROUPS[$group];

                $query->whereDate('date_of_birth', '<=', today()->subYears($youngest));

                if ($oldest !== null) {
                    $query->whereDate('date_of_birth', '>', today()->subYears($oldest + 1));
                }
            });
    }
}
