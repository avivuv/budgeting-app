<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Receipt;
use App\Models\ReceiptItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Receipt>
 */
interface ReceiptRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * BR-H04: the same file is already pending, a draft or confirmed.
     */
    public function hasActiveFile(string $hash): bool;

    /**
     * BR-H04: another draft or confirmed receipt with the same merchant, date and total.
     */
    public function hasSimilar(Receipt $receipt): bool;

    /**
     * Re-read with a row lock, inside a database transaction (BR-H07).
     */
    public function findForUpdate(int $id): Receipt;

    /**
     * @param  list<array{name: string, qty: float, unit_price: int|null, total: int, category_id: int|null}>  $items
     */
    public function replaceItems(Receipt $receipt, array $items): void;

    /**
     * @return Collection<int, ReceiptItem>
     */
    public function items(Receipt $receipt): Collection;

    /**
     * Latest uploads of the family with items, newest first.
     *
     * @return Collection<int, Receipt>
     */
    public function recent(int $limit = 10): Collection;

    /**
     * Drafts whose confirmation window has passed (BR-H10).
     *
     * @return Collection<int, Receipt>
     */
    public function expiredDrafts(CarbonInterface $now): Collection;

    /**
     * Rejected, expired or failed receipts that still have a file and were
     * last changed before the given moment (BR-H10).
     *
     * @return Collection<int, Receipt>
     */
    public function withStaleFiles(CarbonInterface $before): Collection;
}
