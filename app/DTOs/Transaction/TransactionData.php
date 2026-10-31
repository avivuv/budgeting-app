<?php

declare(strict_types=1);

namespace App\DTOs\Transaction;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
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
    ) {}

    /**
     * Columns for the transactions table. Transfers never carry a category,
     * other types never carry a destination wallet (BR-B03).
     *
     * @return array<string, mixed>
     */
    public function toModelArray(): array
    {
        $isTransfer = $this->type === TransactionType::Transfer;
        $note = $this->note !== null ? trim($this->note) : null;

        return [
            'type' => $this->type,
            'amount' => $this->amount,
            'date' => $this->date->toDateString(),
            'wallet_id' => $this->walletId,
            'to_wallet_id' => $isTransfer ? $this->toWalletId : null,
            'category_id' => $isTransfer ? null : $this->categoryId,
            'note' => $note === '' ? null : $note,
            'source' => $this->source,
        ];
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
