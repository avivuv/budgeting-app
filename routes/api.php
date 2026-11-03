<?php

use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\Budget\BudgetController;
use App\Http\Controllers\Api\V1\Category\CategoryController;
use App\Http\Controllers\Api\V1\Family\FamilyController;
use App\Http\Controllers\Api\V1\Family\InvitationController;
use App\Http\Controllers\Api\V1\Family\MemberController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Notification\NotificationController;
use App\Http\Controllers\Api\V1\Receipt\ReceiptController;
use App\Http\Controllers\Api\V1\Report\ReportController;
use App\Http\Controllers\Api\V1\Savings\SavingGoalController;
use App\Http\Controllers\Api\V1\Transaction\TransactionController;
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

// Works on any family of the user, or before joining one (invitation by token, BR-I07).
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'family:optional', 'throttle:api', EnsureIdempotency::class.':user'])
    ->group(function () {
        Route::get('invitations/token/{token}', [InvitationController::class, 'show'])->middleware('abilities:read')->name('invitations.token.show');
        Route::get('notifications', [NotificationController::class, 'index'])->middleware('abilities:read')->name('notifications.index');

        Route::middleware('abilities:write')->group(function () {
            Route::post('families', [FamilyController::class, 'store'])->name('families.store');
            Route::put('families/{family}/switch', [FamilyController::class, 'switch'])->whereNumber('family')->name('families.switch');
            Route::post('families/{family}/leave', [FamilyController::class, 'leave'])->whereNumber('family')->name('families.leave');
            Route::post('invitations/token/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.token.accept');
            Route::post('notifications/read', [NotificationController::class, 'read'])->name('notifications.read');
        });
    });

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'family', 'throttle:api', EnsureIdempotency::class])
    ->group(function () {
        Route::middleware('abilities:read')->group(function () {
            Route::get('wallets', [WalletController::class, 'index'])->name('wallets.index');
            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
            Route::get('transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
            Route::get('transactions/{transaction}/history', [TransactionController::class, 'history'])->name('transactions.history');
            Route::get('tags', [TransactionController::class, 'tags'])->name('tags.index');
            Route::get('summary', [ReportController::class, 'summary'])->name('summary');
            Route::get('dashboard/{widget}', [ReportController::class, 'widget'])->name('dashboard.widget');
            Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
            Route::get('reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');
            Route::get('budgets', [BudgetController::class, 'index'])->name('budgets.index');
            Route::get('savings', [SavingGoalController::class, 'index'])->name('savings.index');
            Route::get('members', [MemberController::class, 'index'])->name('members.index');
            Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
            Route::get('receipts', [ReceiptController::class, 'index'])->name('receipts.index');
            Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
            Route::get('receipts/{receipt}/image', [ReceiptController::class, 'image'])->name('receipts.image');
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
            Route::post('transactions/bulk', [TransactionController::class, 'bulk'])->name('transactions.bulk');
            Route::put('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
            Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
            Route::put('budgets', [BudgetController::class, 'update'])->name('budgets.update');
            Route::post('budgets/copy', [BudgetController::class, 'copy'])->name('budgets.copy');
            Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');

            Route::patch('members/{member}', [MemberController::class, 'update'])->whereNumber('member')->name('members.update');
            Route::delete('members/{member}', [MemberController::class, 'destroy'])->whereNumber('member')->name('members.destroy');
            Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->whereNumber('invitation')->name('invitations.destroy');

            Route::post('savings', [SavingGoalController::class, 'store'])->name('savings.store');
            Route::put('savings/{goal}', [SavingGoalController::class, 'update'])->name('savings.update');
            Route::post('savings/{goal}/contributions', [SavingGoalController::class, 'contribute'])->name('savings.contribute');
            Route::delete('savings/{goal}', [SavingGoalController::class, 'destroy'])->name('savings.destroy');

            Route::post('receipts', [ReceiptController::class, 'store'])->name('receipts.store');
            Route::patch('receipts/{receipt}', [ReceiptController::class, 'update'])->name('receipts.update');
            Route::post('receipts/{receipt}/confirm', [ReceiptController::class, 'confirm'])->name('receipts.confirm');
            Route::delete('receipts/{receipt}', [ReceiptController::class, 'destroy'])->name('receipts.destroy');
        });
    });
