<?php

use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\FamilyController;
use App\Http\Controllers\Portal\ProfileController;
use App\Http\Middleware\EnsureMemberLinked;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Member Portal Routes
|--------------------------------------------------------------------------
|
| The portal is for parishioners. The home page is open to every signed-in
| user; the rest needs the account to be linked to a member record.
|
*/

Route::middleware(['auth', 'verified'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::middleware(EnsureMemberLinked::class)->group(function () {
            Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
            Route::post('profile/changes', [ProfileController::class, 'store'])->name('profile.changes.store');
            Route::delete('profile/changes', [ProfileController::class, 'destroy'])->name('profile.changes.destroy');

            Route::get('family', FamilyController::class)->name('family.show');
        });
    });
