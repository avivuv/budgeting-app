<?php

declare(strict_types=1);

namespace App\DTOs\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Summary cards: balance at the end of the period (BR-C02) and the period's
 * income, expense and net, each against the previous period.
 */
#[TypeScript]
class DashboardSummaryData extends Data
{
    public function __construct(
        public StatData $balance,
        public StatData $income,
        public StatData $expense,
        public StatData $net,
    ) {}
}
