<?php

declare(strict_types=1);

namespace App\DTOs\Transaction;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Tag;
use App\Models\Transaction;
use App\Support\Money\MoneyFormatter;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A transaction as shown in lists and edit forms. Formatting happens here,
 * never in Vue (docs/15-frontend-architecture.md §1).
 */
#[TypeScript]
class TransactionItemData extends Data
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public int $id,
        public TransactionType $type,
        public string $typeLabel,
        public int $amount,
        public string $amountFormatted,
        public string $date,
        public string $dateFormatted,
        public ?string $note,
        public TransactionSource $source,
        public int $walletId,
        public string $walletName,
        public ?int $toWalletId,
        public ?string $toWalletName,
        public ?int $categoryId,
        public ?string $categoryName,
        public ?string $categoryIcon,
        public ?string $categoryColor,
        public ?string $creatorName,
        public array $tags,
        public bool $canEdit,
    ) {}

    public static function fromTransaction(Transaction $transaction, bool $canEdit): self
    {
        return new self(
            id: $transaction->id,
            type: $transaction->type,
            typeLabel: $transaction->type->label(),
            amount: $transaction->amount,
            amountFormatted: MoneyFormatter::format($transaction->amount),
            date: $transaction->date->toDateString(),
            dateFormatted: $transaction->date->translatedFormat('j M Y'),
            note: $transaction->note,
            source: $transaction->source,
            walletId: $transaction->wallet_id,
            walletName: $transaction->wallet->name ?? '-',
            toWalletId: $transaction->to_wallet_id,
            toWalletName: $transaction->toWallet?->name,
            categoryId: $transaction->category_id,
            categoryName: $transaction->category?->name,
            categoryIcon: $transaction->category?->icon,
            categoryColor: $transaction->category?->color,
            creatorName: $transaction->creator?->name,
            tags: $transaction->tags->map(fn (Tag $tag): string => $tag->name)->values()->all(),
            canEdit: $canEdit,
        );
    }
}
