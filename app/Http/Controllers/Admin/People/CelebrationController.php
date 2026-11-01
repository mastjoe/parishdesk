<?php

namespace App\Http\Controllers\Admin\People;

use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Support\People\MemberPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CelebrationController extends Controller
{
    /**
     * The ranges, in days, the page can look ahead.
     */
    public const array RANGES = [7, 30, 90];

    /**
     * List upcoming birthdays and wedding anniversaries.
     */
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Member::class);

        $days = in_array($request->integer('days'), self::RANGES, true) ? $request->integer('days') : 30;

        return Inertia::render('admin/people/members/Celebrations', [
            'days' => $days,
            'ranges' => self::RANGES,
            'birthdays' => self::upcoming('date_of_birth', $days),
            'anniversaries' => self::upcoming('wedding_date', $days),
        ]);
    }

    /**
     * Get the members celebrating a yearly date in the coming days, soonest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function upcoming(string $column, int $days, int $limit = 500): array
    {
        $today = today();

        return array_values(Member::query()
            ->with('media')
            ->whereNotIn('status', [MemberStatus::Deceased, MemberStatus::TransferredOut])
            ->celebrating($column, $today, $days)
            ->limit($limit)
            ->get()
            ->map(function (Member $member) use ($column, $today): array {
                $date = $member->getAttribute($column);
                $next = Member::nextOccurrence($date, $today);

                return [
                    ...MemberPresenter::summary($member),
                    'phone' => $member->phone,
                    'date' => $next->toDateString(),
                    'years' => $next->year - $date->year,
                    'inDays' => (int) $today->diffInDays($next),
                ];
            })
            ->sortBy('date')
            ->all());
    }
}
