<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MemberStatus;
use App\Enums\Permission;
use App\Http\Controllers\Admin\People\CelebrationController;
use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\Member;
use App\Models\ProfileChangeRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    /**
     * Show the admin home with widgets for what the user may see.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'members' => $user->can(Permission::MembersView) ? [
                    'total' => Member::query()->count(),
                    'newThisMonth' => Member::query()->where('created_at', '>=', now()->startOfMonth())->count(),
                    'families' => Family::query()->count(),
                    'active' => Member::query()->where('status', MemberStatus::Active)->count(),
                    'ageGroups' => $this->ageGroups(),
                ] : null,
                'pendingChangeRequests' => $user->can(Permission::MembersChangeRequestsApprove)
                    ? ProfileChangeRequest::query()->pending()->count()
                    : null,
                'users' => $user->can(Permission::UsersView) ? [
                    'total' => User::query()->count(),
                    'active' => User::query()->where('is_active', true)->count(),
                    'pendingInvitations' => UserInvitation::query()->pending()->whereNull('member_id')->count(),
                ] : null,
                'roles' => $user->can(Permission::RolesView) ? Role::query()->count() : null,
            ],
            'birthdays' => $user->can(Permission::MembersView) ? CelebrationController::upcoming('date_of_birth', 7, limit: 8) : null,
            'recentActivity' => $user->can(Permission::ActivityView)
                ? Activity::query()->with('causer')->latest()->limit(8)->get()->map(fn (Activity $activity): array => [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'causer' => $activity->causer?->getAttribute('name'),
                    'createdAt' => $activity->created_at?->toIso8601String(),
                ])
                : null,
        ]);
    }

    /**
     * Count living members in the age groups parish ministries are organised around.
     *
     * @return array{groups: list<array{label: string, count: int}>, unknown: int}
     */
    private function ageGroups(): array
    {
        $groups = ['Children (0-12)' => [0, 12], 'Teens (13-17)' => [13, 17], 'Youth (18-35)' => [18, 35], 'Adults (36-59)' => [36, 59], 'Seniors (60+)' => [60, PHP_INT_MAX]];

        $ages = Member::query()->whereNot('status', MemberStatus::Deceased)->pluck('date_of_birth')
            ->map(fn (?CarbonInterface $birthday): ?int => $birthday?->age);

        return [
            'groups' => array_map(
                fn (string $label, array $range): array => ['label' => $label, 'count' => $ages->filter(fn (?int $age): bool => $age !== null && $age >= $range[0] && $age <= $range[1])->count()],
                array_keys($groups),
                $groups,
            ),
            'unknown' => $ages->filter(fn (?int $age): bool => $age === null)->count(),
        ];
    }
}
