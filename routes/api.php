<?php

use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\Category\CategoryController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\Wallet\WalletController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController;

/*
| REST API v1, the only HTTP door for the web SPA and Android (docs/13-rest-api.md,
| contracts in docs/api-contracts). Web SPA: session cookie (Sanctum stateful);
| other clients: personal access tokens. Abilities: "read" for GET, "write" for
| changes. Family: X-Family-Id header, otherwise the user's active family.
*/

Route::prefix('v1/auth')->name('api.v1.auth.')->group(function () {
    // Web SPA only: these need the session cookie.
    Route::middleware('session.client')->group(function () {
        Route::post('login', [SessionController::class, 'store'])->middleware('throttle:login')->name('login');
        Route::post('two-factor-challenge', [SessionController::class, 'twoFactor'])->middleware('throttle:two-factor')->name('two-factor');
        Route::get('passkeys/options', [PasskeyLoginController::class, 'index'])->middleware('throttle:passkeys')->name('passkeys.options');
        Route::post('passkeys/login', [PasskeyLoginController::class, 'store'])->middleware('throttle:passkeys')->name('passkeys.login');
        Route::post('register', RegisterController::class)->middleware('throttle:6,1')->name('register');
        Route::post('logout', [SessionController::class, 'destroy'])->middleware('auth:sanctum')->name('logout');
    });

    Route::post('forgot-password', [PasswordController::class, 'forgot'])->middleware('throttle:6,1')->name('forgot-password');
    Route::post('reset-password', [PasswordController::class, 'reset'])->middleware('throttle:6,1')->name('reset-password');
    Route::post('email/verification-notification', [PasswordController::class, 'verificationNotification'])
        ->middleware(['auth:sanctum', 'throttle:6,1'])->name('verification-notification');

    // Android and other non-browser clients.
    Route::post('token', [TokenController::class, 'store'])->middleware('throttle:login')->name('token.store');
    Route::delete('token', [TokenController::class, 'destroy'])->middleware('auth:sanctum')->name('token.destroy');
});

// Profile of the signed-in user; works before the user has a family.
Route::get('v1/me', MeController::class)
    ->middleware(['auth:sanctum', 'family:optional', 'throttle:api', 'abilities:read'])
    ->name('api.v1.me');

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'family', 'throttle:api', EnsureIdempotency::class])
    ->group(function () {
        Route::middleware('abilities:read')->group(function () {
            Route::get('wallets', [WalletController::class, 'index'])->name('wallets.index');
            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
            Route::get('transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
            Route::get('summary', [ReportController::class, 'summary'])->name('summary');
            Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
            Route::get('budgets', [BudgetController::class, 'index'])->name('budgets.index');
            Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
        });

        Route::middleware('abilities:write')->group(function () {
            Route::post('wallets', [WalletController::class, 'store'])->name('wallets.store');
            Route::put('wallets/{wallet}', [WalletController::class, 'update'])->name('wallets.update');
            Route::patch('wallets/{wallet}/archive', [WalletController::class, 'archive'])->name('wallets.archive');
            Route::delete('wallets/{wallet}', [WalletController::class, 'destroy'])->name('wallets.destroy');

            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::patch('categories/{category}/archive', [CategoryController::class, 'archive'])->name('categories.archive');
            Route::post('categories/{category}/merge', [CategoryController::class, 'merge'])->name('categories.merge');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

            Route::post('transactions/parse', [TransactionController::class, 'parse'])->name('transactions.parse');
            Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
            Route::put('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
            Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
            Route::put('budgets', [BudgetController::class, 'update'])->name('budgets.update');

            Route::post('receipts', [ReceiptController::class, 'store'])->name('receipts.store');
            Route::patch('receipts/{receipt}', [ReceiptController::class, 'update'])->name('receipts.update');
            Route::post('receipts/{receipt}/confirm', [ReceiptController::class, 'confirm'])->name('receipts.confirm');
            Route::delete('receipts/{receipt}', [ReceiptController::class, 'destroy'])->name('receipts.destroy');
        });
    });
