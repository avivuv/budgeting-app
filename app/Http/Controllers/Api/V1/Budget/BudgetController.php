<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Budget;

use App\DTOs\Dashboard\PeriodData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Budget\BudgetBulkRequest;
use App\Http\Requests\Api\V1\Budget\BudgetPeriodRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\User;
use App\Services\Budget\BudgetService;
use App\Services\Report\PeriodService;
use App\Support\Period\Period;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Budgets of one period (docs/api-contracts/budgets.md).
 */
class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetService $budgets,
        private readonly PeriodService $periods,
    ) {}

    public function index(BudgetPeriodRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Budget::class);

        return $this->respond($request, $this->budgets->budgetPeriod($request->period()));
    }

    /**
     * Create or change several budgets of a period; all or nothing (BR-F01).
     */
    public function update(BudgetBulkRequest $request): JsonResponse
    {
        Gate::authorize('create', Budget::class);

        $period = $this->budgets->budgetPeriod($request->validated('period'));
        $this->budgets->setMany($request->budgets(), $period);

        return $this->respond($request, $period);
    }

    /**
     * BR-F07: copy the previous period's budgets into this one.
     */
    public function copy(BudgetPeriodRequest $request): JsonResponse
    {
        Gate::authorize('create', Budget::class);

        $period = $this->budgets->budgetPeriod($request->period());
        $copied = $this->budgets->copyFromPrevious($period);

        return $this->respond($request, $period, [
            'message' => $copied > 0 ? "{$copied} budget disalin dari periode lalu." : 'Tidak ada budget baru untuk disalin.',
        ], ['copied' => $copied]);
    }

    public function destroy(Budget $budget): Response
    {
        Gate::authorize('delete', $budget);

        $this->budgets->delete($budget);

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $extra  top-level keys
     * @param  array<string, mixed>  $meta  extra meta keys
     */
    private function respond(Request $request, Period $period, array $extra = [], array $meta = []): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $items = $this->budgets->overview($period);
        $totals = $this->budgets->totals($items, $period);
        $data = PeriodData::fromPeriod($period, $this->periods->current());

        return response()->json([
            ...$extra,
            'data' => BudgetResource::collection($items)->resolve($request),
            'meta' => [
                'period' => [
                    'key' => $data->key,
                    'label' => $data->label,
                    'range' => $data->range,
                    'start' => $data->start,
                    'end' => $data->end,
                    'previous_key' => $data->previousKey,
                    'next_key' => $this->budgets->nextPlannableKey($period),
                    'is_current' => $data->isCurrent,
                ],
                'totals' => [
                    'amount' => $totals['amount'],
                    'amount_formatted' => $totals['amountFormatted'],
                    'spent' => $totals['spent'],
                    'spent_formatted' => $totals['spentFormatted'],
                    'remaining' => $totals['remaining'],
                    'remaining_formatted' => $totals['remainingFormatted'],
                    'percentage' => $totals['percentage'],
                    'status' => $totals['status'],
                    'status_label' => $totals['statusLabel'],
                    'daily_allowance' => $totals['dailyAllowance'],
                    'daily_allowance_formatted' => $totals['dailyAllowanceFormatted'],
                ],
                'available_categories' => array_map(fn (array $category): array => [
                    'id' => $category['id'],
                    'name' => $category['name'],
                    'parent_name' => $category['parentName'],
                ], $this->budgets->categoriesWithoutBudget($period)),
                'can_manage' => $user->can('create', Budget::class),
                ...$meta,
            ],
        ]);
    }
}
