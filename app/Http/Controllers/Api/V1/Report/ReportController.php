<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Report;

use App\Enums\DashboardWidget;
use App\Enums\ReportTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Report\DashboardRequest;
use App\Http\Requests\Api\V1\Report\ReportRequest;
use App\Http\Resources\BudgetResource;
use App\Http\Resources\TransactionResource;
use App\Http\Responses\SnakeCase;
use App\Models\Transaction;
use App\Services\Budget\BudgetService;
use App\Services\Report\DashboardService;
use App\Services\Report\ExportService;
use App\Services\Report\ReportService;
use App\Services\Savings\SavingGoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Summary, dashboard widgets, reports and exports (docs/api-contracts/reports.md).
 * Same services as the web pages and the bot, so the numbers match (INV-03).
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly ReportService $reports,
        private readonly ExportService $exports,
        private readonly BudgetService $budgets,
        private readonly SavingGoalService $goals,
    ) {}

    public function summary(DashboardRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Transaction::class);

        $period = $this->dashboard->startedPeriod($request->period());

        return response()->json(['data' => SnakeCase::keys([
            'period' => $this->dashboard->periodData($period),
            'summary' => $this->dashboard->summary($period, $request->scope()),
        ])]);
    }

    public function widget(DashboardRequest $request, DashboardWidget $widget): JsonResponse
    {
        Gate::authorize('viewAny', Transaction::class);

        $period = $this->dashboard->startedPeriod($request->period());
        $scope = $request->scope();

        $data = match ($widget) {
            DashboardWidget::Cashflow => SnakeCase::keys($this->dashboard->cashflow($period, $scope)),
            DashboardWidget::Categories => SnakeCase::keys($this->dashboard->categories($period, $scope)),
            DashboardWidget::Trend => SnakeCase::keys($this->dashboard->trend($period, $scope)),
            DashboardWidget::Members => SnakeCase::keys($this->dashboard->members($period, $scope)),
            DashboardWidget::Wallets => SnakeCase::keys($this->dashboard->walletBalances($period, $scope)),
            // Budgets are family-wide: the wallet/member filter does not apply.
            DashboardWidget::Budgets => BudgetResource::collection($this->budgets->overview($period))->resolve($request),
            DashboardWidget::Goals => SnakeCase::keys($this->goals->overview()),
            DashboardWidget::Recent => TransactionResource::collection($this->dashboard->recent($period, $scope))->resolve($request),
            DashboardWidget::Insights => $this->dashboard->insights($period, $scope),
        };

        return response()->json([
            'data' => $data,
            'meta' => ['period' => SnakeCase::keys($this->dashboard->periodData($period))],
        ]);
    }

    public function show(ReportRequest $request, ReportTab $report): JsonResponse
    {
        Gate::authorize('viewAny', Transaction::class);

        [$from, $to] = $request->range() ?? $this->reports->defaultRange();

        return response()->json(['data' => SnakeCase::keys(
            $this->reports->build($report, $from, $to, $request->year(), $request->type()),
        )]);
    }

    /**
     * Excel or PDF of a report with the same filter (docs/07 §5.1).
     */
    public function export(ReportRequest $request, ReportTab $report): Response
    {
        Gate::authorize('viewAny', Transaction::class);

        [$from, $to] = $request->range() ?? $this->reports->defaultRange();

        return $request->exportFormat() === 'pdf'
            ? $this->exports->pdf($report, $from, $to, $request->year(), $request->type())
            : $this->exports->excel($report, $from, $to, $request->year(), $request->type());
    }
}
