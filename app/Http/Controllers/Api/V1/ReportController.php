<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Transaction\TransactionFilterData;
use App\Enums\ReportTab;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportRequest;
use App\Models\Transaction;
use App\Services\Report\DashboardService;
use App\Services\Report\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * /api/v1/summary and /api/v1/reports/* (docs/13-rest-api.md). Same numbers
 * as the dashboard and the report pages (INV-02, INV-03).
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly ReportService $reports,
    ) {}

    public function summary(ReportRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Transaction::class);

        $period = $this->dashboard->period($request->validated('period'));
        $scope = new TransactionFilterData(
            walletIds: $request->filled('wallet_id') ? [(int) $request->validated('wallet_id')] : [],
            userIds: $request->filled('user_id') ? [(int) $request->validated('user_id')] : [],
        );

        return response()->json(['data' => [
            'period' => $this->dashboard->periodData($period),
            'summary' => $this->dashboard->summary($period, $scope),
        ]]);
    }

    public function show(ReportRequest $request, ReportTab $report): JsonResponse
    {
        Gate::authorize('viewAny', Transaction::class);

        [$from, $to] = $request->range() ?? $this->reports->defaultRange();

        return response()->json(['data' => $this->reports->build(
            $report,
            $from,
            $to,
            $request->validated('year') !== null ? (int) $request->validated('year') : null,
            TransactionType::tryFrom((string) $request->validated('type')) ?? TransactionType::Expense,
        )]);
    }
}
