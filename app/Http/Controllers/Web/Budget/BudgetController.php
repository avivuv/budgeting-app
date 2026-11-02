<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Budget;

use App\DTOs\Dashboard\PeriodData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Budget\BudgetRequest;
use App\Models\Budget;
use App\Models\User;
use App\Services\Budget\BudgetService;
use App\Services\Report\PeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetService $budgets,
        private readonly PeriodService $periods,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Budget::class);

        /** @var User $user */
        $user = $request->user();
        $period = $this->budgets->plannablePeriod($request->string('period')->toString() ?: null);
        $items = $this->budgets->overview($period);
        $data = PeriodData::fromPeriod($period, $this->periods->current());
        $next = $this->periods->next($period);

        return Inertia::render('budgets/Index', [
            // Planning ahead: one period after the current one is allowed.
            'period' => [
                ...$data->toArray(),
                'nextKey' => $period->key() <= $this->periods->current()->key() ? $next->key() : null,
            ],
            'budgets' => $items,
            'totals' => $this->budgets->totals($items, $period),
            'availableCategories' => $this->budgets->categoriesWithoutBudget($period),
            'canManage' => $user->can('create', Budget::class),
        ]);
    }

    /**
     * Create or change a budget (one per category and period).
     */
    public function store(BudgetRequest $request): RedirectResponse
    {
        Gate::authorize('create', Budget::class);

        $period = $this->budgets->plannablePeriod($request->validated('period'));
        $this->budgets->set((int) $request->validated('category_id'), $period, (int) $request->validated('amount'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Budget disimpan.']);

        return redirect()->route('budgets.index', ['period' => $period->key()]);
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        Gate::authorize('delete', $budget);

        $this->budgets->delete($budget);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Budget dihapus.']);

        return back();
    }

    /**
     * BR-F07: copy last period's budgets into this one.
     */
    public function copy(Request $request): RedirectResponse
    {
        Gate::authorize('create', Budget::class);

        $period = $this->budgets->plannablePeriod($request->string('period')->toString() ?: null);
        $count = $this->budgets->copyFromPrevious($period);

        Inertia::flash('toast', $count > 0
            ? ['type' => 'success', 'message' => "{$count} budget disalin dari periode lalu."]
            : ['type' => 'info', 'message' => 'Tidak ada budget baru untuk disalin.']);

        return redirect()->route('budgets.index', ['period' => $period->key()]);
    }
}
