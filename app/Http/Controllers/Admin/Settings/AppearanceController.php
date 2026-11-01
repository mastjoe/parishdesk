<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\AppearanceRequest;
use App\Models\ParishSetting;
use App\Support\Theme\Theme;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    /**
     * Show the theme and branding form.
     */
    public function edit(): Response
    {
        $settings = ParishSetting::current();

        return Inertia::render('admin/settings/Appearance', [
            'settings' => [
                'theme' => $settings->theme,
                'customColor' => $settings->custom_color,
                'logoUrl' => $settings->logoUrl(),
            ],
            'presets' => collect(Theme::PRESETS)
                ->map(fn (array $preset, string $key): array => ['key' => $key, ...$preset])
                ->values(),
        ]);
    }

    /**
     * Update the theme and logo.
     */
    public function update(AppearanceRequest $request): RedirectResponse
    {
        $settings = ParishSetting::current();

        $settings->update([
            'theme' => $request->string('theme')->value(),
            'custom_color' => $request->filled('custom_color') ? strtolower($request->string('custom_color')->value()) : $settings->custom_color,
        ]);

        if ($request->hasFile('logo')) {
            $settings->addMediaFromRequest('logo')->toMediaCollection('logo');
        } elseif ($request->boolean('remove_logo')) {
            $settings->clearMediaCollection('logo');
        }

        ParishSetting::flushCache();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Appearance saved.')]);

        return to_route('admin.settings.appearance.edit');
    }
}
