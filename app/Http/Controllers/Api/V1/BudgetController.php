<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Dashboard\PeriodData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BudgetBulkRequest;
use App\Models\Budget;
use App\Services\Budget\BudgetService;
use App\Services\Report\PeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * GET /api/v1/budgets?period=2026-10 and PUT /api/v1/budgets (docs/13 §3).
 */
class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetService $budgets,
        private readonly PeriodService $periods,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Budget::class);

        $period = $this->budgets->plannablePeriod($request->string('period')->toString() ?: null);

        return $this->respond($period->key());
    }

    /**
     * Set several budgets of a period; all or nothing.
     */
    public function update(BudgetBulkRequest $request): JsonResponse
    {
        Gate::authorize('create', Budget::class);

        $period = $this->budgets->plannablePeriod($request->validated('period'));
        $this->budgets->setMany($request->budgets(), $period);

        return $this->respond($period->key());
    }

    private function respond(string $key): JsonResponse
    {
        $period = $this->budgets->plannablePeriod($key);
        $items = $this->budgets->overview($period);

        return response()->json(['data' => [
            'period' => PeriodData::fromPeriod($period, $this->periods->current()),
            'budgets' => $items,
            'totals' => $this->budgets->totals($items, $period),
        ]]);
    }
}
