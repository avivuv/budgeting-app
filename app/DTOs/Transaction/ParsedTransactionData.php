<?php

declare(strict_types=1);

namespace App\DTOs\Transaction;

use App\Enums\TransactionType;
use App\Support\Money\MoneyFormatter;
use App\Support\Parsing\ParsedLine;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Preview of a transaction read from free text; nothing is saved yet.
 * Fields that could not be recognised are null and listed in $missing.
 */
#[TypeScript]
class ParsedTransactionData extends Data
{
    /**
     * @param  list<string>  $tags
     * @param  list<string>  $missing
     */
    public function __construct(
        public string $line,
        public TransactionType $type,
        public string $typeLabel,
        public ?int $amount,
        public ?string $amountFormatted,
        public string $date,
        public string $dateFormatted,
        public ?int $walletId,
        public ?string $walletName,
        public ?int $toWalletId,
        public ?string $toWalletName,
        public ?int $categoryId,
        public ?string $categoryName,
        public ?string $categorySource,
        public ?string $note,
        public array $tags,
        public array $missing,
        public bool $complete,
    ) {}

    /**
     * @param  array<int, string>  $walletNames
     * @param  array<int, string>  $categoryNames
     */
    public static function fromLine(ParsedLine $line, array $walletNames, array $categoryNames): self
    {
        return new self(
            line: $line->raw,
            type: $line->type,
            typeLabel: $line->type->label(),
            amount: $line->amount,
            amountFormatted: $line->amount !== null ? MoneyFormatter::format($line->amount) : null,
            date: $line->date->toDateString(),
            dateFormatted: $line->date->translatedFormat('j M Y'),
            walletId: $line->walletId,
            walletName: $line->walletId !== null ? ($walletNames[$line->walletId] ?? null) : null,
            toWalletId: $line->toWalletId,
            toWalletName: $line->toWalletId !== null ? ($walletNames[$line->toWalletId] ?? null) : null,
            categoryId: $line->categoryId,
            categoryName: $line->categoryId !== null ? ($categoryNames[$line->categoryId] ?? null) : null,
            categorySource: $line->categorySource,
            note: $line->note,
            tags: $line->tags,
            missing: $line->missing,
            complete: $line->missing === [],
        );
    }
}
