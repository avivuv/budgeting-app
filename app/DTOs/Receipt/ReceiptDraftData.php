<?php

declare(strict_types=1);

namespace App\DTOs\Receipt;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Corrections to a parsed draft before confirming (BR-H09). Items null means
 * "leave the items as they are".
 */
#[MapInputName(SnakeCaseMapper::class)]
class ReceiptDraftData extends Data
{
    /**
     * @param  list<ReceiptItemInputData>|null  $items
     */
    public function __construct(
        public int $total,
        public string $receiptDate,
        public ?string $merchant = null,
        public ?int $categoryId = null,
        public ?int $walletId = null,
        #[DataCollectionOf(ReceiptItemInputData::class)]
        public ?array $items = null,
    ) {}
}
