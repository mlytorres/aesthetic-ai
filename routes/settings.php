<?php

use App\Http\Controllers\Settings\CoordinatorEmailOtpController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/security/coordinator-otp', [CoordinatorEmailOtpController::class, 'show'])
        ->name('security.coordinator-otp.show');
    Route::post('settings/security/coordinator-otp/send', [CoordinatorEmailOtpController::class, 'send'])
        ->middleware('throttle:3,1')
        ->name('security.coordinator-otp.send');
    Route::post('settings/security/coordinator-otp/verify', [CoordinatorEmailOtpController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('security.coordinator-otp.verify');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});
