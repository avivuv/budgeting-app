<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Dashboard;

use App\DTOs\Transaction\TransactionItemData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Dashboard\DashboardRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Family\MembershipService;
use App\Services\Report\DashboardService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly WalletService $wallets,
        private readonly MembershipService $members,
    ) {}

    /**
     * Summary cards render first; charts and lists arrive as deferred props.
     */
    public function __invoke(DashboardRequest $request): Response
    {
        Gate::authorize('viewAny', Transaction::class);

        /** @var User $user */
        $user = $request->user();
        $period = $this->dashboard->period($request->period());
        $scope = $request->scope();

        return Inertia::render('dashboard/Index', [
            'period' => $this->dashboard->periodData($period),
            'filters' => $request->filters(),
            'walletOptions' => $this->wallets->list(includeArchived: true)
                ->map(fn (Wallet $wallet): array => ['id' => $wallet->id, 'name' => $wallet->name, 'archived' => $wallet->is_archived])
                ->values(),
            'memberOptions' => $this->members->members()
                ->map(fn (User $member): array => ['id' => $member->id, 'name' => $member->name])
                ->values(),
            'summary' => $this->dashboard->summary($period, $scope),
            'insights' => Inertia::defer(fn () => $this->dashboard->insights($period, $scope), 'charts'),
            'cashflow' => Inertia::defer(fn () => $this->dashboard->cashflow($period, $scope), 'charts'),
            'categories' => Inertia::defer(fn () => $this->dashboard->categories($period, $scope), 'charts'),
            'trend' => Inertia::defer(fn () => $this->dashboard->trend($period, $scope), 'charts'),
            'members' => Inertia::defer(fn () => $this->dashboard->members($period, $scope), 'charts'),
            'wallets' => Inertia::defer(fn () => $this->dashboard->walletBalances($period, $scope), 'lists'),
            'recent' => Inertia::defer(fn () => $this->dashboard->recent($period, $scope)
                ->map(fn (Transaction $transaction) => TransactionItemData::fromTransaction($transaction, $user->can('update', $transaction)))
                ->values(), 'lists'),
            'canCreate' => $user->can('create', Transaction::class),
        ]);
    }
}
