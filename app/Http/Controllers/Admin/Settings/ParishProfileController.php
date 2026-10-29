<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\ParishProfileRequest;
use App\Models\ParishSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ParishProfileController extends Controller
{
    /**
     * Show the parish profile form.
     */
    public function edit(): Response
    {
        return Inertia::render('admin/settings/Parish', [
            'settings' => ParishSetting::current()->only([
                'name', 'patron_saint', 'diocese', 'deanery', 'address', 'city', 'state', 'country', 'phone', 'email', 'website',
            ]),
        ]);
    }

    /**
     * Update the parish profile.
     */
    public function update(ParishProfileRequest $request): RedirectResponse
    {
        ParishSetting::current()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Parish profile saved.')]);

        return to_route('admin.settings.parish.edit');
    }
}
