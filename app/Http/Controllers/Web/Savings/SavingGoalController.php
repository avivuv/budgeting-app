<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Savings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Savings\ContributeRequest;
use App\Http\Requests\Web\Savings\SavingGoalRequest;
use App\Models\SavingGoal;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Savings\SavingGoalService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SavingGoalController extends Controller
{
    public function __construct(
        private readonly SavingGoalService $goals,
        private readonly WalletService $wallets,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SavingGoal::class);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('savings/Index', [
            'goals' => $this->goals->overview(),
            'wallets' => $this->wallets->list()->map(fn (Wallet $wallet): array => ['id' => $wallet->id, 'name' => $wallet->name])->values(),
            'canManage' => $user->can('create', SavingGoal::class),
            'canContribute' => $user->can('contribute', new SavingGoal),
        ]);
    }

    public function store(SavingGoalRequest $request): RedirectResponse
    {
        Gate::authorize('create', SavingGoal::class);

        $goal = $this->goals->create($request->toData());

        return $this->done("Tujuan {$goal->name} dibuat.");
    }

    public function update(SavingGoalRequest $request, SavingGoal $goal): RedirectResponse
    {
        Gate::authorize('update', $goal);

        $this->goals->update($goal, $request->toData());

        return $this->done('Tujuan diperbarui.');
    }

    public function contribute(ContributeRequest $request, SavingGoal $goal): RedirectResponse
    {
        Gate::authorize('contribute', $goal);

        $this->goals->contribute($goal, $request->signedAmount());

        return $this->done($request->signedAmount() > 0 ? 'Tabungan ditambahkan.' : 'Tabungan diambil.');
    }

    public function destroy(SavingGoal $goal): RedirectResponse
    {
        Gate::authorize('delete', $goal);

        $this->goals->delete($goal);

        return $this->done('Tujuan dihapus.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('savings.index');
    }
}
