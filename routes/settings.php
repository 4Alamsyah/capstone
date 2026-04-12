<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\AppSettingController;
use App\Http\Controllers\Settings\GeneralSettingController;
use App\Http\Controllers\Settings\RoleAccessController;
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

    Route::middleware('permission:menu.settings.general')->group(function (): void {
        Route::get('settings/general', [GeneralSettingController::class, 'edit'])->name('general-settings.edit');
        Route::patch('settings/general', [GeneralSettingController::class, 'update'])->name('general-settings.update');
        Route::post('settings/general/currencies', [GeneralSettingController::class, 'storeCurrency'])->name('general-settings.currencies.store');
        Route::patch('settings/general/currencies/{currency}/default', [GeneralSettingController::class, 'setDefaultCurrency'])->name('general-settings.currencies.default');
        Route::get('settings/general/payment-terms', [GeneralSettingController::class, 'paymentTerms'])->name('general-settings.payment-terms.edit');
        Route::post('settings/general/payment-terms', [GeneralSettingController::class, 'storePaymentTerm'])->name('general-settings.payment-terms.store');
        Route::patch('settings/general/payment-terms/{index}', [GeneralSettingController::class, 'updatePaymentTerm'])->name('general-settings.payment-terms.update');
        Route::delete('settings/general/payment-terms/{index}', [GeneralSettingController::class, 'destroyPaymentTerm'])->name('general-settings.payment-terms.destroy');
    });

    Route::middleware('permission:menu.settings.role_access')->group(function (): void {
        Route::get('settings/role-access', [RoleAccessController::class, 'edit'])->name('role-access.edit');
        Route::post('settings/role-access', [RoleAccessController::class, 'store'])->name('role-access.store');
        Route::patch('settings/role-access/{user}', [RoleAccessController::class, 'update'])->name('role-access.update');
    });

    Route::get('settings/app', [AppSettingController::class, 'edit'])->name('app-settings.edit');
    Route::patch('settings/app', [AppSettingController::class, 'update'])->name('app-settings.update');
});
