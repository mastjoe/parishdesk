<?php

namespace App\Http\Controllers\Admin\Access;

use App\Actions\Access\UpdateUserAccess;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Access\UpdateUserAccessRequest;
use App\Http\Requests\Admin\Access\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use App\Support\Permissions\Names;
use App\Support\Table\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class UserController extends Controller
{
    /**
     * List users with their roles, plus pending invitations.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user();

        $table = TableQuery::for(User::query()->with('roles')->withCount('permissions'), $request)
            ->searchable(['name', 'email'])
            ->sortable(['name', 'email', 'created_at', 'last_login_at'], '-created_at')
            ->filter('role', function (Builder $query, string $role): void {
                $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $role));
            })
            ->filter('status', function (Builder $query, string $status): void {
                $query->where('is_active', $status === 'active');
            });

        $users = $table->paginate()->through(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'isActive' => $user->is_active,
            'lastLoginAt' => $user->last_login_at?->toIso8601String(),
            'createdAt' => $user->created_at?->toIso8601String(),
            'roles' => $user->roles->map(fn (Role $role): array => ['name' => $role->name, 'color' => $role->color])->values(),
            'directPermissionsCount' => $user->permissions_count,
        ]);

        return Inertia::render('admin/access/users/Index', [
            'users' => $users,
            'table' => $table->state(),
            'roles' => Role::query()->orderBy('name')->get(['name', 'color']),
            'catalogue' => Permission::catalogue(),
            'grantable' => $actor->effectivePermissionNames(),
            'invitations' => $actor->can('invite', User::class)
                ? UserInvitation::query()->with('inviter:id,name')->whereNull('accepted_at')->latest()->limit(20)->get()
                    ->map(fn (UserInvitation $invitation): array => [
                        'id' => $invitation->id,
                        'name' => $invitation->name,
                        'email' => $invitation->email,
                        'roles' => $invitation->roles ?? [],
                        'status' => $invitation->status(),
                        'expiresAt' => $invitation->expires_at->toIso8601String(),
                        'invitedBy' => $invitation->inviter?->name,
                    ])
                : [],
            'can' => ['invite' => $actor->can('invite', User::class)],
        ]);
    }

    /**
     * Show a user's details and access.
     */
    public function show(Request $request, User $user): Response
    {
        Gate::authorize('view', $user);

        $actor = $request->user();
        $user->load('roles.permissions', 'permissions');

        return Inertia::render('admin/access/users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'isActive' => $user->is_active,
                'isSuperAdmin' => $user->isSuperAdmin(),
                'isSelf' => $actor->is($user),
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
                'createdAt' => $user->created_at?->toIso8601String(),
                'twoFactorEnabled' => $user->two_factor_confirmed_at !== null,
                'roles' => Names::of($user->roles),
                'directPermissions' => Names::of($user->getDirectPermissions()),
                'effectivePermissions' => $user->effectivePermissionNames(),
            ],
            'roles' => Role::query()->with('permissions:id,name')->orderBy('name')->get()
                ->map(fn (Role $role): array => [
                    'name' => $role->name,
                    'color' => $role->color,
                    'description' => $role->description,
                    'isSuperAdmin' => $role->isSuperAdmin(),
                    'permissions' => $role->isSuperAdmin() ? Permission::names() : Names::of($role->permissions),
                ]),
            'catalogue' => Permission::catalogue(),
            'grantable' => $actor->effectivePermissionNames(),
            'activity' => $user->activitiesAsSubject()->with('causer:id,name')->latest()->limit(15)->get()
                ->map(fn (Activity $activity): array => [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'causer' => $activity->causer?->getAttribute('name'),
                    'properties' => $activity->properties,
                    'createdAt' => $activity->created_at?->toIso8601String(),
                ]),
            'can' => [
                'update' => $actor->can('update', $user),
                'toggleActive' => $actor->can('toggleActive', $user),
                'assignRoles' => $actor->can('assignRoles', $user),
                'assignPermissions' => $actor->can('assignPermissions', $user),
            ],
        ]);
    }

    /**
     * Update a user's name and email.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User details updated.')]);

        return back();
    }

    /**
     * Replace a user's roles and/or direct permissions.
     */
    public function updateAccess(UpdateUserAccessRequest $request, User $user, UpdateUserAccess $updateAccess): RedirectResponse
    {
        $updateAccess->handle(
            $request->user(),
            $user,
            $request->roles(),
            $request->permissions(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Access updated for :name.', ['name' => $user->name])]);

        return back();
    }

    /**
     * Activate or deactivate a user's account.
     */
    public function toggleActive(User $user): RedirectResponse
    {
        Gate::authorize('toggleActive', $user);

        $user->update(['is_active' => ! $user->is_active]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->is_active ? __(':name can sign in again.', ['name' => $user->name]) : __(':name has been deactivated.', ['name' => $user->name]),
        ]);

        return back();
    }

    /**
     * Email the user a password reset link.
     */
    public function sendPasswordReset(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        Password::broker()->sendResetLink(['email' => $user->email]);

        activity('access')->performedOn($user)->event('password_reset_sent')->log("Sent a password reset link to {$user->email}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password reset link sent to :email.', ['email' => $user->email])]);

        return back();
    }
}
