<?php

declare(strict_types=1);

namespace App\DTOs\Transaction;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use Spatie\LaravelData\Data;

/**
 * Structured filter for transaction lists and aggregates
 * (docs/16-backend-architecture.md §3.4). Dates are inclusive (BR-D04).
 */
class TransactionFilterData extends Data
{
    public const array SORTS = ['date', '-date', 'amount', '-amount'];

    /**
     * @param  list<int>  $categoryIds  parent ids also match their sub-categories
     * @param  list<int>  $walletIds  matches source or destination wallet
     * @param  list<int>  $userIds  recorded by (created_by)
     * @param  list<string>  $tags  tag names
     */
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public ?TransactionType $type = null,
        public array $categoryIds = [],
        public array $walletIds = [],
        public array $userIds = [],
        public array $tags = [],
        public ?TransactionSource $source = null,
        public ?int $min = null,
        public ?int $max = null,
        public ?string $search = null,
        public string $sort = '-date',
    ) {}

    /**
     * Same filter for another date range (e.g. the previous period).
     */
    public function between(?string $from, ?string $to): self
    {
        $copy = clone $this;
        $copy->from = $from;
        $copy->to = $to;

        return $copy;
    }

    /**
     * Stable key for caching aggregates of this filter.
     */
    public function cacheKey(): string
    {
        return hash('xxh128', serialize([
            $this->from, $this->to, $this->type?->value, $this->categoryIds, $this->walletIds,
            $this->userIds, $this->tags, $this->source?->value, $this->min, $this->max, $this->search, $this->sort,
        ]));
    }
}
