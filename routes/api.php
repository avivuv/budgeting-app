<?php

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

/*
| REST API v1 — personal access tokens (Sanctum), see docs/13-rest-api.md.
| Abilities: "read" for GET, "write" for changes. Family: X-Family-Id header,
| otherwise the user's active family.
*/

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'family', 'throttle:api', EnsureIdempotency::class])
    ->group(function () {
        Route::middleware('abilities:read')->group(function () {
            Route::get('me', MeController::class)->name('me');
            Route::get('wallets', [WalletController::class, 'index'])->name('wallets.index');
            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
            Route::get('transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
        });

        Route::middleware('abilities:write')->group(function () {
            Route::post('wallets', [WalletController::class, 'store'])->name('wallets.store');
            Route::put('wallets/{wallet}', [WalletController::class, 'update'])->name('wallets.update');
            Route::delete('wallets/{wallet}', [WalletController::class, 'destroy'])->name('wallets.destroy');

            Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
            Route::put('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
            Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
        });
    });
