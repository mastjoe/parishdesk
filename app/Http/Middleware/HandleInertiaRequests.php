<?php

namespace App\Http\Middleware;

use App\Models\ParishSetting;
use App\Models\User;
use App\Support\Theme\Theme;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $parish = ParishSetting::current();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'permissions' => $user instanceof User ? $user->effectivePermissionNames() : [],
                'isSuperAdmin' => $user instanceof User && $user->isSuperAdmin(),
                'canAccessAdmin' => $user instanceof User && $user->canAccessAdmin(),
            ],
            'parish' => [
                'name' => $parish->name,
                'logoUrl' => $parish->logoUrl(),
                'theme' => Theme::active($parish),
                'currency' => $parish->currency,
                'timezone' => $parish->timezone,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
