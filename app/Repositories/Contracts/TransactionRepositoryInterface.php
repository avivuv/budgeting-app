<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\Transaction\TransactionFilterData;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
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
     * Filtered and sorted, with wallet, category, creator and tags loaded.
     *
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function paginateFiltered(TransactionFilterData $filter, int $perPage = 20): LengthAwarePaginator;

    /**
     * The newest $limit matches of the filter, with relations loaded.
     *
     * @return Collection<int, Transaction>
     */
    public function latestFiltered(TransactionFilterData $filter, int $limit): Collection;

    /**
     * Income and expense totals; transfers are never counted (BR-B11).
     *
     * @return array{income: int, expense: int}
     */
    public function totals(TransactionFilterData $filter): array;

    /**
     * Income and expense per date (Y-m-d); dates without transactions are omitted.
     *
     * @return array<string, array{income: int, expense: int}>
     */
    public function dailyTotals(TransactionFilterData $filter): array;

    /**
     * Sum per category id, direct assignment only (roll-up happens in the service).
     *
     * @return array<int, int>
     */
    public function sumByCategory(TransactionFilterData $filter): array;

    /**
     * Sum and count per recorder (created_by); key 0 = deleted account (BR-I04, BR-I08).
     *
     * @return array<int, array{amount: int, count: int}>
     */
    public function sumByCreator(TransactionFilterData $filter): array;

    /**
     * Category used most often for the same note (case-insensitive), for the
     * history step of the category guess (BR-E05).
     */
    public function mostUsedCategoryForNote(string $note, TransactionType $type): ?int;

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Transaction>
     */
    public function findMany(array $ids): Collection;

    /**
     * Most recent transaction created by the user since the given time (BR-B10).
     */
    public function latestCreatedBy(User $user, \DateTimeInterface $since): ?Transaction;
}
