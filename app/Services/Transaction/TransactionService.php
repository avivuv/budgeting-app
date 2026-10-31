<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\DTOs\Transaction\TransactionData;
use App\Enums\TransactionType;
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
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->transactions->paginateLatest($perPage);
    }

    public function find(int $id): Transaction
    {
        return $this->transactions->findOrFail($id);
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

            return $transaction;
        });
    }

    public function update(Transaction $transaction, TransactionData $data): Transaction
    {
        $this->assertValid($data, $transaction);

        return DB::transaction(function () use ($transaction, $data): Transaction {
            // Source and author never change on edit (BR-B07).
            $attributes = $data->toModelArray();
            unset($attributes['source']);

            $transaction = $this->transactions->update($transaction, $attributes);
            $this->transactions->syncTags($transaction, $this->tags->idsForNames($data->normalizedTags()));

            return $transaction;
        });
    }

    /**
     * Soft delete — excluded from every total afterwards (BR-B08).
     */
    public function delete(Transaction $transaction): void
    {
        $this->transactions->delete($transaction);
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
