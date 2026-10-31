<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepositoryInterface<Transaction>
 */
interface TransactionRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @param  list<int>  $tagIds
     */
    public function syncTags(Transaction $transaction, array $tagIds): void;

    /**
     * Latest first, with wallet, category, creator and tags loaded.
     *
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function paginateLatest(int $perPage = 20): LengthAwarePaginator;

    /**
     * Most recent transaction created by the user since the given time (BR-B10).
     */
    public function latestCreatedBy(User $user, \DateTimeInterface $since): ?Transaction;
}
