<?php

declare(strict_types=1);

namespace App\DTOs\Receipt;

use App\Enums\ReceiptDocumentType;
use Spatie\LaravelData\Data;

/**
 * What a receipt parser read from a file — the same shape for every provider
 * (docs/06 §5.4). Amounts are whole rupiah; nothing is saved from it until the
 * user confirms (BR-H08).
 */
class ParsedReceiptData extends Data
{
    /**
     * @param  list<ParsedReceiptItemData>  $items
     * @param  array<string, mixed>  $raw  untouched provider output, kept for audit (BR-H09)
     */
    public function __construct(
        public bool $isReceipt,
        public ?ReceiptDocumentType $documentType = null,
        public ?string $merchant = null,
        public ?string $date = null,
        public array $items = [],
        public int $discount = 0,
        public int $tax = 0,
        public ?int $total = null,
        public ?string $paymentMethod = null,
        public ?string $suggestedWallet = null,
        public ?string $suggestedCategory = null,
        public ?float $confidence = null,
        public ?string $model = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function notAReceipt(?string $model = null, array $raw = []): self
    {
        return new self(isReceipt: false, model: $model, raw: $raw);
    }

    /**
     * From the JSON schema of docs/06 §5.4. Missing or malformed values become
     * null instead of failing, so a partial reading can still be reviewed.
     *
     * @param  array<string, mixed>  $output
     * @param  array<string, mixed>  $raw
     */
    public static function fromOutput(array $output, ?string $model = null, array $raw = []): self
    {
        $total = self::integer($output['total'] ?? null);

        if (($output['is_receipt'] ?? true) === false || $total === null || $total <= 0) {
            return self::notAReceipt($model, $raw);
        }

        $items = [];
        foreach (is_array($output['items'] ?? null) ? $output['items'] : [] as $item) {
            if (is_array($item) && ($parsed = ParsedReceiptItemData::fromOutput($item)) !== null) {
                $items[] = $parsed;
            }
        }

        $confidence = is_numeric($output['confidence'] ?? null) ? (float) $output['confidence'] : null;

        return new self(
            isReceipt: true,
            documentType: ReceiptDocumentType::tryFrom((string) ($output['document_type'] ?? '')) ?? ReceiptDocumentType::Receipt,
            merchant: self::text($output['merchant'] ?? null, 120),
            date: self::text($output['date'] ?? null, 10),
            items: $items,
            discount: abs(self::integer($output['discount'] ?? null) ?? 0),
            tax: abs(self::integer($output['tax'] ?? null) ?? 0),
            total: $total,
            paymentMethod: self::text($output['payment_method'] ?? null, 50),
            suggestedWallet: self::text($output['suggested_wallet'] ?? null),
            suggestedCategory: self::text($output['suggested_category'] ?? null),
            confidence: $confidence !== null ? max(0.0, min(1.0, round($confidence, 2))) : null,
            model: $model,
            raw: $raw,
        );
    }

    /**
     * Whole rupiah from a JSON number or numeric string; fractions are rounded.
     */
    public static function integer(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) round((float) $value);
        }

        return null;
    }

    public static function text(mixed $value, int $max = 255): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, $max);
    }
}
