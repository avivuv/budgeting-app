<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Transaction\TransactionFilterData;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<Transaction>
 */
class TransactionRepository extends BaseRepository implements TransactionRepositoryInterface
{
    private const array RELATIONS = [
        'wallet:id,name,type',
        'toWallet:id,name,type',
        'category:id,name,icon,color,type',
        'creator:id,name',
        'tags:id,name',
    ];

    private const string TOTALS_SQL = 'COALESCE(SUM(CASE WHEN type = ? THEN amount END), 0) AS income, '
        .'COALESCE(SUM(CASE WHEN type = ? THEN amount END), 0) AS expense';

    public function __construct(Transaction $model)
    {
        parent::__construct($model);
    }

    public function syncTags(Transaction $transaction, array $tagIds): void
    {
        $transaction->tags()->sync($tagIds);
    }

    public function paginateFiltered(TransactionFilterData $filter, int $perPage = 20): LengthAwarePaginator
    {
        return $this->sorted($this->filtered($filter), $filter->sort)
            ->with(self::RELATIONS)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function latestFiltered(TransactionFilterData $filter, int $limit): Collection
    {
        return $this->sorted($this->filtered($filter), '-date')
            ->with(self::RELATIONS)
            ->limit($limit)
            ->get();
    }

    public function totals(TransactionFilterData $filter): array
    {
        $row = $this->filtered($filter)
            ->toBase()
            ->selectRaw(self::TOTALS_SQL, [TransactionType::Income->value, TransactionType::Expense->value])
            ->first();

        return ['income' => (int) ($row->income ?? 0), 'expense' => (int) ($row->expense ?? 0)];
    }

    public function dailyTotals(TransactionFilterData $filter): array
    {
        $rows = $this->filtered($filter)
            ->toBase()
            ->selectRaw('date, '.self::TOTALS_SQL, [TransactionType::Income->value, TransactionType::Expense->value])
            ->where('type', '!=', TransactionType::Transfer->value)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $totals[substr((string) $row->date, 0, 10)] = ['income' => (int) $row->income, 'expense' => (int) $row->expense];
        }

        return $totals;
    }

    public function sumByCategory(TransactionFilterData $filter): array
    {
        $rows = $this->filtered($filter)
            ->toBase()
            ->whereNotNull('category_id')
            ->selectRaw('category_id, SUM(amount) AS total')
            ->groupBy('category_id')
            ->get();

        $sums = [];
        foreach ($rows as $row) {
            $sums[(int) $row->category_id] = (int) $row->total;
        }

        return $sums;
    }

    public function sumByCreator(TransactionFilterData $filter): array
    {
        $rows = $this->filtered($filter)
            ->toBase()
            ->selectRaw('COALESCE(created_by, 0) AS creator, SUM(amount) AS total, COUNT(*) AS cnt')
            ->groupByRaw('COALESCE(created_by, 0)')
            ->get();

        $sums = [];
        foreach ($rows as $row) {
            $sums[(int) $row->creator] = ['amount' => (int) $row->total, 'count' => (int) $row->cnt];
        }

        return $sums;
    }

    public function findMany(array $ids): Collection
    {
        return $this->query()->whereKey($ids)->get();
    }

    public function latestCreatedBy(User $user, \DateTimeInterface $since): ?Transaction
    {
        return $this->query()
            ->where('created_by', $user->id)
            ->where('created_at', '>=', $since)
            ->latest('id')
            ->first();
    }

    /**
     * @return Builder<Transaction>
     */
    private function filtered(TransactionFilterData $f): Builder
    {
        $search = trim((string) $f->search);
        $like = '%'.addcslashes($search, '%_\\').'%';

        return $this->query()
            ->when($f->from !== null, fn (Builder $q) => $q->where('date', '>=', $f->from))
            ->when($f->to !== null, fn (Builder $q) => $q->where('date', '<=', $f->to))
            ->when($f->type !== null, fn (Builder $q) => $q->where('type', $f->type))
            // A parent category also matches its sub-categories (BR-E04).
            ->when($f->categoryIds !== [], fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->whereIn('category_id', $f->categoryIds)
                ->orWhereIn('category_id', Category::query()->select('id')->whereIn('parent_id', $f->categoryIds))))
            ->when($f->walletIds !== [], fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->whereIn('wallet_id', $f->walletIds)
                ->orWhereIn('to_wallet_id', $f->walletIds)))
            ->when($f->userIds !== [], fn (Builder $q) => $q->whereIn('created_by', $f->userIds))
            ->when($f->tags !== [], fn (Builder $q) => $q->whereHas('tags', fn (Builder $q) => $q->whereIn('name', $f->tags)))
            ->when($f->source !== null, fn (Builder $q) => $q->where('source', $f->source))
            ->when($f->min !== null, fn (Builder $q) => $q->where('amount', '>=', $f->min))
            ->when($f->max !== null, fn (Builder $q) => $q->where('amount', '<=', $f->max))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('note', 'like', $like)
                ->orWhere('merchant', 'like', $like)));
    }

    /**
     * Whitelisted sorts only (TransactionFilterData::SORTS); id breaks ties.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    private function sorted(Builder $query, string $sort): Builder
    {
        if (! in_array($sort, TransactionFilterData::SORTS, true)) {
            $sort = '-date';
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return $query->orderBy(ltrim($sort, '-'), $direction)->orderBy('id', $direction);
    }
}
