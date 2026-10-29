<?php

use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');
});

Route::middleware(['guest', 'signed'])->group(function () {
    Route::get('invitations/{invitation}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{invitation}', [InvitationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('invitations.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/portal.php';
