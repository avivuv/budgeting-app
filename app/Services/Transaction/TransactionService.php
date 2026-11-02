<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\DTOs\Transaction\TransactionData;
use App\DTOs\Transaction\TransactionFilterData;
use App\Enums\TransactionType;
use App\Events\Transaction\TransactionCreated;
use App\Events\Transaction\TransactionDeleted;
use App\Events\Transaction\TransactionUpdated;
use App\Exceptions\Transaction\TransactionRuleException;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\TagRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Services\Tenancy\TenantContext;
use App\Support\Money\AmountParser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Writes transactions for every channel (web, API, Telegram, receipts) so the
 * same rules apply everywhere (BR-B12).
 */
class TransactionService
{
    public const int MAX_AGE_YEARS = 5;

    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
        private readonly WalletRepositoryInterface $wallets,
        private readonly CategoryRepositoryInterface $categories,
        private readonly TagRepositoryInterface $tags,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function paginate(?TransactionFilterData $filter = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->transactions->paginateFiltered($filter ?? new TransactionFilterData, $perPage);
    }

    /**
     * Income and expense of everything matching the filter; transfers excluded (BR-B11).
     *
     * @return array{income: int, expense: int}
     */
    public function totals(TransactionFilterData $filter): array
    {
        return $this->transactions->totals($filter);
    }

    /**
     * Daily subtotals for the given dates, with the same filter (INV-06).
     *
     * @param  list<string>  $dates  Y-m-d
     * @return array<string, array{income: int, expense: int}>
     */
    public function dailyTotals(TransactionFilterData $filter, array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        // Only the span of the requested dates needs to be aggregated.
        $from = max($filter->from ?? min($dates), min($dates));
        $to = min($filter->to ?? max($dates), max($dates));

        return array_intersect_key(
            $this->transactions->dailyTotals($filter->between($from, $to)),
            array_flip($dates),
        );
    }

    /**
     * @return list<string>
     */
    public function tagNames(): array
    {
        return $this->tags->names();
    }

    public function find(int $id): Transaction
    {
        return $this->transactions->findOrFail($id);
    }

    /**
     * Transactions of the active family by id; ids of another family are not
     * found (BR-I06), so the caller can answer 404.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Transaction>
     */
    public function findMany(array $ids): Collection
    {
        $found = $this->transactions->findMany($ids);

        if ($found->count() !== count(array_unique($ids))) {
            throw (new ModelNotFoundException)->setModel(Transaction::class, $ids);
        }

        return $found;
    }

    /**
     * Bulk action from the list: soft-delete each transaction (BR-B08), each one
     * logged and invalidating caches like a single delete.
     *
     * @param  iterable<Transaction>  $transactions
     */
    public function deleteMany(iterable $transactions): int
    {
        return DB::transaction(function () use ($transactions): int {
            $count = 0;
            foreach ($transactions as $transaction) {
                $this->delete($transaction);
                $count++;
            }

            return $count;
        });
    }

    /**
     * Bulk action from the list: move income/expense transactions to another
     * category through the normal update path (BR-B02, BR-B14). Transfers have
     * no category (BR-B03) and are rejected.
     *
     * @param  iterable<Transaction>  $transactions
     */
    public function changeCategory(iterable $transactions, int $categoryId): int
    {
        return DB::transaction(function () use ($transactions, $categoryId): int {
            $count = 0;
            foreach ($transactions as $transaction) {
                if ($transaction->type === TransactionType::Transfer) {
                    throw TransactionRuleException::transferHasNoCategory();
                }

                $this->update($transaction, TransactionData::fromTransaction($transaction, categoryId: $categoryId));
                $count++;
            }

            return $count;
        });
    }

    public function create(TransactionData $data, User $actor): Transaction
    {
        $this->assertValid($data);

        return DB::transaction(function () use ($data, $actor): Transaction {
            $transaction = $this->transactions->create([
                ...$data->toModelArray(),
                'created_by' => $actor->id,
            ]);

            $this->transactions->syncTags($transaction, $this->tags->idsForNames($data->normalizedTags()));
            TransactionCreated::dispatch($transaction);

            return $transaction;
        });
    }

    public function update(Transaction $transaction, TransactionData $data): Transaction
    {
        $this->assertValid($data, $transaction);

        $previousDate = $transaction->date;

        return DB::transaction(function () use ($transaction, $data, $previousDate): Transaction {
            // Source and author never change on edit (BR-B07).
            $attributes = $data->toModelArray();
            unset($attributes['source']);

            $transaction = $this->transactions->update($transaction, $attributes);
            $this->transactions->syncTags($transaction, $this->tags->idsForNames($data->normalizedTags()));
            TransactionUpdated::dispatch($transaction, $previousDate);

            return $transaction;
        });
    }

    /**
     * Soft delete — excluded from every total afterwards (BR-B08).
     */
    public function delete(Transaction $transaction): void
    {
        $this->transactions->delete($transaction);
        TransactionDeleted::dispatch($transaction);
    }

    /**
     * BR-A02, BR-B02..B06. Lookups are tenant-scoped, so ids of another family
     * fail with "not found" (BR-B04, BR-I06).
     */
    private function assertValid(TransactionData $data, ?Transaction $existing = null): void
    {
        if ($data->amount <= 0 || $data->amount > AmountParser::MAX) {
            throw TransactionRuleException::amountOutOfRange();
        }

        $this->assertDate($data->date);

        $wallet = $this->wallets->findOrFail($data->walletId);
        if ($wallet->is_archived && $existing?->wallet_id !== $wallet->id) {
            throw TransactionRuleException::archivedWallet();
        }

        if ($data->type === TransactionType::Transfer) {
            if ($data->toWalletId === null) {
                throw TransactionRuleException::transferTargetRequired();
            }

            if ($data->toWalletId === $data->walletId) {
                throw TransactionRuleException::transferToSameWallet();
            }

            $target = $this->wallets->findOrFail($data->toWalletId);
            if ($target->is_archived && $existing?->to_wallet_id !== $target->id) {
                throw TransactionRuleException::archivedWallet('to_wallet_id');
            }

            return;
        }

        if ($data->categoryId === null) {
            throw TransactionRuleException::categoryRequired();
        }

        $category = $this->categories->findOrFail($data->categoryId);

        if ($category->type !== $data->type->categoryType()) {
            throw TransactionRuleException::categoryTypeMismatch();
        }

        if ($category->is_archived && $existing?->category_id !== $category->id) {
            throw TransactionRuleException::archivedCategory();
        }
    }

    /**
     * Not after today and not more than 5 years back, in the family's timezone (BR-B06).
     */
    private function assertDate(CarbonImmutable $date): void
    {
        $today = CarbonImmutable::now($this->tenant->get()->timezone)->startOfDay();
        $day = CarbonImmutable::parse($date->toDateString(), $this->tenant->get()->timezone);

        if ($day->greaterThan($today)) {
            throw TransactionRuleException::dateInFuture();
        }

        if ($day->lessThan($today->subYears(self::MAX_AGE_YEARS))) {
            throw TransactionRuleException::dateTooOld();
        }
    }
}
