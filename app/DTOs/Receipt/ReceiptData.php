<?php

declare(strict_types=1);

namespace App\DTOs\Receipt;

use App\Enums\ReceiptDocumentType;
use App\Enums\ReceiptStatus;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Transaction;
use App\Support\Money\MoneyFormatter;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A receipt draft for the review screen and the API (docs/06 §8).
 */
#[TypeScript]
class ReceiptData extends Data
{
    /**
     * @param  list<string>  $flags
     * @param  list<ReceiptItemData>  $items
     * @param  list<int>  $transactionIds
     */
    public function __construct(
        public int $id,
        public ReceiptStatus $status,
        public string $statusLabel,
        public ?ReceiptDocumentType $documentType,
        public ?string $documentTypeLabel,
        public string $mimeType,
        public bool $hasFile,
        public ?string $merchant,
        public ?string $date,
        public ?int $total,
        public ?string $totalFormatted,
        public int $discount,
        public int $tax,
        public ?string $paymentMethod,
        public ?int $categoryId,
        public ?int $walletId,
        public ?float $confidence,
        public array $flags,
        public ?string $error,
        public array $items,
        public int $itemsTotal,
        public array $transactionIds,
        public ?string $expiresAt,
        public string $createdAt,
    ) {}

    public static function fromReceipt(Receipt $receipt): self
    {
        $items = $receipt->relationLoaded('items') ? $receipt->items : collect();
        $transactions = $receipt->relationLoaded('transactions') ? $receipt->transactions : collect();

        return new self(
            id: $receipt->id,
            status: $receipt->status,
            statusLabel: $receipt->status->label(),
            documentType: $receipt->document_type,
            documentTypeLabel: $receipt->document_type?->label(),
            mimeType: $receipt->mime_type,
            hasFile: $receipt->file_path !== null,
            merchant: $receipt->merchant,
            date: $receipt->receipt_date?->toDateString(),
            total: $receipt->total,
            totalFormatted: $receipt->total !== null ? MoneyFormatter::format($receipt->total) : null,
            discount: $receipt->discount,
            tax: $receipt->tax,
            paymentMethod: $receipt->payment_method,
            categoryId: $receipt->suggested_category_id,
            walletId: $receipt->suggested_wallet_id,
            confidence: $receipt->confidence,
            flags: $receipt->flags ?? [],
            error: $receipt->error,
            items: array_values($items->map(fn (ReceiptItem $item): ReceiptItemData => ReceiptItemData::fromItem($item))->all()),
            itemsTotal: (int) $items->sum('total'),
            transactionIds: array_values($transactions->map(fn (Transaction $t): int => $t->id)->all()),
            expiresAt: $receipt->expires_at?->toIso8601String(),
            createdAt: $receipt->created_at?->toIso8601String() ?? '',
        );
    }
}
