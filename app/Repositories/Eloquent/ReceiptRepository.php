<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\ReceiptStatus;
use App\Models\Receipt;
use App\Repositories\Contracts\ReceiptRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Receipt>
 */
class ReceiptRepository extends BaseRepository implements ReceiptRepositoryInterface
{
    public function __construct(Receipt $model)
    {
        parent::__construct($model);
    }

    public function hasActiveFile(string $hash): bool
    {
        return $this->query()
            ->where('file_hash', $hash)
            ->whereIn('status', ReceiptStatus::active())
            ->exists();
    }

    public function hasSimilar(Receipt $receipt): bool
    {
        if ($receipt->merchant === null || $receipt->receipt_date === null || $receipt->total === null) {
            return false;
        }

        return $this->query()
            ->whereKeyNot($receipt->id)
            ->whereIn('status', [ReceiptStatus::Parsed, ReceiptStatus::Confirmed])
            ->where('merchant', $receipt->merchant)
            ->whereDate('receipt_date', $receipt->receipt_date->toDateString())
            ->where('total', $receipt->total)
            ->exists();
    }

    public function findForUpdate(int $id): Receipt
    {
        return $this->query()->lockForUpdate()->findOrFail($id);
    }

    public function replaceItems(Receipt $receipt, array $items): void
    {
        $receipt->items()->delete();
        $receipt->items()->createMany($items);
        $receipt->unsetRelation('items');
    }

    public function items(Receipt $receipt): Collection
    {
        return $receipt->items()->get();
    }

    public function recent(int $limit = 10): Collection
    {
        return $this->query()
            ->withCount('items')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function expiredDrafts(CarbonInterface $now): Collection
    {
        return $this->query()
            ->where('status', ReceiptStatus::Parsed)
            ->where('expires_at', '<', $now)
            ->get();
    }

    public function withStaleFiles(CarbonInterface $before): Collection
    {
        return $this->query()
            ->whereIn('status', [ReceiptStatus::Rejected, ReceiptStatus::Expired, ReceiptStatus::Failed])
            ->whereNotNull('file_path')
            ->where('updated_at', '<', $before)
            ->get();
    }
}
