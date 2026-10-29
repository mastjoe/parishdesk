<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\FinanceSettingsRequest;
use App\Models\ParishSetting;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FinanceSettingsController extends Controller
{
    /**
     * Show the finance defaults form.
     */
    public function edit(): Response
    {
        return Inertia::render('admin/settings/Finance', [
            'settings' => ParishSetting::current()->only([
                'currency', 'timezone', 'fiscal_year_start_month', 'bank_name', 'bank_account_name', 'bank_account_number',
            ]),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    /**
     * Update the finance defaults.
     */
    public function update(FinanceSettingsRequest $request): RedirectResponse
    {
        ParishSetting::current()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Finance settings saved.')]);

        return to_route('admin.settings.finance.edit');
    }
}
