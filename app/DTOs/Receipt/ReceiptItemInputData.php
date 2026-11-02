<?php

declare(strict_types=1);

namespace App\DTOs\Receipt;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A draft line as corrected by the user (BR-H09).
 */
#[MapInputName(SnakeCaseMapper::class)]
class ReceiptItemInputData extends Data
{
    public function __construct(
        public string $name,
        public int $total,
        public float $qty = 1.0,
        public ?int $unitPrice = null,
        public ?int $categoryId = null,
    ) {}
}
