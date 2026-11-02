<?php

declare(strict_types=1);

namespace App\DTOs\Report;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A category in the category report. A parent's amount includes its
 * sub-categories (BR-E04); share is of the report total, change vs the
 * previous range of equal length (BR-A05).
 */
#[TypeScript]
class CategoryRowData extends Data
{
    /**
     * @param  list<CategoryRowData>  $children
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $color,
        public MoneyData $amount,
        public ?float $share,
        public MoneyData $previous,
        public ?float $change,
        public int $count,
        public array $children,
    ) {}
}
