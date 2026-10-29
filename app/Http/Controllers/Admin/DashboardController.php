<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
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
                'users' => $user->can(Permission::UsersView) ? [
                    'total' => User::query()->count(),
                    'active' => User::query()->where('is_active', true)->count(),
                    'pendingInvitations' => UserInvitation::query()->pending()->count(),
                ] : null,
                'roles' => $user->can(Permission::RolesView) ? Role::query()->count() : null,
            ],
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
}
