<?php

declare(strict_types=1);

namespace App\DTOs\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Income vs expense of one period in the cash-flow chart.
 */
#[TypeScript]
class CashflowPointData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public int $income,
        public int $expense,
        public int $net,
    ) {}
}
