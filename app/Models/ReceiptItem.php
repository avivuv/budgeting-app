<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of a receipt draft. Reached only through its (tenant-scoped) receipt.
 *
 * @property int $id
 * @property int $receipt_id
 * @property string $name
 * @property float $qty
 * @property int|null $unit_price
 * @property int $total
 * @property int|null $category_id
 */
#[Fillable(['name', 'qty', 'unit_price', 'total', 'category_id'])]
class ReceiptItem extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'unit_price' => 'integer',
            'total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Receipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}
