<?php

use App\Http\Controllers\Web\Category\CategoryController;
use App\Http\Controllers\Web\Family\OnboardingController;
use App\Http\Controllers\Web\Family\SwitchFamilyController;
use App\Http\Controllers\Web\Transaction\TransactionController;
use App\Http\Controllers\Web\Wallet\WalletController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Before a family exists.
    Route::get('onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
    Route::put('families/{family}/switch', SwitchFamilyController::class)->whereNumber('family')->name('families.switch');

    // Everything below runs inside the active family (tenant).
    Route::middleware('family')->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');

        Route::resource('transactions', TransactionController::class)->except('show');

        Route::get('wallets', [WalletController::class, 'index'])->name('wallets.index');
        Route::post('wallets', [WalletController::class, 'store'])->name('wallets.store');
        Route::put('wallets/{wallet}', [WalletController::class, 'update'])->name('wallets.update');
        Route::patch('wallets/{wallet}/archive', [WalletController::class, 'archive'])->name('wallets.archive');
        Route::delete('wallets/{wallet}', [WalletController::class, 'destroy'])->name('wallets.destroy');

        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::patch('categories/{category}/archive', [CategoryController::class, 'archive'])->name('categories.archive');
        Route::post('categories/{category}/merge', [CategoryController::class, 'merge'])->name('categories.merge');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });
});

require __DIR__.'/settings.php';
