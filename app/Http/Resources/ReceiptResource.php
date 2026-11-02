<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Support\Money\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A receipt draft (docs/13 §3): status, reading and corrections. While the
 * status is "parsed" no transaction exists yet (BR-H08).
 *
 * @mixin Receipt
 */
class ReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'document_type' => $this->document_type?->value,
            'mime_type' => $this->mime_type,
            'merchant' => $this->merchant,
            'date' => $this->receipt_date?->toDateString(),
            'total' => $this->total,
            'total_formatted' => $this->total !== null ? MoneyFormatter::format($this->total) : null,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'payment_method' => $this->payment_method,
            'category_id' => $this->suggested_category_id,
            'wallet_id' => $this->suggested_wallet_id,
            'confidence' => $this->confidence,
            'flags' => $this->flags ?? [],
            'error' => $this->error,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (ReceiptItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'qty' => $item->qty,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
                'category_id' => $item->category_id,
            ])->values()),
            'transactions' => $this->whenLoaded('transactions', fn () => TransactionResource::collection($this->transactions)),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
