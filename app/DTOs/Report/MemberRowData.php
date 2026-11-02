<?php

declare(strict_types=1);

namespace App\DTOs\Report;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What one member recorded in the range (INV-05: expenses add up to the total).
 */
#[TypeScript]
class MemberRowData extends Data
{
    public function __construct(
        public ?int $userId,
        public string $name,
        public MoneyData $income,
        public MoneyData $expense,
        public ?float $expenseShare,
        public int $count,
    ) {}
}
