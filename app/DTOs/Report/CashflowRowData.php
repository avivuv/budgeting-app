<?php

declare(strict_types=1);

namespace App\DTOs\Report;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One period of the cash-flow report: income, expense, net and total
 * balance at its start and end (INV-07: closing of N = opening of N+1).
 */
#[TypeScript]
class CashflowRowData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public string $start,
        public string $end,
        public MoneyData $income,
        public MoneyData $expense,
        public MoneyData $net,
        public MoneyData $opening,
        public MoneyData $closing,
    ) {}
}
