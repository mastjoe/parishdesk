<?php

namespace App\Http\Controllers\Admin\Access;

use App\Actions\Access\SaveRole;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Access\SaveRoleRequest;
use App\Models\Role;
use App\Support\Permissions\Names;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * List every role with its user and permission counts.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Role::class);

        $user = $request->user();

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderByRaw('name = ? desc', [Role::SUPER_ADMIN])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'color' => $role->color,
                'isSuperAdmin' => $role->isSuperAdmin(),
                'usersCount' => $role->users_count,
                'permissionsCount' => $role->isSuperAdmin() ? count(Permission::cases()) : $role->permissions_count,
                'can' => [
                    'update' => $user->can('update', $role),
                    'delete' => $user->can('delete', $role),
                    'duplicate' => $user->can('duplicate', $role),
                ],
            ]);

        return Inertia::render('admin/access/roles/Index', [
            'roles' => $roles,
            'can' => ['create' => $user->can('create', Role::class)],
        ]);
    }

    /**
     * Show the form for creating a role.
     */
    public function create(): Response
    {
        Gate::authorize('create', Role::class);

        return Inertia::render('admin/access/roles/Create', $this->formProps());
    }

    /**
     * Store a newly created role.
     */
    public function store(SaveRoleRequest $request, SaveRole $saveRole): RedirectResponse
    {
        $role = $saveRole->handle($request->user(), $request->roleData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role ":name" created.', ['name' => $role->name])]);

        return to_route('admin.roles.index');
    }

    /**
     * Show the form for editing a role.
     */
    public function edit(Role $role): Response
    {
        Gate::authorize('update', $role);

        return Inertia::render('admin/access/roles/Edit', [
            ...$this->formProps(),
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'color' => $role->color,
                'permissions' => Names::of($role->permissions),
                'usersCount' => $role->users()->count(),
            ],
        ]);
    }

    /**
     * Update a role and its permissions.
     */
    public function update(SaveRoleRequest $request, Role $role, SaveRole $saveRole): RedirectResponse
    {
        $saveRole->handle($request->user(), $request->roleData(), $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role ":name" updated.', ['name' => $role->name])]);

        return to_route('admin.roles.index');
    }

    /**
     * Create a copy of a role with the same permissions.
     */
    public function duplicate(Request $request, Role $role, SaveRole $saveRole): RedirectResponse
    {
        Gate::authorize('duplicate', $role);

        $name = $this->uniqueCopyName($role->name);

        $copy = $saveRole->handle($request->user(), [
            'name' => $name,
            'description' => $role->description,
            'color' => $role->color,
            'permissions' => Names::of($role->permissions),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role duplicated as ":name".', ['name' => $copy->name])]);

        return to_route('admin.roles.edit', $copy);
    }

    /**
     * Delete a role that no longer has users.
     */
    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        if ($role->users()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Remove this role from its users before deleting it.')]);

            return back();
        }

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role ":name" deleted.', ['name' => $role->name])]);

        return to_route('admin.roles.index');
    }

    /**
     * Get the props shared by the create and edit forms.
     *
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'catalogue' => Permission::catalogue(),
            'colors' => Role::COLORS,
            'grantable' => request()->user()->effectivePermissionNames(),
        ];
    }

    /**
     * Find a free "Copy of …" name for a duplicated role.
     */
    private function uniqueCopyName(string $name): string
    {
        $base = Str::limit("Copy of {$name}", 52, '');
        $candidate = $base;
        $counter = 2;

        while (Role::query()->where('name', $candidate)->exists()) {
            $candidate = "{$base} ({$counter})";
            $counter++;
        }

        return $candidate;
    }
}
