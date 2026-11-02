<?php

declare(strict_types=1);

namespace App\DTOs\Report;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Mutations of one wallet: closing = opening + income − expense
 * + transfer in − transfer out (BR-C01).
 */
#[TypeScript]
class WalletMovementData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $typeLabel,
        public bool $isArchived,
        public MoneyData $opening,
        public MoneyData $income,
        public MoneyData $expense,
        public MoneyData $transferIn,
        public MoneyData $transferOut,
        public MoneyData $closing,
    ) {}
}
