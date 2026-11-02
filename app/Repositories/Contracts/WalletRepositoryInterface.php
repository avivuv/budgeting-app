<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Wallet;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Wallet>
 */
interface WalletRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Collection<int, Wallet>
     */
    public function listForFamily(bool $includeArchived = false): Collection;

    /**
     * Balance per wallet id up to and including $until (BR-C01).
     * Soft-deleted transactions are excluded (BR-B08).
     *
     * @param  list<int>  $walletIds
     * @return array<int, int>
     */
    public function balances(array $walletIds, ?string $until = null): array;

    /**
     * Money in and out per wallet between two dates (inclusive, BR-D04),
     * soft-deleted transactions excluded (BR-B08).
     *
     * @param  list<int>  $walletIds
     * @return array<int, array{income: int, expense: int, transferIn: int, transferOut: int}>
     */
    public function movements(array $walletIds, string $from, string $to): array;

    public function hasTransactions(Wallet $wallet): bool;

    /**
     * id => name for the given ids (archived/soft-deleted included), for display.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function namesByIds(array $ids): array;
}
