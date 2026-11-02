<?php

declare(strict_types=1);

namespace App\DTOs\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Expense recorded by one member in the period (INV-05).
 */
#[TypeScript]
class MemberTotalData extends Data
{
    public function __construct(
        public ?int $userId,
        public string $name,
        public int $amount,
        public string $formatted,
        public int $count,
        public ?float $percentage,
    ) {}
}
