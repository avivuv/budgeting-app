<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Repositories\Contracts\WalletRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Wallet>
 */
class WalletRepository extends BaseRepository implements WalletRepositoryInterface
{
    public function __construct(Wallet $model)
    {
        parent::__construct($model);
    }

    public function listForFamily(bool $includeArchived = false): Collection
    {
        return $this->query()
            ->when(! $includeArchived, fn ($q) => $q->where('is_archived', false))
            ->orderBy('is_archived')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function balances(array $walletIds, ?string $until = null): array
    {
        if ($walletIds === []) {
            return [];
        }

        $balances = $this->query()
            ->whereIn('id', $walletIds)
            ->pluck('initial_balance', 'id')
            ->map(fn ($balance): int => (int) $balance)
            ->all();

        $base = Transaction::query()->when($until !== null, fn ($q) => $q->whereDate('date', '<=', $until));

        // income (+), expense (-), transfer out (-) on the source wallet
        $outgoing = (clone $base)
            ->whereIn('wallet_id', $walletIds)
            ->selectRaw(
                'wallet_id, SUM(CASE WHEN type = ? THEN amount ELSE -amount END) as delta',
                [TransactionType::Income->value],
            )
            ->groupBy('wallet_id')
            ->pluck('delta', 'wallet_id');

        // transfer in (+) on the destination wallet
        $incoming = (clone $base)
            ->where('type', TransactionType::Transfer)
            ->whereIn('to_wallet_id', $walletIds)
            ->selectRaw('to_wallet_id, SUM(amount) as delta')
            ->groupBy('to_wallet_id')
            ->pluck('delta', 'to_wallet_id');

        foreach ($balances as $id => $balance) {
            $balances[$id] = $balance + (int) ($outgoing[$id] ?? 0) + (int) ($incoming[$id] ?? 0);
        }

        return $balances;
    }

    public function movements(array $walletIds, string $from, string $to): array
    {
        if ($walletIds === []) {
            return [];
        }

        $base = Transaction::query()->whereBetween('date', [$from, $to]);

        // income / expense / transfer out, booked on the source wallet
        $outgoing = [];
        foreach ((clone $base)->toBase()->whereIn('wallet_id', $walletIds)->select('wallet_id', 'type')->selectRaw('SUM(amount) AS total')->groupBy('wallet_id', 'type')->get() as $row) {
            $outgoing[(int) $row->wallet_id][(string) $row->type] = (int) $row->total;
        }

        // transfer in, booked on the destination wallet
        $incoming = [];
        foreach ((clone $base)->toBase()->where('type', TransactionType::Transfer)->whereIn('to_wallet_id', $walletIds)->select('to_wallet_id')->selectRaw('SUM(amount) AS total')->groupBy('to_wallet_id')->get() as $row) {
            $incoming[(int) $row->to_wallet_id] = (int) $row->total;
        }

        $movements = [];
        foreach ($walletIds as $id) {
            $movements[$id] = [
                'income' => $outgoing[$id][TransactionType::Income->value] ?? 0,
                'expense' => $outgoing[$id][TransactionType::Expense->value] ?? 0,
                'transferIn' => $incoming[$id] ?? 0,
                'transferOut' => $outgoing[$id][TransactionType::Transfer->value] ?? 0,
            ];
        }

        return $movements;
    }

    public function hasTransactions(Wallet $wallet): bool
    {
        return Transaction::withTrashed()
            ->where(fn ($q) => $q->where('wallet_id', $wallet->id)->orWhere('to_wallet_id', $wallet->id))
            ->exists();
    }

    public function namesByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = $this->query()->whereIn('id', $ids)->pluck('name', 'id')->all();

        return $names;
    }
}
