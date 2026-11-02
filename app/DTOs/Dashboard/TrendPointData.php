<?php

declare(strict_types=1);

namespace App\DTOs\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One day of the period: cumulative expense so far (null for days still ahead)
 * and the pace of the previous period's daily average.
 */
#[TypeScript]
class TrendPointData extends Data
{
    public function __construct(
        public string $date,
        public string $label,
        public ?int $cumulative,
        public int $pace,
    ) {}
}
