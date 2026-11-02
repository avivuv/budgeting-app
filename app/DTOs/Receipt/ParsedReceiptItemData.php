<?php

declare(strict_types=1);

namespace App\DTOs\Receipt;

use Spatie\LaravelData\Data;

/**
 * One line as read by the AI (docs/06 §5.4).
 */
class ParsedReceiptItemData extends Data
{
    public function __construct(
        public string $name,
        public float $qty,
        public ?int $unitPrice,
        public int $total,
        public ?string $category = null,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public static function fromOutput(array $item): ?self
    {
        $name = trim((string) ($item['name'] ?? ''));
        $total = ParsedReceiptData::integer($item['total'] ?? null);

        if ($name === '' || $total === null) {
            return null;
        }

        $qty = is_numeric($item['qty'] ?? null) ? (float) $item['qty'] : 1.0;

        return new self(
            name: mb_substr($name, 0, 120),
            qty: $qty > 0 ? round($qty, 2) : 1.0,
            unitPrice: ParsedReceiptData::integer($item['unit_price'] ?? null),
            total: $total,
            category: ParsedReceiptData::text($item['category'] ?? null),
        );
    }
}
