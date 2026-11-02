<?php

declare(strict_types=1);

namespace App\DTOs\Report;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Twelve periods × top-level expense categories (heatmap), with totals.
 */
#[TypeScript]
class YearlyReportData extends Data
{
    /**
     * @param  list<string>  $months  short month labels
     * @param  list<array{id: int, name: string, values: list<int>, total: MoneyData}>  $categories
     * @param  list<MoneyData>  $monthTotals
     */
    public function __construct(
        public int $year,
        public array $months,
        public array $categories,
        public array $monthTotals,
        public MoneyData $total,
        public MoneyData $income,
        public ?string $peakMonth,
    ) {}
}
