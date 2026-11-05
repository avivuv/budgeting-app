<?php

use App\Http\Controllers\Web\Budget\BudgetController;
use App\Http\Controllers\Web\Category\CategoryController;
use App\Http\Controllers\Web\Dashboard\DashboardController;
use App\Http\Controllers\Web\Family\InvitationController;
use App\Http\Controllers\Web\Family\MemberController;
use App\Http\Controllers\Web\Family\OnboardingController;
use App\Http\Controllers\Web\Family\SwitchFamilyController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\Receipt\ReceiptController;
use App\Http\Controllers\Web\Report\ReportController;
use App\Http\Controllers\Web\Savings\SavingGoalController;
use App\Http\Controllers\Web\SpaController;
use App\Http\Controllers\Web\Transaction\TransactionController;
use App\Http\Controllers\Web\Wallet\WalletController;
use Illuminate\Support\Facades\Route;

// No public landing page: guests end up on the login page, members on the dashboard.
Route::redirect('/', '/dashboard')->name('home');

// Web SPA (frontend/) during the migration; the SPA does its own auth checks.
Route::get('app/{path?}', SpaController::class)->where('path', '.*')->name('spa');

Route::middleware(['auth', 'verified'])->group(function () {
    // Before a family exists.
    Route::get('onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
    Route::put('families/{family}/switch', SwitchFamilyController::class)->whereNumber('family')->name('families.switch');

    // The invitee is not a member yet: resolved by token, no family context (BR-I07).
    Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

    // Everything below runs inside the active family (tenant).
    Route::middleware('family')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::post('transactions/bulk', [TransactionController::class, 'bulk'])->name('transactions.bulk');
        Route::post('transactions/parse', [TransactionController::class, 'parse'])->name('transactions.parse');
        Route::resource('transactions', TransactionController::class)->except('show');

        Route::get('receipts', [ReceiptController::class, 'index'])->name('receipts.index');
        Route::post('receipts', [ReceiptController::class, 'store'])->name('receipts.store');
        Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
        Route::get('receipts/{receipt}/image', [ReceiptController::class, 'image'])->name('receipts.image');
        Route::put('receipts/{receipt}', [ReceiptController::class, 'update'])->name('receipts.update');
        Route::post('receipts/{receipt}/confirm', [ReceiptController::class, 'confirm'])->name('receipts.confirm');
        Route::delete('receipts/{receipt}', [ReceiptController::class, 'destroy'])->name('receipts.destroy');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read', [NotificationController::class, 'read'])->name('notifications.read');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

        Route::get('budgets', [BudgetController::class, 'index'])->name('budgets.index');
        Route::post('budgets', [BudgetController::class, 'store'])->name('budgets.store');
        Route::post('budgets/copy', [BudgetController::class, 'copy'])->name('budgets.copy');
        Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');

        Route::get('savings', [SavingGoalController::class, 'index'])->name('savings.index');
        Route::post('savings', [SavingGoalController::class, 'store'])->name('savings.store');
        Route::put('savings/{goal}', [SavingGoalController::class, 'update'])->name('savings.update');
        Route::post('savings/{goal}/contribute', [SavingGoalController::class, 'contribute'])->name('savings.contribute');
        Route::delete('savings/{goal}', [SavingGoalController::class, 'destroy'])->name('savings.destroy');

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

        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::post('members/invitations', [MemberController::class, 'invite'])->name('members.invite');
        Route::delete('members/invitations/{invitation}', [MemberController::class, 'revokeInvitation'])->name('members.invitations.destroy');
        Route::patch('members/{member}', [MemberController::class, 'updateRole'])->whereNumber('member')->name('members.update');
        Route::delete('members/{member}', [MemberController::class, 'remove'])->whereNumber('member')->name('members.destroy');
        Route::post('family/leave', [MemberController::class, 'leave'])->name('family.leave');
    });
});

require __DIR__.'/settings.php';
