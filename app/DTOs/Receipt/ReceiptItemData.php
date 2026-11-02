<?php

declare(strict_types=1);

namespace App\DTOs\Receipt;

use App\Models\ReceiptItem;
use App\Support\Money\MoneyFormatter;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ReceiptItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public float $qty,
        public ?int $unitPrice,
        public int $total,
        public string $totalFormatted,
        public ?int $categoryId,
    ) {}

    public static function fromItem(ReceiptItem $item): self
    {
        return new self(
            id: $item->id,
            name: $item->name,
            qty: $item->qty,
            unitPrice: $item->unit_price,
            total: $item->total,
            totalFormatted: MoneyFormatter::format($item->total),
            categoryId: $item->category_id,
        );
    }
}
