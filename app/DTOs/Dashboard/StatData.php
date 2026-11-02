<?php

declare(strict_types=1);

namespace App\DTOs\Dashboard;

use App\Support\Math\Percentage;
use App\Support\Money\MoneyFormatter;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An amount compared with the previous period (BR-A05: change is null = "Baru").
 */
#[TypeScript]
class StatData extends Data
{
    public function __construct(
        public int $amount,
        public string $formatted,
        public int $previous,
        public ?float $change,
    ) {}

    public static function compare(int $amount, int $previous): self
    {
        return new self($amount, MoneyFormatter::format($amount), $previous, Percentage::change($amount, $previous));
    }
}
