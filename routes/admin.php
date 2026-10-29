<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\Access\InvitationController;
use App\Http\Controllers\Admin\Access\RoleController;
use App\Http\Controllers\Admin\Access\UserController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Settings\AppearanceController;
use App\Http\Controllers\Admin\Settings\FinanceSettingsController;
use App\Http\Controllers\Admin\Settings\ParishProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Dashboard Routes
|--------------------------------------------------------------------------
|
| Every route in this group needs the "access-admin" gate plus its own
| permission, checked either by a policy in the controller or by the
| "can" middleware below. tests/Feature/Admin/RouteCoverageTest.php fails
| when a route here is reachable without a specific permission.
|
*/

Route::middleware(['auth', 'verified', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::resource('roles', RoleController::class)->except('show');
        Route::post('roles/{role}/duplicate', [RoleController::class, 'duplicate'])->name('roles.duplicate');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::put('users/{user}/access', [UserController::class, 'updateAccess'])->name('users.access.update');
        Route::patch('users/{user}/status', [UserController::class, 'toggleActive'])->name('users.status.update');
        Route::post('users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])
            ->middleware('throttle:6,1')
            ->name('users.password-reset');

        Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('invitations.resend');
        Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('parish', [ParishProfileController::class, 'edit'])
                ->can(Permission::SettingsParishUpdate)
                ->name('parish.edit');
            Route::patch('parish', [ParishProfileController::class, 'update'])
                ->can(Permission::SettingsParishUpdate)
                ->name('parish.update');

            Route::get('appearance', [AppearanceController::class, 'edit'])
                ->can(Permission::SettingsAppearanceUpdate)
                ->name('appearance.edit');
            Route::post('appearance', [AppearanceController::class, 'update'])
                ->can(Permission::SettingsAppearanceUpdate)
                ->name('appearance.update');

            Route::get('finance', [FinanceSettingsController::class, 'edit'])
                ->can(Permission::SettingsFinanceUpdate)
                ->name('finance.edit');
            Route::patch('finance', [FinanceSettingsController::class, 'update'])
                ->can(Permission::SettingsFinanceUpdate)
                ->name('finance.update');
        });

        Route::get('activity', ActivityLogController::class)
            ->can(Permission::ActivityView)
            ->name('activity.index');
    });
