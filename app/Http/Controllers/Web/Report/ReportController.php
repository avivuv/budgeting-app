<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Report;

use App\Enums\ReportTab;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Report\ReportRequest;
use App\Models\Transaction;
use App\Services\Report\ExportService;
use App\Services\Report\PeriodService;
use App\Services\Report\ReportService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ExportService $exports,
        private readonly PeriodService $periods,
    ) {}

    public function index(ReportRequest $request): Response
    {
        Gate::authorize('viewAny', Transaction::class);

        [$from, $to] = $request->range() ?? $this->reports->defaultRange();
        $tab = $request->tab();
        $current = $this->periods->current();
        $previous = $this->periods->previous($current);

        return Inertia::render('reports/Index', [
            'report' => $this->reports->build(
                $tab,
                $from,
                $to,
                $request->validated('year') !== null ? (int) $request->validated('year') : null,
                TransactionType::tryFrom((string) $request->validated('type')) ?? TransactionType::Expense,
            ),
            'tabs' => collect(ReportTab::cases())->map(fn (ReportTab $t): array => ['value' => $t->value, 'label' => $t->label()])->values(),
            // Quick ranges for the filter.
            'presets' => [
                ['label' => 'Periode ini', 'from' => $current->start->toDateString(), 'to' => $current->end->toDateString()],
                ['label' => 'Periode lalu', 'from' => $previous->start->toDateString(), 'to' => $previous->end->toDateString()],
                ['label' => '3 periode terakhir', 'from' => $this->periods->previous($previous)->start->toDateString(), 'to' => $current->end->toDateString()],
                ['label' => 'Tahun ini', 'from' => $current->end->startOfYear()->toDateString(), 'to' => $current->end->toDateString()],
            ],
        ]);
    }

    /**
     * Excel or PDF of the report currently shown, with the same filter.
     */
    public function export(ReportRequest $request): HttpResponse
    {
        Gate::authorize('viewAny', Transaction::class);

        [$from, $to] = $request->range() ?? $this->reports->defaultRange();
        $year = $request->validated('year') !== null ? (int) $request->validated('year') : null;

        return $request->validated('format') === 'pdf'
            ? $this->exports->pdf($request->tab(), $from, $to, $year)
            : $this->exports->excel($request->tab(), $from, $to, $year);
    }
}
