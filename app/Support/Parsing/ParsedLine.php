<?php

declare(strict_types=1);

namespace App\Support\Parsing;

use App\Enums\TransactionType;
use Carbon\CarbonImmutable;

/**
 * One line of free text turned into transaction fields. Anything that could
 * not be recognised stays null and is listed in $missing.
 */
final readonly class ParsedLine
{
    public const string CATEGORY_EXPLICIT = 'explicit';

    public const string CATEGORY_KEYWORD = 'keyword';

    public const string CATEGORY_HISTORY = 'history';

    /**
     * @param  list<string>  $tags
     * @param  list<string>  $missing  field names: amount, category_id, to_wallet_id
     */
    public function __construct(
        public string $raw,
        public TransactionType $type,
        public ?int $amount,
        public CarbonImmutable $date,
        public ?int $walletId,
        public ?int $toWalletId,
        public ?int $categoryId,
        public ?string $categorySource,
        public ?string $note,
        public array $tags,
        public array $missing,
    ) {}

    public function withCategory(int $categoryId, string $source): self
    {
        return new self(
            $this->raw, $this->type, $this->amount, $this->date, $this->walletId, $this->toWalletId,
            $categoryId, $source, $this->note, $this->tags,
            array_values(array_diff($this->missing, ['category_id'])),
        );
    }

    public function withWallet(int $walletId): self
    {
        return new self(
            $this->raw, $this->type, $this->amount, $this->date, $walletId, $this->toWalletId,
            $this->categoryId, $this->categorySource, $this->note, $this->tags, $this->missing,
        );
    }
}
