<?php

declare(strict_types=1);

namespace App\DTOs\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Expense of a top-level category including its sub-categories (BR-E04).
 */
#[TypeScript]
class CategorySliceData extends Data
{
    public function __construct(
        public int $categoryId,
        public string $name,
        public ?string $color,
        public ?string $icon,
        public int $amount,
        public string $formatted,
        public ?float $percentage,
    ) {}
}
