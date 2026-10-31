<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<Transaction>
 */
class TransactionRepository extends BaseRepository implements TransactionRepositoryInterface
{
    public function __construct(Transaction $model)
    {
        parent::__construct($model);
    }

    public function syncTags(Transaction $transaction, array $tagIds): void
    {
        $transaction->tags()->sync($tagIds);
    }

    public function paginateLatest(int $perPage = 20): LengthAwarePaginator
    {
        return $this->query()
            ->with([
                'wallet:id,name,type',
                'toWallet:id,name,type',
                'category:id,name,icon,color,type',
                'creator:id,name',
                'tags:id,name',
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function latestCreatedBy(User $user, \DateTimeInterface $since): ?Transaction
    {
        return $this->query()
            ->where('created_by', $user->id)
            ->where('created_at', '>=', $since)
            ->latest('id')
            ->first();
    }
}
