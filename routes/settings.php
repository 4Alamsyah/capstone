<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\AppSettingController;
use App\Http\Controllers\Settings\GeneralSettingController;
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

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/general', [GeneralSettingController::class, 'edit'])->name('general-settings.edit');
    Route::patch('settings/general', [GeneralSettingController::class, 'update'])->name('general-settings.update');
    Route::post('settings/general/currencies', [GeneralSettingController::class, 'storeCurrency'])->name('general-settings.currencies.store');
    Route::patch('settings/general/currencies/{currency}/default', [GeneralSettingController::class, 'setDefaultCurrency'])->name('general-settings.currencies.default');

    Route::get('settings/app', [AppSettingController::class, 'edit'])->name('app-settings.edit');
    Route::patch('settings/app', [AppSettingController::class, 'update'])->name('app-settings.update');
});
