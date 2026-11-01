<?php

namespace App\Http\Controllers\Portal;

use App\Enums\FamilyRole;
use App\Enums\MaritalStatus;
use App\Http\Controllers\Controller;
use App\Models\Member;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * How many days ahead a birthday or anniversary is announced on the home page.
     */
    private const int CELEBRATION_NOTICE_DAYS = 30;

    /**
     * Show the member portal home.
     */
    public function __invoke(Request $request): Response
    {
        $member = $request->user()->member?->load([
            'family' => fn ($query) => $query->withCount('members'),
            'family.members' => fn ($query) => $query->with('media')->orderByRaw(FamilyRole::ORDER_EXPRESSION)->orderBy('date_of_birth')->limit(5),
            'community:id,name',
        ]);

        return Inertia::render('portal/Dashboard', [
            'member' => $member !== null ? [
                'name' => $member->full_name,
                'firstName' => $member->first_name,
                'memberNo' => $member->member_no,
                'photoUrl' => $member->photoUrl(),
                'community' => $member->community?->name,
                'memberSince' => $member->joined_at?->year,
                'family' => $member->family !== null ? [
                    'name' => $member->family->name,
                    'membersCount' => $member->family->members_count,
                    'people' => $member->family->members->map(fn (Member $person): array => [
                        'id' => $person->id,
                        'name' => $person->full_name,
                        'photoUrl' => $person->photoUrl(),
                    ]),
                ] : null,
                'celebrations' => array_values(array_filter([
                    $this->upcoming('birthday', $member->date_of_birth),
                    // Only celebrate a wedding anniversary while the marriage is current.
                    $this->upcoming('anniversary', $member->marital_status === MaritalStatus::Married ? $member->wedding_date : null),
                ])),
                'hasPendingChanges' => $member->changeRequests()->pending()->exists(),
            ] : null,
        ]);
    }

    /**
     * Describe a birthday or wedding anniversary coming up soon.
     *
     * @return array{type: string, date: string, inDays: int, years: int}|null
     */
    private function upcoming(string $type, ?CarbonInterface $date): ?array
    {
        if ($date === null) {
            return null;
        }

        $today = now()->startOfDay();
        $next = Member::nextOccurrence($date, $today);
        $inDays = (int) $today->diffInDays($next);

        return $inDays <= self::CELEBRATION_NOTICE_DAYS ? [
            'type' => $type,
            'date' => $next->toDateString(),
            'inDays' => $inDays,
            'years' => $next->year - $date->year,
        ] : null;
    }
}
