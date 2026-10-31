<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tag;
use App\Models\Transaction;
use App\Support\Money\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'amount' => $this->amount,
            'amount_formatted' => MoneyFormatter::format($this->amount),
            'date' => $this->date->toDateString(),
            'note' => $this->note,
            'source' => $this->source->value,
            'wallet' => $this->whenLoaded('wallet', fn () => ['id' => $this->wallet->id, 'name' => $this->wallet->name]),
            'to_wallet' => $this->whenLoaded('toWallet', fn () => $this->toWallet ? ['id' => $this->toWallet->id, 'name' => $this->toWallet->name] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn (Tag $tag): string => $tag->name)->values()),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
