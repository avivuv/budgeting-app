<?php

declare(strict_types=1);

namespace App\DTOs\Report;

use App\Support\Money\MoneyFormatter;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An amount with its display form (BR-A06).
 */
#[TypeScript]
class MoneyData extends Data
{
    public function __construct(
        public int $amount,
        public string $formatted,
    ) {}

    public static function of(int $amount): self
    {
        return new self($amount, MoneyFormatter::format($amount));
    }
}
