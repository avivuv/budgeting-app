<?php

declare(strict_types=1);

namespace App\DTOs\Report;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A named total in a ranking (merchants, tags).
 */
#[TypeScript]
class RankedRowData extends Data
{
    public function __construct(
        public string $name,
        public MoneyData $amount,
        public int $count,
        public ?float $share,
    ) {}
}
