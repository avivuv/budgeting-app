<?php

declare(strict_types=1);

namespace App\DTOs\Transaction;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Tag;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Input for creating/updating a transaction — identical for web, API and Telegram (BR-B12).
 */
#[MapInputName(SnakeCaseMapper::class)]
class TransactionData extends Data
{
    /**
     * @param  list<string>  $tags  tag names, created on demand
     */
    public function __construct(
        public TransactionType $type,
        public int $amount,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        public CarbonImmutable $date,
        public int $walletId,
        public ?int $toWalletId = null,
        public ?int $categoryId = null,
        public ?string $note = null,
        public TransactionSource $source = TransactionSource::Web,
        public array $tags = [],
        public ?string $merchant = null,
        public ?int $receiptId = null,
    ) {}

    /**
     * The current values of a stored transaction, optionally with a new category
     * (used by bulk actions so they go through the normal update path).
     */
    public static function fromTransaction(Transaction $transaction, ?int $categoryId = null): self
    {
        return new self(
            type: $transaction->type,
            amount: $transaction->amount,
            date: $transaction->date,
            walletId: $transaction->wallet_id,
            toWalletId: $transaction->to_wallet_id,
            categoryId: $categoryId ?? $transaction->category_id,
            note: $transaction->note,
            source: $transaction->source,
            tags: array_values($transaction->tags->map(fn (Tag $tag): string => $tag->name)->all()),
            merchant: $transaction->merchant,
            receiptId: $transaction->receipt_id,
        );
    }

    /**
     * Columns for the transactions table. Transfers never carry a category,
     * other types never carry a destination wallet (BR-B03). Merchant and
     * receipt come only from receipts, so a form without them keeps them.
     *
     * @return array<string, mixed>
     */
    public function toModelArray(): array
    {
        $isTransfer = $this->type === TransactionType::Transfer;
        $note = $this->note !== null ? trim($this->note) : null;

        $attributes = [
            'type' => $this->type,
            'amount' => $this->amount,
            'date' => $this->date->toDateString(),
            'wallet_id' => $this->walletId,
            'to_wallet_id' => $isTransfer ? $this->toWalletId : null,
            'category_id' => $isTransfer ? null : $this->categoryId,
            'note' => $note === '' ? null : $note,
            'source' => $this->source,
        ];

        if ($this->merchant !== null) {
            $attributes['merchant'] = $this->merchant;
        }

        if ($this->receiptId !== null) {
            $attributes['receipt_id'] = $this->receiptId;
        }

        return $attributes;
    }

    /**
     * @return list<string>
     */
    public function normalizedTags(): array
    {
        $names = array_map(
            fn (string $tag): string => mb_strtolower(ltrim(trim($tag), '#')),
            $this->tags,
        );

        return array_values(array_unique(array_filter($names, fn (string $tag): bool => $tag !== '')));
    }
}
